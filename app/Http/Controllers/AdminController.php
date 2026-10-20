<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Email;
use App\Models\MailAccount;
use App\Models\MailAlias;
use App\Models\SmtpRelay;
use App\Services\MailRelayService;
use App\Services\PasswordPolicyService;
use App\Services\CaptchaService;
use App\Models\SystemSetting;
use App\Services\MailboxMigrationService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    protected MailRelayService $relayService;
    protected PasswordPolicyService $policyService;
    protected CaptchaService $captchaService;
    protected MailboxMigrationService $migrationService;

    public function __construct(
        MailRelayService $relayService,
        PasswordPolicyService $policyService,
        CaptchaService $captchaService,
        MailboxMigrationService $migrationService
    ) {
        $this->relayService = $relayService;
        $this->policyService = $policyService;
        $this->captchaService = $captchaService;
        $this->migrationService = $migrationService;
    }

    /**
     * Admin Dashboard: System Overview & Resource Health.
     */
    public function dashboard()
    {
        $totalAccounts = MailAccount::count();
        $activeAccounts = MailAccount::where('is_active', true)->count();
        $totalDomains = Domain::count();
        
        $totalAllocatedQuota = MailAccount::sum('quota_bytes');
        $totalUsedStorage = MailAccount::sum('used_bytes');

        $activeRelay = SmtpRelay::where('is_default', true)->first() ?? SmtpRelay::first();

        // Email traffic & queue statistics
        $emailsSentToday = Email::whereDate('sent_at', Carbon::today())->count();
        $totalEmails = Email::count();
        $queueStats = [
            'total' => $totalEmails,
            'delivered' => Email::where('delivery_status', 'delivered')->count(),
            'deferred' => Email::where('delivery_status', 'deferred')->count(),
            'held' => Email::where('delivery_status', 'held')->count(),
            'bounced' => Email::whereIn('delivery_status', ['bounced', 'failed'])->count(),
        ];

        // Recent 5 emails sent via system
        $recentEmails = Email::with('account')->orderBy('sent_at', 'desc')->take(6)->get();

        // Recent accounts
        $recentAccounts = MailAccount::with('domain')->orderBy('created_at', 'desc')->take(5)->get();

        // Password Security Policy Rules
        $passwordPolicy = $this->policyService->getRules();
        $captchaEnabled = $this->captchaService->isEnabled();

        return view('admin.dashboard', compact(
            'totalAccounts',
            'activeAccounts',
            'totalDomains',
            'totalAllocatedQuota',
            'totalUsedStorage',
            'activeRelay',
            'emailsSentToday',
            'totalEmails',
            'queueStats',
            'recentEmails',
            'recentAccounts',
            'passwordPolicy',
            'captchaEnabled'
        ));
    }

    /**
     * Update Password Security Policy & Login Protection settings from Admin Dashboard.
     */
    public function updatePasswordPolicy(Request $request)
    {
        $request->validate([
            'min_length' => 'required|integer|min:6|max:64',
            'history_limit' => 'nullable|integer|min:1|max:20',
        ], [
            'min_length.required' => 'Panjang minimal kata sandi wajib ditentukan.',
            'min_length.min' => 'Panjang minimal kata sandi tidak boleh kurang dari 6 karakter.',
        ]);

        $this->policyService->updateRules([
            'min_length' => (int) $request->input('min_length', 8),
            'require_uppercase' => $request->has('require_uppercase'),
            'require_symbol' => $request->has('require_symbol'),
            'prevent_reuse' => $request->has('prevent_reuse'),
            'history_limit' => (int) $request->input('history_limit', 5),
        ]);

        // Toggle Login CAPTCHA setting
        if ($request->has('captcha_setting_submitted')) {
            SystemSetting::set(
                'login_captcha_enabled',
                $request->has('login_captcha_enabled') ? '1' : '0',
                'Aktifkan verifikasi CAPTCHA pada halaman login Webmail dan Admin Console'
            );
        }

        return redirect()->back()->with('success', 'Aturan kebijakan keamanan dan proteksi login berhasil diperbarui.');
    }

    /**
     * View and configure SMTP Relays (Brevo, SES, etc).
     */
    public function relays()
    {
        $relays = SmtpRelay::with('domain')->get();
        $domains = Domain::where('is_active', true)->get();
        $activeRelay = SmtpRelay::where('is_default', true)->first() ?? $relays->first();

        return view('admin.relays', compact('relays', 'domains', 'activeRelay'));
    }

    /**
     * Update SMTP Relay configuration.
     */
    public function updateRelay(Request $request, $id)
    {
        $relay = SmtpRelay::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'provider' => 'required|string',
            'host' => 'required|string',
            'port' => 'required|integer',
            'encryption' => 'nullable|string',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
            'sender_email' => 'nullable|email',
            'sender_name' => 'nullable|string',
            'domain_id' => 'nullable|exists:domains,id',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $validated['is_default'] = $request->has('is_default');
        $validated['domain_id'] = $request->input('domain_id') ?: null;

        if ($validated['is_default']) {
            SmtpRelay::where('id', '!=', $relay->id)->update(['is_default' => false]);
        }

        // Only overwrite password if entered
        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $relay->update($validated);

        return redirect()->route('admin.relays')
            ->with('success', "Konfigurasi relay {$relay->name} berhasil disimpan.");
    }

    /**
     * Test connection to SMTP Relay with live diagnostic.
     */
    public function testRelay(Request $request, $id)
    {
        $request->validate([
            'test_email' => 'required|email',
        ]);

        $relay = SmtpRelay::findOrFail($id);
        $result = $this->relayService->testRelayConnection($relay, $request->input('test_email'));

        if ($result['success']) {
            return redirect()->route('admin.relays')->with('success', $result['message']);
        }

        return redirect()->route('admin.relays')->with('error', $result['message']);
    }

    /**
     * Test Direct MX DNS lookup & port 25 connection diagnostic.
     */
    public function testDirectMx(Request $request)
    {
        $request->validate([
            'test_email' => 'required|email',
        ]);

        $result = $this->relayService->testDirectMxConnection($request->input('test_email'));

        if ($result['success']) {
            return redirect()->route('admin.relays')->with('success', $result['message']);
        }

        return redirect()->route('admin.relays')->with('error', $result['message']);
    }

    /**
     * Manage domains and view DNS records (SPF, DKIM, DMARC, MX).
     */
    public function domains(Request $request)
    {
        $domains = Domain::withCount('accounts')->get();
        $domainId = $request->query('domain_id');
        $selectedDomain = $domainId ? Domain::find($domainId) : $domains->first();

        if ($selectedDomain && empty($selectedDomain->dkim_private_key)) {
            $selectedDomain->generateDkimKeys();
        }

        $dnsCheckResult = session('dns_check_result');
        $checkedDomainId = session('checked_domain_id');

        return view('admin.domains', compact('domains', 'selectedDomain', 'dnsCheckResult', 'checkedDomainId'));
    }

    /**
     * Store new domain with auto-generated 2048-bit RSA DKIM and DNS template records.
     */
    public function storeDomain(Request $request)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'unique:domains,name',
                'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/i',
            ],
            'description' => 'nullable|string|max:255',
            'dkim_selector' => 'nullable|string|alpha_dash|max:32',
        ], [
            'name.required' => 'Nama domain wajib diisi.',
            'name.unique' => 'Domain ini sudah terdaftar dalam sistem.',
            'name.regex' => 'Format nama domain tidak valid (contoh: perusahaan.com).',
        ]);

        $domainName = strtolower(trim($request->input('name')));
        $selector = strtolower(trim($request->input('dkim_selector'))) ?: 'apexmail';

        $domain = Domain::create([
            'name' => $domainName,
            'description' => $request->input('description'),
            'is_active' => true,
            'is_verified' => true,
            'dkim_selector' => $selector,
            'spf_record' => 'v=spf1 ip4:103.156.88.95 include:_spf.mx.cloudflare.net ~all',
            'dmarc_record' => 'v=DMARC1; p=none; rua=mailto:dmarc@' . $domainName,
            'mx_record' => 'route1.mx.cloudflare.net',
        ]);

        $domain->generateDkimKeys();

        return redirect()->route('admin.domains', ['domain_id' => $domain->id, 'created' => 1])
            ->with('success', "Domain '{$domain->name}' berhasil didaftarkan! Kunci DKIM 2048-bit RSA, SPF, DMARC, dan MX otomatis digenerate. Anda tinggal menyalin rekam DNS ke Cloudflare.");
    }

    /**
     * Check live DNS propagation status for a domain.
     */
    public function checkDomainDns(Request $request, $id)
    {
        $domain = Domain::findOrFail($id);
        $status = $domain->checkLiveDnsStatus();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'domain' => $domain->name,
                'data' => $status,
            ]);
        }

        return redirect()->route('admin.domains', ['domain_id' => $domain->id])
            ->with('dns_check_result', $status)
            ->with('checked_domain_id', $domain->id)
            ->with('success', "Pemeriksaan status DNS live untuk domain '{$domain->name}' selesai dijalankan.");
    }

    /**
     * Regenerate DKIM key pair for a domain.
     */
    public function regenerateDkim(Request $request, $id)
    {
        $domain = Domain::findOrFail($id);
        $domain->generateDkimKeys();

        return redirect()->route('admin.domains', ['domain_id' => $domain->id])
            ->with('success', "Pasangan kunci kriptografi DKIM 2048-bit untuk domain {$domain->name} berhasil diperbarui. Pastikan mengupdate record TXT di DNS manager.");
    }

    /**
     * Export all DNS records for a domain as a downloadable plain text file.
     */
    public function exportDomainDns($id)
    {
        $domain = Domain::findOrFail($id);
        $content = $domain->generateZoneFileText();
        $filename = 'dns-records-' . $domain->name . '-' . date('Ymd-His') . '.txt';

        return response($content, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Delete a domain.
     */
    public function destroyDomain(Request $request, $id)
    {
        $domain = Domain::findOrFail($id);
        $accountsCount = $domain->accounts()->count();

        if ($accountsCount > 0 && !$request->has('force')) {
            return redirect()->back()
                ->with('error', "Domain '{$domain->name}' tidak dapat dihapus karena masih digunakan oleh {$accountsCount} akun kotak surat. Hapus akun terkait terlebih dahulu.");
        }

        $domainName = $domain->name;
        $domain->delete();

        return redirect()->route('admin.domains')
            ->with('success', "Domain '{$domainName}' berhasil dihapus dari sistem.");
    }

    /**
     * Manage mailbox accounts.
     */
    public function accounts(Request $request)
    {
        $query = $request->query('q');
        $domainId = $request->query('domain_id');

        $accountsQuery = MailAccount::with('domain');

        if ($query) {
            $accountsQuery->where(function ($q) use ($query) {
                $q->where('username', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%")
                  ->orWhere('display_name', 'like', "%{$query}%");
            });
        }

        if ($domainId) {
            $accountsQuery->where('domain_id', $domainId);
        }

        $accounts = $accountsQuery->paginate(15);
        $domains = Domain::where('is_active', true)->get();
        $passwordPolicy = $this->policyService->getRules();

        return view('admin.accounts', compact('accounts', 'domains', 'query', 'domainId', 'passwordPolicy'));
    }

    /**
     * Create new mailbox account.
     */
    public function storeAccount(Request $request)
    {
        $request->validate([
            'domain_id' => 'required|exists:domains,id',
            'username' => 'required|string|alpha_dash',
            'recovery_email' => 'required|email|max:255',
            'display_name' => 'required|string',
            'password' => $this->policyService->getLaravelValidationRules(null, true),
            'quota_gb' => 'required|numeric|min:1',
        ], [
            'recovery_email.required' => 'Alamat email pemulihan wajib diisi untuk keamanan dan pengiriman OTP reset sandi.',
            'recovery_email.email' => 'Format alamat email pemulihan tidak valid.',
            'password.required' => 'Kata sandi awal wajib diisi.',
            'password.min' => 'Kata sandi awal minimal harus :min karakter.',
            'password.regex' => 'Kata sandi awal wajib mengandung huruf besar (A-Z) dan simbol unik.',
        ]);

        $domain = Domain::findOrFail($request->input('domain_id'));
        $email = strtolower($request->input('username')) . '@' . $domain->name;

        if (MailAccount::where('email', $email)->exists()) {
            return redirect()->back()->with('error', "Alamat email {$email} sudah terdaftar.");
        }

        // Validate password against policy
        $policyCheck = $this->policyService->validatePassword($request->input('password'));
        if (!$policyCheck['valid']) {
            return redirect()->back()
                ->withInput($request->except('password'))
                ->with('error', implode(' ', $policyCheck['errors']));
        }

        $account = MailAccount::create([
            'domain_id' => $domain->id,
            'username' => strtolower($request->input('username')),
            'email' => $email,
            'recovery_email' => strtolower(trim($request->input('recovery_email'))),
            'display_name' => $request->input('display_name'),
            'password' => Hash::make($request->input('password')),
            'quota_bytes' => (int) ($request->input('quota_gb') * 1073741824),
            'is_admin' => $request->has('is_admin'),
            'is_active' => true,
        ]);

        $account->createDefaultFolders();

        // Record initial password into history
        $this->policyService->recordPasswordHistory($account, $account->password);

        return redirect()->route('admin.accounts')->with('success', "Kotak surat {$email} berhasil dibuat dengan email pemulihan {$account->recovery_email}.");
    }

    /**
     * Update mailbox account details (Display name, recovery email, quota, password).
     */
    public function updateAccount(Request $request, $id)
    {
        $account = MailAccount::findOrFail($id);

        $request->validate([
            'display_name' => 'required|string|max:255',
            'recovery_email' => 'required|email|max:255',
            'quota_gb' => 'required|numeric|min:1',
            'password' => $this->policyService->getLaravelValidationRules($account, false),
        ], [
            'recovery_email.required' => 'Alamat email pemulihan wajib diisi.',
            'recovery_email.email' => 'Format email pemulihan tidak valid.',
            'password.min' => 'Kata sandi baru minimal harus :min karakter.',
            'password.regex' => 'Kata sandi baru wajib mengandung huruf besar (A-Z) dan simbol unik.',
        ]);

        $data = [
            'display_name' => $request->input('display_name'),
            'recovery_email' => strtolower(trim($request->input('recovery_email'))),
            'quota_bytes' => (int) ($request->input('quota_gb') * 1073741824),
        ];

        if ($request->filled('password')) {
            $newPassword = $request->input('password');

            // Validate against password policy & previous password
            $policyCheck = $this->policyService->validatePassword($newPassword, $account);
            if (!$policyCheck['valid']) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', implode(' ', $policyCheck['errors']));
            }

            // Record old password in history before changing
            if (!empty($account->password)) {
                $this->policyService->recordPasswordHistory($account, $account->password);
            }

            $newHash = Hash::make($newPassword);
            $data['password'] = $newHash;
            $this->policyService->recordPasswordHistory($account, $newHash);
        }

        if ($request->has('reset_two_factor')) {
            $data['two_factor_enabled'] = false;
            $data['two_factor_secret'] = null;
            $data['two_factor_confirmed_at'] = null;
        }

        $account->update($data);

        return redirect()->back()->with('success', "Informasi akun {$account->email} berhasil diperbarui.");
    }

    /**
     * Toggle suspend / activate mailbox account.
     */
    public function toggleAccountStatus($id)
    {
        $account = MailAccount::findOrFail($id);
        $account->update(['is_active' => !$account->is_active]);

        $statusText = $account->is_active ? 'diaktifkan kembali' : 'ditangguhkan (suspended)';
        return redirect()->back()->with('success', "Akun {$account->email} berhasil {$statusText}.");
    }

    /**
     * Reset user password by Admin.
     */
    public function resetPassword(Request $request, $id)
    {
        $account = MailAccount::findOrFail($id);

        $request->validate([
            'password' => $this->policyService->getLaravelValidationRules($account, true),
        ], [
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi baru minimal harus :min karakter.',
            'password.regex' => 'Kata sandi baru wajib mengandung huruf besar (A-Z) dan simbol unik.',
        ]);

        $newPassword = $request->input('password');

        $policyCheck = $this->policyService->validatePassword($newPassword, $account);
        if (!$policyCheck['valid']) {
            return redirect()->back()
                ->withInput()
                ->with('error', implode(' ', $policyCheck['errors']));
        }

        if (!empty($account->password)) {
            $this->policyService->recordPasswordHistory($account, $account->password);
        }

        $newHash = Hash::make($newPassword);
        $account->update(['password' => $newHash]);
        $this->policyService->recordPasswordHistory($account, $newHash);

        return redirect()->back()->with('success', "Kata sandi untuk {$account->email} berhasil diperbarui.");
    }

    /**
     * Delete mailbox account.
     */
    public function destroyAccount($id)
    {
        $account = MailAccount::findOrFail($id);

        // Prevent deleting own logged in admin account
        if (auth()->id() === $account->id) {
            return redirect()->back()->with('error', 'Anda tidak dapat menghapus akun administrator yang sedang Anda gunakan.');
        }

        $email = $account->email;
        $account->delete();

        return redirect()->route('admin.accounts')->with('success', "Akun {$email} berhasil dihapus permanen.");
    }

    /**
     * Manage Aliases & Distribution Lists.
     */
    public function aliases()
    {
        $aliases = MailAlias::with('domain')->paginate(20);
        $domains = Domain::where('is_active', true)->get();

        return view('admin.aliases', compact('aliases', 'domains'));
    }

    /**
     * Store new Mail Alias / Distribution List.
     */
    public function storeAlias(Request $request)
    {
        $request->validate([
            'domain_id' => 'required|exists:domains,id',
            'source_username' => 'required|string|alpha_dash',
            'destination_emails' => 'required|string',
        ]);

        $domain = Domain::findOrFail($request->input('domain_id'));
        $sourceEmail = strtolower($request->input('source_username')) . '@' . $domain->name;

        MailAlias::create([
            'domain_id' => $domain->id,
            'source_email' => $sourceEmail,
            'destination_emails' => $request->input('destination_emails'),
            'is_active' => true,
        ]);

        return redirect()->route('admin.aliases')->with('success', "Grup alias / forwarder {$sourceEmail} berhasil dibuat.");
    }

    /**
     * Delete alias.
     */
    public function destroyAlias($id)
    {
        $alias = MailAlias::findOrFail($id);
        $source = $alias->source_email;
        $alias->delete();

        return redirect()->route('admin.aliases')->with('success', "Alias {$source} berhasil dihapus.");
    }

    /**
     * Mail Queue Inspector & Outgoing Delivery Logs.
     */
    public function queue(Request $request)
    {
        $statusFilter = $request->query('status', 'all');
        $query = Email::with('account.domain')->orderBy('sent_at', 'desc');

        if ($statusFilter === 'delivered') {
            $query->where('delivery_status', 'delivered');
        } elseif ($statusFilter === 'deferred') {
            $query->where('delivery_status', 'deferred');
        } elseif ($statusFilter === 'held') {
            $query->where('delivery_status', 'held');
        } elseif ($statusFilter === 'bounced') {
            $query->whereIn('delivery_status', ['bounced', 'failed']);
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('from_email', 'like', "%{$search}%")
                  ->orWhere('to', 'like', "%{$search}%");
            });
        }

        $recentEmails = $query->paginate(20)->appends($request->query());
        $activeRelay = SmtpRelay::where('is_default', true)->first() ?? SmtpRelay::first();

        $stats = [
            'total' => Email::count(),
            'delivered' => Email::where('delivery_status', 'delivered')->count(),
            'deferred' => Email::where('delivery_status', 'deferred')->count(),
            'held' => Email::where('delivery_status', 'held')->count(),
            'bounced' => Email::whereIn('delivery_status', ['bounced', 'failed'])->count(),
        ];

        return view('admin.queue', compact('recentEmails', 'activeRelay', 'stats', 'statusFilter'));
    }

    /**
     * Flush and retry all deferred emails in the queue.
     */
    public function flushQueue(Request $request)
    {
        $deferredEmails = Email::where('delivery_status', 'deferred')->get();
        if ($deferredEmails->isEmpty()) {
            return redirect()->back()->with('warning', 'Tidak ada antrean surat yang tertahan saat ini.');
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($deferredEmails as $email) {
            $result = $this->relayService->retryEmail($email);
            if ($result['success']) {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        if ($failCount === 0) {
            return redirect()->back()->with('success', "Proses flush antrean selesai: {$successCount} email tertahan berhasil dikirim.");
        }

        return redirect()->back()->with('warning', "Proses antrean: {$successCount} berhasil terkirim, {$failCount} masih tertahan.");
    }

    /**
     * Retry sending a single queued/deferred email.
     */
    public function retryQueueItem(Request $request, $id)
    {
        $email = Email::findOrFail($id);
        $result = $this->relayService->retryEmail($email);

        if ($result['success']) {
            return redirect()->back()->with('success', "Pesan #{$id} berhasil dikirimkan.");
        }

        return redirect()->back()->with('error', $result['message']);
    }

    /**
     * Toggle hold state on a queue item.
     */
    public function toggleHoldQueueItem(Request $request, $id)
    {
        $email = Email::findOrFail($id);
        if ($email->delivery_status === 'held') {
            $email->update(['delivery_status' => 'deferred']);
            return redirect()->back()->with('success', "Pesan #{$email->id} dilepas dari tahanan (status: Tertahan / Siap Kirim).");
        } else {
            $email->update(['delivery_status' => 'held']);
            return redirect()->back()->with('success', "Pesan #{$email->id} berhasil ditahan (status: Ditahan Admin / On Hold).");
        }
    }

    /**
     * Delete an email from the queue.
     */
    public function deleteQueueItem(Request $request, $id)
    {
        $email = Email::findOrFail($id);
        $email->delete();

        return redirect()->back()->with('success', "Pesan #{$id} berhasil dihapus dari antrean.");
    }

    /**
     * Inbound Mail Management & Webhook Configuration (Brevo Inbound Parsing).
     */
    public function inbound()
    {
        $webhookToken = env('INBOUND_WEBHOOK_TOKEN', 'apex-inbound-secret');
        $webhookUrl = url('/api/mail/inbound') . ($webhookToken ? '?token=' . $webhookToken : '');

        $domains = Domain::where('is_active', true)->get();
        $accounts = MailAccount::where('is_active', true)->get();

        $recentInboundEmails = Email::with('account.domain')
            ->whereHas('folder', function ($q) {
                $q->where('slug', 'inbox');
            })
            ->whereNotNull('received_at')
            ->orderBy('received_at', 'desc')
            ->take(15)
            ->get();

        return view('admin.inbound', compact('webhookUrl', 'webhookToken', 'domains', 'accounts', 'recentInboundEmails'));
    }

    /**
     * Handle test inbound email from Admin UI.
     */
    public function simulateInbound(Request $request, \App\Services\InboundMailService $inboundService)
    {
        $validated = $request->validate([
            'from_email' => 'required|email',
            'from_name' => 'nullable|string|max:100',
            'to_email' => 'required|email',
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string',
            'body_text' => 'nullable|string',
        ]);

        $payload = [
            'items' => [
                [
                    'MessageId' => '<simulated-' . uniqid() . '@' . parse_url($validated['from_email'], PHP_URL_HOST) . '>',
                    'From' => [
                        'Name' => $validated['from_name'] ?: 'External Client',
                        'Address' => $validated['from_email'],
                    ],
                    'To' => [
                        [
                            'Name' => '',
                            'Address' => $validated['to_email'],
                        ]
                    ],
                    'Subject' => $validated['subject'],
                    'RawHtmlBody' => $validated['body_html'],
                    'RawTextBody' => $validated['body_text'] ?: strip_tags($validated['body_html']),
                    'SentAtDate' => now()->toIso8601String(),
                ]
            ]
        ];

        $simulatedRequest = Request::create('/api/mail/inbound', 'POST', $payload);

        if ($request->hasFile('attachments')) {
            $simulatedRequest->files->set('attachments', $request->file('attachments'));
        }

        $result = $inboundService->processInbound($simulatedRequest);

        if ($result['delivered_count'] > 0) {
            return redirect()->route('admin.inbound')->with('success', "Simulasi berhasil! Email masuk berhasil diteruskan ke kotak masuk {$validated['to_email']}.");
        }

        return redirect()->route('admin.inbound')->with('warning', "Simulasi selesai, namun alamat {$validated['to_email']} belum terdaftar sebagai kotak surat aktif.");
    }

    /**
     * Mailbox & User Migration Suite View.
     */
    public function migration()
    {
        $totalAccounts = MailAccount::count();
        $accounts = MailAccount::with('domain')->orderBy('email')->get();
        $domains = Domain::orderBy('name')->get();

        return view('admin.migration', compact('totalAccounts', 'accounts', 'domains'));
    }

    /**
     * Process mailbox archive (.zip, .tgz, .tar.gz, .eml) import.
     */
    public function importMailbox(Request $request)
    {
        $request->validate([
            'account_id' => 'required|exists:mail_accounts,id',
            'archive_file' => 'nullable|file|max:524288', // 512MB
            'server_path' => 'nullable|string',
            'default_folder' => 'nullable|string',
            'skip_duplicates' => 'nullable|boolean',
        ]);

        $account = MailAccount::findOrFail($request->account_id);
        $tempPath = null;
        $filePath = null;

        if ($request->hasFile('archive_file')) {
            $file = $request->file('archive_file');
            $extension = $file->getClientOriginalExtension() ?: 'zip';
            $tempFilename = 'upload_' . uniqid() . '.' . $extension;
            $destinationDir = storage_path('app/migration_temp');
            if (!is_dir($destinationDir)) {
                mkdir($destinationDir, 0755, true);
            }
            $file->move($destinationDir, $tempFilename);
            $filePath = $destinationDir . DIRECTORY_SEPARATOR . $tempFilename;
            $tempPath = $filePath;
        } elseif (!empty($request->server_path)) {
            $filePath = trim($request->server_path);
        } else {
            return back()->with('error', 'Silakan pilih berkas arsip yang akan diunggah atau masukkan path berkas di server.');
        }

        if (!file_exists($filePath)) {
            return back()->with('error', "Berkas arsip tidak ditemukan pada jalur: {$filePath}");
        }

        try {
            $result = $this->migrationService->importArchive($account, $filePath, [
                'default_folder' => $request->default_folder ?: 'inbox',
                'skip_duplicates' => $request->boolean('skip_duplicates', true),
                'default_is_read' => true,
            ]);

            $importedMb = round($result['total_bytes'] / (1024 * 1024), 2);
            $folderList = implode(', ', $result['folders'] ?? []);

            $message = "Migrasi mailbox ke {$account->email} sukses! " .
                       "Berhasil mengimpor {$result['imported_count']} pesan ({$importedMb} MB) ke folder [{$folderList}]. " .
                       "Dilewati (duplikat): {$result['skipped_count']}, Gagal: {$result['failed_count']}.";

            return back()->with('success', $message);
        } catch (Exception $e) {
            return back()->with('error', 'Gagal memproses migrasi: ' . $e->getMessage());
        } finally {
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Process bulk user accounts import from CSV.
     */
    public function importAccounts(Request $request)
    {
        $request->validate([
            'domain_id' => 'required|exists:domains,id',
            'csv_file' => 'nullable|file|mimes:csv,txt|max:10240',
            'csv_text' => 'nullable|string',
            'default_password' => 'nullable|string|min:6',
            'update_existing' => 'nullable|boolean',
        ]);

        $csvContent = '';
        if ($request->hasFile('csv_file')) {
            $csvContent = file_get_contents($request->file('csv_file')->getRealPath());
        } elseif (!empty($request->csv_text)) {
            $csvContent = $request->csv_text;
        } else {
            return back()->with('error', 'Silakan unggah berkas CSV atau tempel teks data CSV akun.');
        }

        try {
            $result = $this->migrationService->importAccountsFromCsv(
                $csvContent,
                $request->domain_id,
                [
                    'default_password' => $request->default_password ?: 'Password123!',
                    'update_existing' => $request->boolean('update_existing', false),
                ]
            );

            $msg = "Impor akun selesai! Dibuat: {$result['created_count']}, " .
                   "Diperbarui: {$result['updated_count']}, " .
                   "Dilewati: {$result['skipped_count']}, " .
                   "Gagal: {$result['failed_count']}.";

            if (!empty($result['errors'])) {
                return back()->with('warning', $msg . ' Catatan: ' . implode(' | ', array_slice($result['errors'], 0, 3)));
            }

            return back()->with('success', $msg);
        } catch (Exception $e) {
            return back()->with('error', 'Gagal memproses CSV akun: ' . $e->getMessage());
        }
    }

    /**
     * Test live remote IMAP connection (for live server-to-server migration).
     */
    public function testImap(Request $request)
    {
        $request->validate([
            'host' => 'required|string',
            'port' => 'required|integer',
            'encryption' => 'required|in:ssl,tls,none',
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $result = $this->migrationService->testImapConnection(
            $request->host,
            $request->port,
            $request->encryption,
            $request->username,
            $request->password
        );

        return response()->json($result);
    }
}


