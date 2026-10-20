<?php

namespace App\Services;

use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\MailAccount;
use App\Models\MailFolder;
use App\Models\SmtpRelay;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email as SymfonyEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Crypto\DkimSigner;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

class MailRelayService
{
    /**
     * Send email via configured SMTP Relay (e.g. Brevo) and record it in database.
     *
     * @param MailAccount $sender
     * @param array $to List of recipient emails
     * @param string $subject
     * @param string $bodyHtml
     * @param string|null $bodyText
     * @param array $attachments Array of uploaded files or paths
     * @param SmtpRelay|null $relay
     * @return array
     */
    /**
     * Parse and normalize recipient input into structured list of valid recipients.
     * Supports formats:
     * - "user1@domain.com, user2@domain.com"
     * - "user1@domain.com; user2@domain.com"
     * - "user1@domain.com user2@domain.com"
     * - "Display Name <user@domain.com>"
     * - "\"Quoted Name\" <user@domain.com>"
     * - Array of strings or structured arrays
     *
     * @param string|array|null $rawInput
     * @return array List of ['email' => string, 'name' => string]
     */
    public function parseRecipients($rawInput): array
    {
        if (empty($rawInput)) {
            return [];
        }

        if (is_array($rawInput)) {
            $isStructured = true;
            foreach ($rawInput as $item) {
                if (!is_array($item) || empty($item['email'])) {
                    $isStructured = false;
                    break;
                }
            }

            if ($isStructured) {
                return $rawInput;
            }

            $rawInput = implode(', ', array_map(function ($i) {
                if (is_array($i)) {
                    return (!empty($i['name']) ? "\"{$i['name']}\" " : '') . ($i['email'] ?? '');
                }
                return (string) $i;
            }, $rawInput));
        }

        $results = [];
        $seen = [];

        $pattern = '/(?:(?:"([^"]*)"|([^<,;\n\r]*?))\s*)?<([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})>|([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/i';

        if (preg_match_all($pattern, (string) $rawInput, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $email = '';
                $name = '';

                if (!empty($m[3])) {
                    $email = strtolower(trim($m[3]));
                    $name = trim(!empty($m[1]) ? $m[1] : ($m[2] ?? ''));
                } elseif (!empty($m[4])) {
                    $email = strtolower(trim($m[4]));
                }

                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) && !isset($seen[$email])) {
                    $seen[$email] = true;
                    $results[] = [
                        'email' => $email,
                        'name' => $name,
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Build Symfony Mailer instance for an SMTP Relay.
     *
     * @param SmtpRelay $relay
     * @return Mailer
     */
    public function buildSymfonyMailer(SmtpRelay $relay): Mailer
    {
        $isDirectSsl = ($relay->port == 465 || strtolower($relay->encryption ?? '') === 'ssl');
        $transport = new EsmtpTransport(
            $relay->host,
            $relay->port,
            $isDirectSsl
        );

        if ($relay->username) {
            $transport->setUsername($relay->username);
            $transport->setPassword($relay->password ?? '');
        }

        return new Mailer($transport);
    }

    /**
     * Get sorted list of MX hostnames for a domain (lowest priority number first).
     *
     * @param string $domain
     * @return array List of MX hostnames
     */
    public function getMxHosts(string $domain): array
    {
        $domain = strtolower(trim($domain));
        if (empty($domain)) {
            return [];
        }

        $mxHosts = [];
        $weights = [];

        if (function_exists('getmxrr') && @getmxrr($domain, $mxHosts, $weights)) {
            $records = [];
            foreach ($mxHosts as $i => $host) {
                $records[] = [
                    'host' => trim($host),
                    'weight' => isset($weights[$i]) ? (int)$weights[$i] : 10,
                ];
            }
            usort($records, fn($a, $b) => $a['weight'] <=> $b['weight']);
            return array_values(array_unique(array_column($records, 'host')));
        }

        if (function_exists('dns_get_record')) {
            $dns = @dns_get_record($domain, DNS_MX);
            if (!empty($dns)) {
                usort($dns, fn($a, $b) => ($a['pri'] ?? 10) <=> ($b['pri'] ?? 10));
                $hosts = [];
                foreach ($dns as $d) {
                    if (!empty($d['target'])) {
                        $hosts[] = trim($d['target']);
                    }
                }
                if (!empty($hosts)) {
                    return array_values(array_unique($hosts));
                }
            }
        }

        return [$domain];
    }

    /**
     * Dispatch email message to external recipients directly via DNS MX lookup on port 25.
     *
     * @param SymfonyEmail $message
     * @param array $externalRecipients Array of ['email' => string, 'name' => string]
     * @param string $envelopeSenderEmail
     * @param string $fromName
     * @param string|null $localDomain
     * @return array ['success' => bool, 'message' => string|null, 'details' => array]
     */
    public function dispatchDirectMx(
        RawMessage $message,
        array $externalRecipients,
        string $envelopeSenderEmail,
        string $fromName,
        ?string $localDomain = null
    ): array {
        if (empty($externalRecipients)) {
            return ['success' => true, 'message' => null, 'details' => []];
        }

        // Group external recipients by domain
        $byDomain = [];
        foreach ($externalRecipients as $item) {
            $email = $item['email'];
            $parts = explode('@', $email);
            $domain = strtolower(trim(end($parts)));
            $byDomain[$domain][] = $item;
        }

        $allSuccess = true;
        $failedDomains = [];
        $domainDetails = [];

        foreach ($byDomain as $domain => $recipientsForDomain) {
            $mxHosts = $this->getMxHosts($domain);
            $domainDelivered = false;
            $lastError = null;

            foreach ($mxHosts as $mxHost) {
                try {
                    $transport = new EsmtpTransport($mxHost, 25, false);
                    if ($localDomain) {
                        $transport->setLocalDomain($localDomain);
                    }
                    $transport->getStream()->setTimeout(15);
                    $mailer = new Mailer($transport);

                    $envelopeRecipients = [];
                    foreach ($recipientsForDomain as $extItem) {
                        $envelopeRecipients[] = empty($extItem['name'])
                            ? new Address($extItem['email'])
                            : new Address($extItem['email'], $extItem['name']);
                    }

                    $envelope = new Envelope(
                        new Address($envelopeSenderEmail, $fromName),
                        $envelopeRecipients
                    );

                    $mailer->send($message, $envelope);
                    $domainDelivered = true;
                    $domainDetails[$domain] = [
                        'status' => 'delivered',
                        'mx_host' => $mxHost,
                        'recipients_count' => count($recipientsForDomain),
                    ];
                    Log::info("Direct MX delivery to {$domain} via {$mxHost}:25 succeeded.");
                    break;
                } catch (Exception $mxEx) {
                    $lastError = $mxEx->getMessage();
                    Log::warning("Direct MX delivery attempt to {$domain} via {$mxHost}:25 failed: " . $lastError);
                }
            }

            if (!$domainDelivered) {
                $allSuccess = false;
                $failedDomains[$domain] = $lastError ?: 'Gagal terhubung ke seluruh server MX untuk domain ' . $domain;
                $domainDetails[$domain] = [
                    'status' => 'failed',
                    'error' => $lastError,
                ];
            }
        }

        if (!$allSuccess) {
            $errParts = [];
            foreach ($failedDomains as $dom => $err) {
                $errParts[] = "[{$dom}]: {$err}";
            }
            return [
                'success' => false,
                'message' => 'Direct MX pengiriman gagal: ' . implode('; ', $errParts),
                'details' => $domainDetails,
            ];
        }

        return [
            'success' => true,
            'message' => null,
            'details' => $domainDetails,
        ];
    }

    /**
     * Test direct outbound port 25 connectivity and DNS MX resolution for a recipient.
     *
     * @param string $recipientEmail
     * @param string|null $senderDomain
     * @return array
     */
    public function testDirectMxConnection(string $recipientEmail, ?string $senderDomain = null): array
    {
        $startTime = microtime(true);
        $parts = explode('@', $recipientEmail);
        if (count($parts) < 2) {
            return [
                'success' => false,
                'message' => 'Format email tujuan tidak valid.',
            ];
        }

        $domain = strtolower(trim(end($parts)));
        $mxHosts = $this->getMxHosts($domain);

        if (empty($mxHosts)) {
            return [
                'success' => false,
                'message' => "Tidak ditemukan catatan DNS MX untuk domain '{$domain}'.",
            ];
        }

        $senderDomain = $senderDomain ?: 'apexmail.local';
        $testedMx = null;
        $connectionOk = false;
        $lastErr = null;

        foreach ($mxHosts as $mxHost) {
            try {
                $transport = new EsmtpTransport($mxHost, 25, false);
                $transport->setLocalDomain($senderDomain);
                $transport->getStream()->setTimeout(10);
                $transport->start();
                $testedMx = $mxHost;
                $connectionOk = true;
                $transport->stop();
                break;
            } catch (Exception $e) {
                $lastErr = $e->getMessage();
            }
        }

        $latencyMs = round((microtime(true) - $startTime) * 1000);

        if ($connectionOk) {
            return [
                'success' => true,
                'message' => "Koneksi Direct MX ke {$testedMx}:25 (Domain: {$domain}) berhasil terhubung dalam {$latencyMs}ms dengan STARTTLS aktif.",
                'mx_host' => $testedMx,
                'domain' => $domain,
                'latency_ms' => $latencyMs,
            ];
        }

        return [
            'success' => false,
            'message' => "Gagal terhubung ke server MX {$domain} (Host: " . implode(', ', $mxHosts) . "): " . $lastErr,
        ];
    }

    /**
     * Sign a Symfony Email with DKIM if the domain has a private key.
     *
     * @param SymfonyEmail $message
     * @param MailAccount $account
     * @return RawMessage
     */
    public function signWithDkim(SymfonyEmail $message, MailAccount $account): RawMessage
    {
        $domain = $account->domain;
        if ($domain && !empty($domain->dkim_private_key)) {
            try {
                $signer = new DkimSigner(
                    $domain->dkim_private_key,
                    $domain->name,
                    $domain->dkim_selector ?: 'apexmail'
                );
                return $signer->sign($message);
            } catch (Exception $dkimEx) {
                Log::warning('DKIM signing failed for domain ' . ($domain->name ?? '') . ': ' . $dkimEx->getMessage());
            }
        }
        return $message;
    }

    /**
     * Send email via active SMTP Relay or Direct MX Delivery and record it in database.
     *
     * @param MailAccount $sender
     * @param array|string $to List of recipient emails or raw string
     * @param string $subject
     * @param string $bodyHtml
     * @param string|null $bodyText
     * @param array $attachments Array of uploaded files or paths
     * @param SmtpRelay|null $relay
     * @param array|string $cc List of CC recipients
     * @param array|string $bcc List of BCC recipients
     * @return array
     */
    public function sendEmail(
        MailAccount $sender,
        $to,
        string $subject,
        string $bodyHtml,
        ?string $bodyText = null,
        array $attachments = [],
        ?SmtpRelay $relay = null,
        $cc = [],
        $bcc = []
    ): array {
        $relay = $relay ?? SmtpRelay::getActiveRelay($sender->domain_id);

        $parsedTo = $this->parseRecipients($to);
        $parsedCc = $this->parseRecipients($cc);
        $parsedBcc = $this->parseRecipients($bcc);

        if (empty($parsedTo)) {
            return [
                'success' => false,
                'message' => 'Alamat email penerima kosong atau tidak memiliki format yang valid.',
            ];
        }

        $bodyText = $bodyText ?: strip_tags($bodyHtml);
        $snippet = mb_substr(trim(preg_replace('/\s+/', ' ', $bodyText)), 0, 120);

        try {
            $fromEmail = $sender->email;
            $fromName = $sender->display_name ?: $sender->username;
            $envelopeSenderEmail = $fromEmail;
            $localDomain = $sender->domain->name ?? 'apexmail.local';

            $message = (new SymfonyEmail())
                ->from(new Address($fromEmail, $fromName))
                ->replyTo(new Address($fromEmail, $fromName))
                ->subject($subject)
                ->html($bodyHtml)
                ->text($bodyText);

            foreach ($parsedTo as $item) {
                $message->addTo(empty($item['name']) ? new Address($item['email']) : new Address($item['email'], $item['name']));
            }

            foreach ($parsedCc as $item) {
                $message->addCc(empty($item['name']) ? new Address($item['email']) : new Address($item['email'], $item['name']));
            }

            foreach ($parsedBcc as $item) {
                $message->addBcc(empty($item['name']) ? new Address($item['email']) : new Address($item['email'], $item['name']));
            }

            // Handle Attachments
            foreach ($attachments as $attachment) {
                if (is_array($attachment) && isset($attachment['path'], $attachment['name'])) {
                    $message->attachFromPath($attachment['path'], $attachment['name'], $attachment['mime'] ?? null);
                }
            }

            // 3. Classify recipients: Local vs External
            $allDeliveries = array_merge($parsedTo, $parsedCc, $parsedBcc);
            $localRecipients = [];
            $externalRecipients = [];

            foreach ($allDeliveries as $recipientItem) {
                $recEmail = $recipientItem['email'];
                $localAcc = MailAccount::where('email', $recEmail)->first();
                if ($localAcc) {
                    $localRecipients[$recEmail] = [
                        'item' => $recipientItem,
                        'account' => $localAcc,
                    ];
                } else {
                    $externalRecipients[$recEmail] = $recipientItem;
                }
            }

            // 4. Dispatch for external recipients: Relay vs Direct MX
            $sendSuccess = true;
            $errorMessage = null;

            if (count($externalRecipients) > 0) {
                // Apply cryptographic DKIM signature if domain has private key
                $message = $this->signWithDkim($message, $sender);

                if ($relay && $relay->is_active) {
                    // Mode A: External SMTP Relay / Smart Host (e.g. Zimbra Relay MTA)
                    try {
                        $symfonyMailer = $this->buildSymfonyMailer($relay);
                        $envelopeRecipients = [];
                        foreach ($externalRecipients as $extItem) {
                            $envelopeRecipients[] = empty($extItem['name'])
                                ? new Address($extItem['email'])
                                : new Address($extItem['email'], $extItem['name']);
                        }

                        $envelope = new Envelope(
                            new Address($envelopeSenderEmail, $fromName),
                            $envelopeRecipients
                        );

                        $symfonyMailer->send($message, $envelope);
                        Log::info("External delivery via SMTP Relay ({$relay->host}) succeeded.");
                    } catch (Exception $sendEx) {
                        $sendSuccess = false;
                        $errorMessage = $sendEx->getMessage();
                        Log::warning('SMTP Relay Send Notice: ' . $errorMessage);
                    }
                } else {
                    // Mode B: Direct MX Delivery via DNS resolution on Port 25
                    $mxResult = $this->dispatchDirectMx(
                        $message,
                        array_values($externalRecipients),
                        $envelopeSenderEmail,
                        $fromName,
                        $localDomain
                    );

                    if (!$mxResult['success']) {
                        $sendSuccess = false;
                        $errorMessage = $mxResult['message'];
                    }
                }
            } else {
                // All recipients are local mailboxes on this server.
                Log::info("sendEmail: All recipients are local. External dispatch skipped.");
            }

            // 5. Save to Sender's "Sent" folder
            $sentFolder = $sender->folders()->firstOrCreate(
                ['slug' => 'sent'],
                ['name' => 'Terkirim', 'icon' => 'send', 'color' => '#10B981', 'is_system' => true]
            );

            $sentEmailRecord = $sentFolder->emails()->create([
                'mail_account_id' => $sender->id,
                'message_id' => '<' . uniqid('apex-', true) . '@' . ($sender->domain->name ?? 'apexmail.local') . '>',
                'from_name' => $fromName,
                'from_email' => $fromEmail,
                'to' => $parsedTo,
                'cc' => !empty($parsedCc) ? $parsedCc : null,
                'bcc' => !empty($parsedBcc) ? $parsedBcc : null,
                'subject' => $subject,
                'snippet' => $snippet,
                'body_text' => $bodyText,
                'body_html' => $bodyHtml,
                'is_read' => true,
                'is_starred' => false,
                'is_draft' => false,
                'has_attachments' => !empty($attachments),
                'delivery_status' => $sendSuccess ? 'delivered' : 'deferred',
                'delivery_error' => $sendSuccess ? null : $errorMessage,
                'queued_at' => $sendSuccess ? null : Carbon::now(),
                'sent_at' => Carbon::now(),
            ]);

            // Save attachment records
            foreach ($attachments as $att) {
                if (is_array($att) && isset($att['name'], $att['path'])) {
                    $sentEmailRecord->attachments()->create([
                        'filename' => $att['name'],
                        'content_type' => $att['mime'] ?? 'application/octet-stream',
                        'size_bytes' => $att['size'] ?? 0,
                        'storage_path' => $att['path'],
                    ]);
                }
            }

            // 6. Internal Delivery: If recipient exists locally, deliver directly to their Inbox!
            foreach ($localRecipients as $recEmail => $localData) {
                $localAccount = $localData['account'];
                if ($localAccount->id !== $sender->id) {
                    $inboxFolder = $localAccount->folders()->firstOrCreate(
                        ['slug' => 'inbox'],
                        ['name' => 'Kotak Masuk', 'icon' => 'inbox', 'color' => '#2563EB', 'is_system' => true]
                    );

                    $inboxFolder->emails()->create([
                        'mail_account_id' => $localAccount->id,
                        'message_id' => $sentEmailRecord->message_id,
                        'from_name' => $fromName,
                        'from_email' => $fromEmail,
                        'to' => $parsedTo,
                        'cc' => !empty($parsedCc) ? $parsedCc : null,
                        'bcc' => !empty($parsedBcc) ? $parsedBcc : null,
                        'subject' => $subject,
                        'snippet' => $snippet,
                        'body_text' => $bodyText,
                        'body_html' => $bodyHtml,
                        'is_read' => false,
                        'is_starred' => false,
                        'is_draft' => false,
                        'has_attachments' => !empty($attachments),
                        'sent_at' => Carbon::now(),
                        'received_at' => Carbon::now(),
                    ]);

                    $inboxFolder->increment('unread_count');
                }
            }

            if (!$sendSuccess) {
                return [
                    'success' => false,
                    'message' => 'Email disimpan di folder Terkirim, namun pengiriman relay menghasilkan respon: ' . $errorMessage,
                    'email_id' => $sentEmailRecord->id,
                ];
            }

            return [
                'success' => true,
                'message' => 'Pesan terkirim',
                'email_id' => $sentEmailRecord->id,
            ];
        } catch (Exception $e) {
            Log::error('MailRelayService Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gagal memproses pengiriman: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Retry sending an email that was deferred or held in queue.
     *
     * @param Email $email
     * @return array
     */
    public function retryEmail(Email $email): array
    {
        $sender = $email->account;
        if (!$sender) {
            return [
                'success' => false,
                'message' => 'Akun pengirim tidak ditemukan.',
            ];
        }

        $relay = SmtpRelay::getActiveRelay($sender->domain_id);
        $localDomain = $sender->domain->name ?? 'apexmail.local';

        try {
            $fromEmail = $sender->email;
            $fromName = $sender->display_name ?: $sender->username;

            // Envelope MAIL FROM: Always use the authentic sender email ($fromEmail)
            // so envelope sender and RFC 5322 From header are fully aligned with the actual domain.
            $envelopeSenderEmail = $fromEmail;

            $message = (new SymfonyEmail())
                ->from(new Address($fromEmail, $fromName))
                ->replyTo(new Address($fromEmail, $fromName))
                ->subject($email->subject ?: '(Tanpa Subjek)')
                ->html($email->body_html ?: nl2br(e($email->body_text ?? '')))
                ->text($email->body_text ?: strip_tags($email->body_html ?? ''));

            $parsedTo = is_array($email->to) ? $email->to : $this->parseRecipients($email->to);
            $parsedCc = is_array($email->cc) ? $email->cc : $this->parseRecipients($email->cc);
            $parsedBcc = is_array($email->bcc) ? $email->bcc : $this->parseRecipients($email->bcc);

            foreach ($parsedTo as $item) {
                $message->addTo(empty($item['name']) ? new Address($item['email']) : new Address($item['email'], $item['name']));
            }
            foreach ($parsedCc as $item) {
                $message->addCc(empty($item['name']) ? new Address($item['email']) : new Address($item['email'], $item['name']));
            }
            foreach ($parsedBcc as $item) {
                $message->addBcc(empty($item['name']) ? new Address($item['email']) : new Address($item['email'], $item['name']));
            }

            // Attach existing attachments
            foreach ($email->attachments as $att) {
                if ($att->storage_path && file_exists($att->storage_path)) {
                    $message->attachFromPath($att->storage_path, $att->filename, $att->content_type);
                }
            }

            // External recipients
            $allDeliveries = array_merge($parsedTo, $parsedCc, $parsedBcc);
            $externalRecipients = [];
            foreach ($allDeliveries as $recipientItem) {
                $recEmail = $recipientItem['email'];
                $localAcc = MailAccount::where('email', $recEmail)->first();
                if (!$localAcc) {
                    $externalRecipients[$recEmail] = $recipientItem;
                }
            }

            if (count($externalRecipients) > 0) {
                // Apply cryptographic DKIM signature if domain has private key
                $message = $this->signWithDkim($message, $sender);

                if ($relay && $relay->is_active) {
                    $symfonyMailer = $this->buildSymfonyMailer($relay);
                    $envelopeRecipients = [];
                    foreach ($externalRecipients as $extItem) {
                        $envelopeRecipients[] = empty($extItem['name'])
                            ? new Address($extItem['email'])
                            : new Address($extItem['email'], $extItem['name']);
                    }

                    $envelope = new Envelope(
                        new Address($envelopeSenderEmail, $fromName),
                        $envelopeRecipients
                    );

                    $symfonyMailer->send($message, $envelope);
                } else {
                    $mxResult = $this->dispatchDirectMx(
                        $message,
                        array_values($externalRecipients),
                        $envelopeSenderEmail,
                        $fromName,
                        $localDomain
                    );

                    if (!$mxResult['success']) {
                        throw new Exception($mxResult['message']);
                    }
                }
            }

            $email->update([
                'delivery_status' => 'delivered',
                'delivery_error' => null,
                'sent_at' => Carbon::now(),
            ]);

            return [
                'success' => true,
                'message' => 'Pesan dalam antrean berhasil dikirimkan.',
            ];
        } catch (Exception $e) {
            Log::warning('SMTP Relay Retry Failed: ' . $e->getMessage());
            $email->update([
                'delivery_status' => 'deferred',
                'delivery_error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Pengiriman ulang gagal: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Test connection and send diagnostic email to verify Brevo / SMTP Relay credentials.
     *
     * @param SmtpRelay $relay
     * @param string $recipientEmail
     * @return array
     */
    public function testRelayConnection(SmtpRelay $relay, string $recipientEmail): array
    {
        $startTime = microtime(true);

        try {
            // Port 465 uses direct SSL/TLS wrapper; Port 587 uses STARTTLS
            $isDirectSsl = ($relay->port == 465 || strtolower($relay->encryption ?? '') === 'ssl');
            $transport = new EsmtpTransport(
                $relay->host,
                $relay->port,
                $isDirectSsl
            );

            if ($relay->username) {
                $transport->setUsername($relay->username);
                $transport->setPassword($relay->password ?? '');
            }

            $symfonyMailer = new Mailer($transport);

            $fromEmail = $relay->sender_email ?: ($relay->username ?: 'test@apexmail.local');
            $fromName = $relay->sender_name ?: 'ApexMail Diagnostic Engine';

            $testEmail = (new SymfonyEmail())
                ->from(new Address($fromEmail, $fromName))
                ->to(new Address($recipientEmail))
                ->subject('ApexMail SMTP Relay Diagnostic Test')
                ->html('
                    <div style="font-family: sans-serif; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <h2 style="color: #2563eb; margin-top: 0;">Uji Koneksi SMTP Relay Berhasil!</h2>
                        <p>Halo,</p>
                        <p>Ini adalah email diagnostik dari <strong>ApexMail Collaboration Suite</strong>.</p>
                        <div style="background: #f8fafc; padding: 12px; border-radius: 6px; font-family: monospace; font-size: 13px; margin: 15px 0;">
                            <strong>Relay Host:</strong> ' . htmlspecialchars($relay->host) . ':' . $relay->port . '<br>
                            <strong>Provider:</strong> ' . htmlspecialchars(strtoupper($relay->provider)) . '<br>
                            <strong>Enkripsi:</strong> ' . htmlspecialchars(strtoupper($relay->encryption ?? 'NONE')) . '<br>
                            <strong>Waktu Pengujian:</strong> ' . Carbon::now()->toDateTimeString() . '
                        </div>
                        <p style="color: #10b981; font-weight: bold;">✔ Kredensial dan jalur pengiriman terverifikasi aktif.</p>
                    </div>
                ');

            $symfonyMailer->send($testEmail);

            $latencyMs = round((microtime(true) - $startTime) * 1000);
            $successMsg = "Koneksi ke {$relay->host}:{$relay->port} sukses! Email pengujian berhasil terkirim dalam {$latencyMs}ms.";

            $relay->update([
                'last_tested_at' => Carbon::now(),
                'last_test_status' => 'success',
                'last_test_message' => $successMsg,
            ]);

            return [
                'success' => true,
                'message' => $successMsg,
            ];
        } catch (Exception $e) {
            $errorMsg = 'Gagal terhubung ke relay: ' . $e->getMessage();

            $relay->update([
                'last_tested_at' => Carbon::now(),
                'last_test_status' => 'failed',
                'last_test_message' => $errorMsg,
            ]);

            return [
                'success' => false,
                'message' => $errorMsg,
            ];
        }
    }

    /**
     * Send OTP Reset Password to user's recovery email via active SMTP relay.
     *
     * @param MailAccount $account
     * @param string $otpCode
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendPasswordResetOtp(MailAccount $account, string $otpCode): array
    {
        if (empty($account->recovery_email)) {
            return [
                'success' => false,
                'message' => 'Akun ini belum memiliki alamat email pemulihan yang terdaftar.',
            ];
        }

        $relay = SmtpRelay::getActiveRelay($account->domain_id);

        try {
            $fromEmail = ($relay && $relay->is_active && $relay->sender_email)
                ? $relay->sender_email
                : ('noreply@' . ($account->domain->name ?? 'apexmail.local'));
            $fromName = ($relay && $relay->is_active && $relay->sender_name)
                ? $relay->sender_name
                : 'ApexMail Security';

            $subject = 'Kode OTP Pemulihan Kata Sandi ApexMail: ' . $otpCode;

            $bodyHtml = '
            <!DOCTYPE html>
            <html>
            <head><meta charset="utf-8"></head>
            <body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; padding: 30px 10px; margin: 0;">
                <div style="max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                    <div style="background: #2563eb; padding: 24px; text-align: center;">
                        <h1 style="color: #ffffff; font-size: 22px; font-weight: 700; margin: 0; letter-spacing: -0.5px;">ApexMail</h1>
                        <p style="color: #bfdbfe; font-size: 12px; margin: 4px 0 0 0;">Verifikasi Keamanan Akun</p>
                    </div>
                    <div style="padding: 32px 24px; color: #1e293b;">
                        <h2 style="font-size: 16px; font-weight: 600; color: #0f172a; margin-top: 0;">Halo, ' . htmlspecialchars($account->display_name ?: $account->username) . '</h2>
                        <p style="font-size: 13px; line-height: 1.6; color: #475569;">
                            Kami menerima permintaan untuk mereset kata sandi akun email <strong>' . htmlspecialchars($account->email) . '</strong>.
                        </p>
                        <p style="font-size: 13px; line-height: 1.6; color: #475569;">
                            Gunakan kode One-Time Password (OTP) berikut untuk melanjutkan proses reset kata sandi Anda:
                        </p>
                        <div style="text-align: center; margin: 24px 0;">
                            <div style="display: inline-block; background: #eff6ff; border: 2px dashed #2563eb; border-radius: 12px; padding: 14px 28px; font-size: 28px; font-weight: 800; letter-spacing: 6px; color: #1d4ed8; font-family: Courier, monospace;">
                                ' . htmlspecialchars($otpCode) . '
                            </div>
                        </div>
                        <p style="font-size: 12px; color: #64748b; line-height: 1.5; text-align: center;">
                            Kode ini berlaku selama <strong>15 menit</strong>. Jangan berikan kode ini kepada siapapun demi keamanan akun Anda.
                        </p>
                        <hr style="border: none; border-top: 1px solid #f1f5f9; margin: 24px 0;">
                        <p style="font-size: 11px; color: #94a3b8; margin: 0; line-height: 1.4;">
                            Jika Anda tidak pernah meminta perubahan kata sandi ini, silakan abaikan email ini atau segera hubungi Administrator IT Anda.
                        </p>
                    </div>
                    <div style="background: #f8fafc; padding: 16px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 11px; color: #94a3b8;">
                        &copy; ' . date('Y') . ' ApexMail Collaboration Suite. Hak cipta dilindungi.
                    </div>
                </div>
            </body>
            </html>
            ';

            $bodyText = "Halo {$account->display_name},\n\nKode OTP reset kata sandi Anda untuk akun {$account->email} adalah: {$otpCode}\n\nKode ini berlaku selama 15 menit. Jangan berikan kode ini kepada siapapun.";

            $message = (new SymfonyEmail())
                ->from(new Address($fromEmail, $fromName))
                ->to(new Address($account->recovery_email, $account->display_name ?: $account->username))
                ->subject($subject)
                ->html($bodyHtml)
                ->text($bodyText);

            // Sign with DKIM if configured
            $message = $this->signWithDkim($message, $account);

            if ($relay && $relay->is_active) {
                $symfonyMailer = $this->buildSymfonyMailer($relay);
                $symfonyMailer->send($message);
            } else {
                $mxRes = $this->dispatchDirectMx(
                    $message,
                    [['email' => $account->recovery_email, 'name' => $account->display_name ?: $account->username]],
                    $fromEmail,
                    $fromName,
                    $account->domain->name ?? 'apexmail.local'
                );

                if (!$mxRes['success']) {
                    throw new Exception($mxRes['message']);
                }
            }

            return [
                'success' => true,
                'message' => 'Kode OTP berhasil dikirim ke ' . $this->maskEmail($account->recovery_email),
            ];
        } catch (Exception $e) {
            Log::error('Gagal mengirimkan OTP Reset Sandi: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gagal mengirim email OTP: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Mask email address for privacy (e.g. k***g@azkario.biz.id).
     */
    public function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) < 2) return $email;

        $name = $parts[0];
        $domain = $parts[1];

        $len = strlen($name);
        if ($len <= 2) {
            $maskedName = substr($name, 0, 1) . '*';
        } else {
            $maskedName = substr($name, 0, 1) . str_repeat('*', min(4, $len - 2)) . substr($name, -1);
        }

        return $maskedName . '@' . $domain;
    }

    /**
     * Format a Carbon date/time instance into Indonesian format (e.g. Senin, 5 Oktober 2026 10:54:03).
     */
    public function formatIndonesianDateTime(Carbon $date): string
    {
        $days = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $dayName = $days[$date->format('l')] ?? $date->format('l');
        $dayNumber = $date->format('j');
        $monthName = $months[(int)$date->format('n')] ?? $date->format('F');
        $year = $date->format('Y');
        $time = $date->format('H:i:s');

        return "{$dayName}, {$dayNumber} {$monthName} {$year} {$time}";
    }

    /**
     * Send new device & location login notification with OTP verification to user's recovery email.
     */
    public function sendNewDeviceLoginAlert(MailAccount $account, string $ipAddress, string $userAgent, Carbon $loginTime, ?string $otpCode = null): array
    {
        $targetEmail = !empty($account->recovery_email) ? $account->recovery_email : $account->email;
        if (empty($targetEmail)) {
            return [
                'success' => false,
                'message' => 'Akun tidak memiliki email penerima notifikasi.',
            ];
        }

        $relay = SmtpRelay::where('is_active', true)->first();

        $userName = strtoupper($account->display_name ?: $account->username);
        $formattedDate = $this->formatIndonesianDateTime($loginTime);
        $resetUrl = route('password.forgot', ['email' => $account->email]);

        if ($otpCode) {
            $subject = 'Kode OTP Verifikasi Login Perangkat Baru: ' . $otpCode . ' - ApexMail';

            $bodyText = "Hi, {$userName}\n" .
                "Akun anda terdeteksi melakukan login menggunakan Perangkat dan Lokasi baru.\n" .
                "Silakan masukkan kode One-Time Password (OTP) berikut untuk memverifikasi perangkat Anda dan mengakses dashboard email:\n\n" .
                "KODE OTP: {$otpCode}\n\n" .
                "Kode ini berlaku selama 15 menit. Jangan berikan kode ini kepada siapapun demi keamanan akun Anda.\n\n" .
                "Jika aktifitas login tersebut bukan anda, segera lakukan pergantian password untuk melindungi akun dan data pribadi anda.\n" .
                "Klik ini untuk melakukan pergantian password:\n{$resetUrl}\n\n" .
                "Riwayat Login :\n" .
                "IP Address : {$ipAddress}\n" .
                "Device Info : {$userAgent}\n" .
                "Pada Tanggal {$formattedDate}\n";

            $bodyHtml = '
            <!DOCTYPE html>
            <html>
            <head><meta charset="utf-8"></head>
            <body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; padding: 30px 10px; margin: 0;">
                <div style="max-width: 540px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                    <div style="background: #1e293b; padding: 22px 24px; text-align: left;">
                        <div style="display: inline-block; background-color: #ef4444; color: #ffffff; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 4px; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 6px;">Keamanan Akun</div>
                        <h1 style="color: #ffffff; font-size: 20px; font-weight: 700; margin: 0; letter-spacing: -0.5px;">ApexMail Security</h1>
                    </div>
                    <div style="padding: 28px 24px; color: #1e293b;">
                        <h2 style="font-size: 16px; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 14px;">Hi, ' . htmlspecialchars($userName) . '</h2>
                        <p style="font-size: 13px; line-height: 1.6; color: #334155; margin: 0 0 16px 0;">
                            Akun anda terdeteksi melakukan login menggunakan Perangkat dan Lokasi baru.
                            Silakan masukkan kode One-Time Password (OTP) berikut untuk memverifikasi perangkat Anda dan mengakses dashboard email:
                        </p>

                        <!-- OTP Display Box -->
                        <div style="text-align: center; margin: 24px 0;">
                            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Kode OTP Verifikasi Perangkat Baru</div>
                            <div style="display: inline-block; background: #eff6ff; border: 2px dashed #2563eb; border-radius: 12px; padding: 14px 28px; font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #1d4ed8; font-family: Courier, monospace;">
                                ' . htmlspecialchars($otpCode) . '
                            </div>
                            <div style="font-size: 11px; color: #64748b; margin-top: 8px;">Berlaku selama <strong>15 menit</strong>. Jangan berikan kode ini kepada siapapun.</div>
                        </div>

                        <!-- Riwayat Login Box -->
                        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 18px; margin-top: 22px;">
                            <h3 style="margin-top: 0; margin-bottom: 10px; font-size: 12px; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px;">
                                Riwayat Login :
                            </h3>
                            <table style="width: 100%; border-collapse: collapse; font-size: 12px; line-height: 1.6;">
                                <tr>
                                    <td style="color: #64748b; width: 100px; padding: 4px 0; vertical-align: top; font-weight: 600;">IP Address</td>
                                    <td style="color: #0f172a; padding: 4px 0; font-family: monospace;">: ' . htmlspecialchars($ipAddress) . '</td>
                                </tr>
                                <tr>
                                    <td style="color: #64748b; padding: 4px 0; vertical-align: top; font-weight: 600;">Device Info</td>
                                    <td style="color: #334155; padding: 4px 0; font-family: monospace; font-size: 11px; word-break: break-all;">: ' . htmlspecialchars($userAgent) . '</td>
                                </tr>
                                <tr>
                                    <td style="color: #64748b; padding: 4px 0; vertical-align: top; font-weight: 600;">Pada Tanggal</td>
                                    <td style="color: #0f172a; padding: 4px 0;">: ' . htmlspecialchars($formattedDate) . '</td>
                                </tr>
                            </table>
                        </div>

                        <p style="font-size: 12px; line-height: 1.6; color: #475569; margin: 20px 0 10px 0;">
                            Jika aktifitas login tersebut bukan anda, segera lakukan pergantian password untuk melindungi akun dan data pribadi anda.
                        </p>
                        <div style="margin: 12px 0 20px 0;">
                            <a href="' . htmlspecialchars($resetUrl) . '" 
                               style="display: inline-block; background-color: #dc2626; color: #ffffff; padding: 10px 20px; border-radius: 10px; font-weight: 700; font-size: 12px; text-decoration: none; box-shadow: 0 2px 4px rgba(220, 38, 38, 0.2);">
                                Klik ini untuk melakukan pergantian password.
                            </a>
                        </div>

                        <hr style="border: none; border-top: 1px solid #f1f5f9; margin: 24px 0 16px 0;">
                        <p style="font-size: 11px; color: #94a3b8; margin: 0; line-height: 1.4;">
                            Pemberitahuan keamanan ini dikirimkan ke alamat email pemulihan terdaftar (' . htmlspecialchars($this->maskEmail($targetEmail)) . ') untuk akun ' . htmlspecialchars($account->email) . '.
                        </p>
                    </div>
                    <div style="background: #f8fafc; padding: 14px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 11px; color: #94a3b8;">
                        &copy; ' . date('Y') . ' ApexMail Collaboration Suite. Hak cipta dilindungi.
                    </div>
                </div>
            </body>
            </html>
            ';
        } else {
            $subject = 'Pemberitahuan Login dari Perangkat dan Lokasi Baru - ApexMail';

            $bodyText = "Hi, {$userName}\n" .
                "Akun anda terdeteksi melakukan login menggunakan Perangkat dan Lokasi baru. Jika aktifitas login tersebut bukan anda, segera lakukan pergantian password untuk melindungi akun dan data pribadi anda.\n" .
                "Klik ini untuk melakukan pergantian password:\n{$resetUrl}\n\n" .
                "Riwayat Login :\n" .
                "IP Address : {$ipAddress}\n" .
                "Device Info : {$userAgent}\n" .
                "Pada Tanggal {$formattedDate}\n";

            $bodyHtml = '
            <!DOCTYPE html>
            <html>
            <head><meta charset="utf-8"></head>
            <body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; padding: 30px 10px; margin: 0;">
                <div style="max-width: 540px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                    <div style="background: #1e293b; padding: 22px 24px; text-align: left;">
                        <div style="display: inline-block; background-color: #ef4444; color: #ffffff; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 4px; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 6px;">Keamanan Akun</div>
                        <h1 style="color: #ffffff; font-size: 20px; font-weight: 700; margin: 0; letter-spacing: -0.5px;">ApexMail Security</h1>
                    </div>
                    <div style="padding: 28px 24px; color: #1e293b;">
                        <h2 style="font-size: 16px; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 14px;">Hi, ' . htmlspecialchars($userName) . '</h2>
                        <p style="font-size: 13px; line-height: 1.6; color: #334155; margin: 0 0 16px 0;">
                            Akun anda terdeteksi melakukan login menggunakan Perangkat dan Lokasi baru. Jika aktifitas login tersebut bukan anda, segera lakukan pergantian password untuk melindungi akun dan data pribadi anda.
                        </p>
                        <div style="margin: 20px 0;">
                            <a href="' . htmlspecialchars($resetUrl) . '" 
                               style="display: inline-block; background-color: #dc2626; color: #ffffff; padding: 11px 22px; border-radius: 10px; font-weight: 700; font-size: 12px; text-decoration: none; box-shadow: 0 2px 4px rgba(220, 38, 38, 0.2);">
                                Klik ini untuk melakukan pergantian password.
                            </a>
                        </div>
                        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 18px; margin-top: 22px;">
                            <h3 style="margin-top: 0; margin-bottom: 10px; font-size: 12px; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px;">
                                Riwayat Login :
                            </h3>
                            <table style="width: 100%; border-collapse: collapse; font-size: 12px; line-height: 1.6;">
                                <tr>
                                    <td style="color: #64748b; width: 100px; padding: 4px 0; vertical-align: top; font-weight: 600;">IP Address</td>
                                    <td style="color: #0f172a; padding: 4px 0; font-family: monospace;">: ' . htmlspecialchars($ipAddress) . '</td>
                                </tr>
                                <tr>
                                    <td style="color: #64748b; padding: 4px 0; vertical-align: top; font-weight: 600;">Device Info</td>
                                    <td style="color: #334155; padding: 4px 0; font-family: monospace; font-size: 11px; word-break: break-all;">: ' . htmlspecialchars($userAgent) . '</td>
                                </tr>
                                <tr>
                                    <td style="color: #64748b; padding: 4px 0; vertical-align: top; font-weight: 600;">Pada Tanggal</td>
                                    <td style="color: #0f172a; padding: 4px 0;">: ' . htmlspecialchars($formattedDate) . '</td>
                                </tr>
                            </table>
                        </div>
                        <hr style="border: none; border-top: 1px solid #f1f5f9; margin: 24px 0 16px 0;">
                        <p style="font-size: 11px; color: #94a3b8; margin: 0; line-height: 1.4;">
                            Pemberitahuan keamanan ini dikirimkan ke alamat email pemulihan terdaftar (' . htmlspecialchars($this->maskEmail($targetEmail)) . ') untuk akun ' . htmlspecialchars($account->email) . '.
                        </p>
                    </div>
                    <div style="background: #f8fafc; padding: 14px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 11px; color: #94a3b8;">
                        &copy; ' . date('Y') . ' ApexMail Collaboration Suite. Hak cipta dilindungi.
                    </div>
                </div>
            </body>
            </html>
            ';
        }

        // Check if recipient is a local mailbox in ApexMail - deliver internally too!
        $localRecipient = MailAccount::where('email', $targetEmail)->first();
        if ($localRecipient) {
            try {
                $inboxFolder = $localRecipient->folders()->firstOrCreate(
                    ['slug' => 'inbox'],
                    ['name' => 'Kotak Masuk', 'icon' => 'inbox', 'color' => '#2563EB', 'is_system' => true]
                );

                $inboxFolder->emails()->create([
                    'mail_account_id' => $localRecipient->id,
                    'message_id' => '<' . uniqid('apex-sec-', true) . '@' . ($account->domain->name ?? 'apexmail.local') . '>',
                    'from_name' => 'ApexMail Security',
                    'from_email' => 'no-reply@' . ($account->domain->name ?? 'apexmail.local'),
                    'to' => [['email' => $targetEmail, 'name' => $userName]],
                    'subject' => $subject,
                    'snippet' => $otpCode ? "Kode OTP Verifikasi Login: {$otpCode}" : 'Akun anda terdeteksi melakukan login menggunakan Perangkat dan Lokasi baru.',
                    'body_text' => $bodyText,
                    'body_html' => $bodyHtml,
                    'is_read' => false,
                    'is_starred' => true,
                    'is_draft' => false,
                    'has_attachments' => false,
                    'sent_at' => $loginTime,
                ]);
            } catch (Exception $localEx) {
                Log::warning('Gagal menyimpan notifikasi login baru ke Inbox lokal: ' . $localEx->getMessage());
            }
        }

        // Send through SMTP relay if active, or fallback to Direct MX
        if ($relay && $relay->is_active) {
            try {
                $symfonyMailer = $this->buildSymfonyMailer($relay);

                $fromEmail = $relay->sender_email ?: ($relay->username ?: ('noreply@' . ($account->domain->name ?? 'apexmail.local')));
                $fromName = $relay->sender_name ?: 'ApexMail Security';

                $message = (new SymfonyEmail())
                    ->from(new Address($fromEmail, $fromName))
                    ->to(new Address($targetEmail, $userName))
                    ->subject($subject)
                    ->html($bodyHtml)
                    ->text($bodyText);

                $message = $this->signWithDkim($message, $account);

                $symfonyMailer->send($message);

                return [
                    'success' => true,
                    'message' => 'Email pemberitahuan login baru berhasil dikirim ke ' . $this->maskEmail($targetEmail),
                ];
            } catch (Exception $e) {
                Log::error('Gagal mengirimkan notifikasi login baru via relay: ' . $e->getMessage());
                return [
                    'success' => false,
                    'message' => 'Gagal mengirim email: ' . $e->getMessage(),
                ];
            }
        }

        // Direct MX Delivery
        try {
            $fromEmail = 'noreply@' . ($account->domain->name ?? 'apexmail.local');
            $fromName = 'ApexMail Security';

            $message = (new SymfonyEmail())
                ->from(new Address($fromEmail, $fromName))
                ->to(new Address($targetEmail, $userName))
                ->subject($subject)
                ->html($bodyHtml)
                ->text($bodyText);

            $message = $this->signWithDkim($message, $account);

            $mxRes = $this->dispatchDirectMx(
                $message,
                [['email' => $targetEmail, 'name' => $userName]],
                $fromEmail,
                $fromName,
                $account->domain->name ?? 'apexmail.local'
            );

            if ($mxRes['success']) {
                return [
                    'success' => true,
                    'message' => 'Email pemberitahuan login baru berhasil dikirim ke ' . $this->maskEmail($targetEmail),
                ];
            }

            return [
                'success' => false,
                'message' => 'Gagal mengirim email: ' . $mxRes['message'],
            ];
        } catch (Exception $e) {
            Log::error('Gagal mengirimkan notifikasi login baru via direct MX: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gagal mengirim email: ' . $e->getMessage(),
            ];
        }
    }
}
