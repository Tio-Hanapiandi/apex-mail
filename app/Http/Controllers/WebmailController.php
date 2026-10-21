<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\MailAccount;
use App\Models\MailFolder;
use App\Services\MailRelayService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class WebmailController extends Controller
{
    protected MailRelayService $relayService;

    public function __construct(MailRelayService $relayService)
    {
        $this->relayService = $relayService;
    }

    /**
     * Get the authenticated mail account strictly via Auth.
     */
    protected function getActiveAccount(): MailAccount
    {
        return Auth::user();
    }

    /**
     * Display the 3-column webmail interface.
     */
    public function index(Request $request)
    {
        $currentAccount = $this->getActiveAccount();

        // Ensure default folders exist
        $currentAccount->createDefaultFolders();

        // Real-time unread count for each folder
        $folders = $currentAccount->folders()->withCount(['emails as unread_count' => function ($q) {
            $q->where('is_read', false);
        }])->get();

        // Selected folder (default to inbox)
        $currentFolderSlug = $request->query('folder', 'inbox');
        $currentFolder = $folders->firstWhere('slug', $currentFolderSlug) ?? $folders->first();

        // Search query
        $query = $request->query('q');

        // Email list query
        $emailsQuery = $currentFolder ? $currentFolder->emails() : Email::where('mail_account_id', $currentAccount->id);

        if ($query) {
            $emailsQuery->where(function ($q) use ($query) {
                $q->where('subject', 'like', "%{$query}%")
                  ->orWhere('from_name', 'like', "%{$query}%")
                  ->orWhere('from_email', 'like', "%{$query}%")
                  ->orWhere('snippet', 'like', "%{$query}%")
                  ->orWhere('body_text', 'like', "%{$query}%");
            });
        }

        // Filter: all, unread, starred, attachments
        $filter = $request->query('filter', 'all');
        if ($filter === 'unread') {
            $emailsQuery->where('is_read', false);
        } elseif ($filter === 'starred') {
            $emailsQuery->where('is_starred', true);
        } elseif ($filter === 'attachments') {
            $emailsQuery->where('has_attachments', true);
        }

        $emails = $emailsQuery->orderBy('sent_at', 'desc')->paginate(25)->withQueryString();

        // Selected email detail: Hanya dibuka dan ditandai dibaca jika user mengeklik pesan
        $selectedEmailId = $request->query('email');
        $selectedEmail = null;

        if ($selectedEmailId) {
            $selectedEmail = Email::with('attachments')->find($selectedEmailId);
            if ($selectedEmail && !$selectedEmail->is_read) {
                $selectedEmail->update(['is_read' => true]);
                if ($currentFolder && $currentFolder->unread_count > 0) {
                    $currentFolder->unread_count--;
                }
            }
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'html' => view('webmail.partials.email-list', compact('emails', 'selectedEmail', 'currentFolder', 'query', 'filter'))->render(),
                'paginationHtml' => $emails->hasPages() ? view('webmail.partials.pagination', compact('emails'))->render() : '',
                'total' => $emails->total(),
                'hasPages' => $emails->hasPages(),
                'query' => $query,
            ]);
        }

        // GAL contacts for composer auto-complete
        $galContacts = Contact::where('domain_id', $currentAccount->domain_id)
            ->orWhere('is_gal', true)
            ->get(['name', 'email', 'job_title']);

        return view('webmail.index', compact(
            'currentAccount',
            'folders',
            'currentFolder',
            'emails',
            'selectedEmail',
            'query',
            'filter',
            'galContacts'
        ));
    }

    /**
     * Check for new incoming emails and real-time mailbox updates.
     */
    public function checkUpdates(Request $request)
    {
        $currentAccount = $this->getActiveAccount();

        // 1. Gather all folder unread counts
        $folders = $currentAccount->folders()->withCount(['emails as unread_count' => function ($q) {
            $q->where('is_read', false);
        }])->get();

        $folderUnreads = [];
        $inboxUnread = 0;
        foreach ($folders as $f) {
            $folderUnreads[$f->slug] = (int) $f->unread_count;
            if ($f->slug === 'inbox') {
                $inboxUnread = (int) $f->unread_count;
            }
        }

        $folderSlug = $request->query('folder', 'inbox');
        $currentFolder = $folders->firstWhere('slug', $folderSlug) ?? $folders->first();

        // 2. Query for current folder/filter
        $emailsQuery = $currentFolder ? $currentFolder->emails() : Email::where('mail_account_id', $currentAccount->id);

        $query = $request->query('q');
        if ($query) {
            $emailsQuery->where(function ($q) use ($query) {
                $q->where('subject', 'like', "%{$query}%")
                  ->orWhere('from_name', 'like', "%{$query}%")
                  ->orWhere('from_email', 'like', "%{$query}%")
                  ->orWhere('snippet', 'like', "%{$query}%")
                  ->orWhere('body_text', 'like', "%{$query}%");
            });
        }

        $filter = $request->query('filter', 'all');
        if ($filter === 'unread') {
            $emailsQuery->where('is_read', false);
        } elseif ($filter === 'starred') {
            $emailsQuery->where('is_starred', true);
        } elseif ($filter === 'attachments') {
            $emailsQuery->where('has_attachments', true);
        }

        $total = (clone $emailsQuery)->count();
        $latestEmail = (clone $emailsQuery)->orderBy('id', 'desc')->first();
        $latestId = $latestEmail ? $latestEmail->id : 0;

        $lastEmailId = $request->query('last_email_id');
        $knownTotal = $request->query('known_total');

        // Check if there are newly arrived emails since the last known ID
        $newCount = 0;
        $newestEmail = null;
        if ($lastEmailId && (int)$latestId > (int)$lastEmailId) {
            $newCount = (clone $emailsQuery)->where('id', '>', (int)$lastEmailId)->count();
            if ($newCount > 0) {
                $newestEmail = (clone $emailsQuery)->where('id', '>', (int)$lastEmailId)->orderBy('id', 'desc')->first();
            }
        }

        $hasChanged = true;
        if ($lastEmailId !== null && $knownTotal !== null) {
            if ((int)$lastEmailId === (int)$latestId && (int)$knownTotal === (int)$total) {
                $hasChanged = false;
            }
        }

        // If nothing changed and client did not force a full refresh, return compact lightweight response
        if (!$hasChanged && !$request->boolean('force')) {
            return response()->json([
                'success' => true,
                'changed' => false,
                'has_new' => false,
                'new_count' => 0,
                'latest_id' => $latestId,
                'total' => $total,
                'folder_unreads' => $folderUnreads,
                'inbox_unread' => $inboxUnread,
            ]);
        }

        // Render updated view partials
        $emails = $emailsQuery->orderBy('sent_at', 'desc')->paginate(25);
        $selectedEmailId = $request->query('selected_email_id');
        $selectedEmail = $selectedEmailId ? Email::find($selectedEmailId) : null;

        $html = view('webmail.partials.email-list', [
            'emails' => $emails,
            'selectedEmail' => $selectedEmail,
            'currentFolder' => $currentFolder,
            'query' => $query,
            'filter' => $filter,
        ])->render();

        $paginationHtml = $emails->hasPages() ? view('webmail.partials.pagination', compact('emails'))->render() : '';

        return response()->json([
            'success' => true,
            'changed' => true,
            'has_new' => $newCount > 0,
            'new_count' => $newCount,
            'newest_email' => $newestEmail ? [
                'id' => $newestEmail->id,
                'from' => $newestEmail->from_name ?: $newestEmail->from_email,
                'subject' => $newestEmail->subject ?: '(Tanpa Subjek)',
                'snippet' => $newestEmail->snippet ?: '',
            ] : null,
            'latest_id' => $latestId,
            'total' => $total,
            'folder_unreads' => $folderUnreads,
            'inbox_unread' => $inboxUnread,
            'html' => $html,
            'paginationHtml' => $paginationHtml,
        ]);
    }

    /**
     * Send email via Brevo / SMTP Relay.
     */
    public function send(Request $request)
    {
        $request->validate([
            'to' => 'required|string',
            'cc' => 'nullable|string',
            'bcc' => 'nullable|string',
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string',
        ]);

        $sender = $this->getActiveAccount();
        $recipients = $this->relayService->parseRecipients($request->input('to'));

        if (empty($recipients)) {
            return redirect()->back()
                ->withInput()
                ->with('warning', 'Format alamat email penerima tidak valid. Masukkan setidaknya 1 alamat email yang benar.');
        }

        $ccRecipients = $this->relayService->parseRecipients($request->input('cc'));
        $bccRecipients = $this->relayService->parseRecipients($request->input('bcc'));

        // Prepare attachments if uploaded
        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('mail_attachments', 'local');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'path' => storage_path('app/' . $path),
                ];
            }
        }

        // Include existing attachments forwarded from an email
        if ($request->has('forward_attachment_ids') && is_array($request->input('forward_attachment_ids'))) {
            $existingAtts = EmailAttachment::whereIn('id', $request->input('forward_attachment_ids'))->get();
            foreach ($existingAtts as $ea) {
                $diskPath = storage_path('app/' . $ea->storage_path);
                if (file_exists($diskPath)) {
                    $attachments[] = [
                        'name' => $ea->filename,
                        'mime' => $ea->content_type,
                        'size' => $ea->size_bytes,
                        'path' => $diskPath,
                    ];
                }
            }
        }

        $result = $this->relayService->sendEmail(
            $sender,
            $recipients,
            $request->input('subject'),
            $request->input('body_html'),
            null,
            $attachments,
            null,
            $ccRecipients,
            $bccRecipients
        );

        if ($result['success']) {
            return redirect()->route('webmail.index', ['folder' => 'sent'])
                ->with('success', 'Pesan terkirim');
        }

        return redirect()->back()->with('warning', $result['message']);
    }

    /**
     * Toggle starred flag.
     */
    public function toggleStar($id)
    {
        $email = Email::findOrFail($id);
        $email->update(['is_starred' => !$email->is_starred]);

        return response()->json(['success' => true, 'is_starred' => $email->is_starred]);
    }

    /**
     * Toggle read/unread flag via AJAX.
     */
    public function toggleRead($id)
    {
        $email = Email::findOrFail($id);
        $email->update(['is_read' => !$email->is_read]);

        return response()->json(['success' => true, 'is_read' => $email->is_read]);
    }

    /**
     * Handle quick inline reply from reading pane.
     */
    public function quickReply(Request $request)
    {
        $request->validate([
            'email_id' => 'required|exists:emails,id',
            'body_html' => 'required|string',
            'is_reply_all' => 'nullable',
            'attachments.*' => 'nullable|file|max:25600',
        ]);

        $originalEmail = Email::findOrFail($request->input('email_id'));
        $sender = $this->getActiveAccount();

        $isReplyAll = filter_var($request->input('is_reply_all', false), FILTER_VALIDATE_BOOLEAN);

        $subject = str_starts_with(strtolower($originalEmail->subject ?? ''), 're:')
            ? $originalEmail->subject
            : 'Re: ' . ($originalEmail->subject ?: 'Tanpa Subjek');

        if ($isReplyAll) {
            $replyRecipients = $originalEmail->getReplyAllRecipients($sender->email);
            $to = $replyRecipients['to'];
            $cc = $replyRecipients['cc'];
            if (empty($to)) {
                $to = [$originalEmail->from_email];
            }
        } else {
            $singleRecipients = $originalEmail->getReplySingleRecipients($sender->email);
            $to = $singleRecipients['to'];
            $cc = [];
            if (empty($to)) {
                $to = [$originalEmail->from_email];
            }
        }

        // Prepare attachments if uploaded
        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                if ($file->isValid()) {
                    $path = $file->store('mail_attachments/outbound/' . date('Y/m/d'), 'local');
                    $attachments[] = [
                        'name' => $file->getClientOriginalName(),
                        'mime' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                        'path' => storage_path('app/' . $path),
                    ];
                }
            }
        }

        $result = $this->relayService->sendEmail(
            $sender,
            $to,
            $subject,
            $request->input('body_html'),
            null,
            $attachments,
            null,
            $cc
        );

        if ($result['success']) {
            return redirect()->back()->with('success', 'Pesan terkirim');
        }

        return redirect()->back()->with('warning', $result['message']);
    }

    /**
     * Batch actions on multiple selected emails (mark read, delete, star).
     */
    public function batchAction(Request $request)
    {
        $request->validate([
            'action' => 'required|string|in:mark_read,mark_unread,delete,star',
            'email_ids' => 'required|array',
            'email_ids.*' => 'exists:emails,id',
        ]);

        $ids = $request->input('email_ids');
        $action = $request->input('action');
        $sender = $this->getActiveAccount();

        switch ($action) {
            case 'mark_read':
                Email::whereIn('id', $ids)->where('mail_account_id', $sender->id)->update(['is_read' => true]);
                $msg = count($ids) . ' pesan ditandai sudah dibaca.';
                break;
            case 'mark_unread':
                Email::whereIn('id', $ids)->where('mail_account_id', $sender->id)->update(['is_read' => false]);
                $msg = count($ids) . ' pesan ditandai belum dibaca.';
                break;
            case 'star':
                Email::whereIn('id', $ids)->where('mail_account_id', $sender->id)->update(['is_starred' => true]);
                $msg = count($ids) . ' pesan ditandai bintang.';
                break;
            case 'delete':
                $trashFolder = MailFolder::where('mail_account_id', $sender->id)->where('slug', 'trash')->first();
                if ($trashFolder) {
                    Email::whereIn('id', $ids)->where('mail_account_id', $sender->id)->update(['mail_folder_id' => $trashFolder->id]);
                    $msg = count($ids) . ' pesan dipindahkan ke Sampah.';
                } else {
                    Email::whereIn('id', $ids)->where('mail_account_id', $sender->id)->delete();
                    $msg = count($ids) . ' pesan dihapus.';
                }
                break;
            default:
                $msg = 'Aksi tidak dikenal.';
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Delete email (move to trash or permanently delete).
     */
    public function destroy(Request $request, $id)
    {
        $email = Email::findOrFail($id);
        $trashFolder = MailFolder::where('mail_account_id', $email->mail_account_id)
            ->where('slug', 'trash')
            ->first();

        $page = $request->input('page') ?: $request->query('page');
        $folder = $request->input('folder') ?: $request->query('folder', 'inbox');
        $redirectParams = array_filter(['folder' => $folder, 'page' => $page]);

        if ($email->mail_folder_id === optional($trashFolder)->id) {
            $email->delete();
            $redirectParams['folder'] = 'trash';
            return redirect()->route('webmail.index', $redirectParams)
                ->with('success', 'Email berhasil dihapus permanen.');
        }

        if ($trashFolder) {
            $email->update(['mail_folder_id' => $trashFolder->id]);
            return redirect()->route('webmail.index', $redirectParams)
                ->with('success', 'Email dipindahkan ke kotak Sampah.');
        }

        $email->delete();
        return redirect()->route('webmail.index', $redirectParams)->with('success', 'Email dihapus.');
    }

    /**
     * Download attachment file securely.
     */
    public function downloadAttachment($id)
    {
        $attachment = EmailAttachment::with('email')->findOrFail($id);
        $user = $this->getActiveAccount();

        // Ensure user has access to this attachment's email
        if (!$user->is_admin && $attachment->email && $attachment->email->mail_account_id !== $user->id) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengunduh lampiran ini.');
        }

        $realPath = $attachment->real_path;
        if ($realPath && file_exists($realPath)) {
            $downloadFilename = $attachment->download_filename;
            $mime = $attachment->content_type ?: (@mime_content_type($realPath) ?: 'application/octet-stream');
            $headers = [
                'Content-Type' => $mime,
            ];
            return response()->download($realPath, $downloadFilename, $headers);
        }

        return redirect()->back()->with('warning', 'Berkas lampiran tidak ditemukan di penyimpanan server.');
    }

    /**
     * Stream attachment file inline (e.g. for body images, PDF preview).
     */
    public function inlineAttachment($id)
    {
        $attachment = EmailAttachment::with('email')->findOrFail($id);
        $user = $this->getActiveAccount();

        // Ensure user has access to this attachment's email
        if (!$user->is_admin && $attachment->email && $attachment->email->mail_account_id !== $user->id) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat lampiran ini.');
        }

        $realPath = $attachment->real_path;
        if ($realPath && file_exists($realPath)) {
            $downloadFilename = $attachment->download_filename;
            $mime = $attachment->content_type ?: (@mime_content_type($realPath) ?: 'application/octet-stream');
            return response()->file($realPath, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="' . addslashes($downloadFilename) . '"',
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        abort(404, 'Berkas lampiran tidak ditemukan.');
    }

    /**
     * Show Email Forwarding settings page.
     */
    public function showForwarding()
    {
        $user = $this->getActiveAccount();
        return view('webmail.forwarding-settings', compact('user'));
    }

    /**
     * Update Email Forwarding settings.
     */
    public function updateForwarding(Request $request)
    {
        $user = $this->getActiveAccount();

        $request->validate([
            'forward_active' => 'nullable',
            'forward_destination' => 'nullable|string|max:500',
            'forward_keep_copy' => 'nullable',
        ]);

        $isActive = $request->boolean('forward_active');
        $destination = trim($request->input('forward_destination') ?? '');
        $keepCopy = $request->boolean('forward_keep_copy', true);

        if ($isActive) {
            if (empty($destination)) {
                return redirect()->back()
                    ->withInput()
                    ->with('warning', 'Silakan masukkan setidaknya satu alamat email tujuan penerusan.');
            }

            $parsed = $this->relayService->parseRecipients($destination);
            if (empty($parsed)) {
                return redirect()->back()
                    ->withInput()
                    ->with('warning', 'Format alamat email tujuan penerusan tidak valid.');
            }

            // Loop prevention: ensure destination does not contain the user's own email
            foreach ($parsed as $item) {
                if (strtolower($item['email']) === strtolower($user->email)) {
                    return redirect()->back()
                        ->withInput()
                        ->with('warning', 'Alamat email penerusan tidak boleh sama dengan alamat kotak surat Anda sendiri untuk mencegah loop perputaran email.');
                }
            }
        }

        $user->forward_active = $isActive;
        $user->forward_destination = $destination ?: null;
        $user->forward_keep_copy = $keepCopy;
        $user->save();

        return redirect()->route('webmail.forwarding')
            ->with('success', 'Pengaturan penerusan email (email forwarding) berhasil diperbarui.');
    }
}
