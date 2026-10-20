<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Domain;
use App\Models\SmtpRelay;
use App\Models\MailAccount;
use App\Models\Contact;
use App\Models\CalendarEvent;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Default Domain
        $domain = Domain::create([
            'name' => 'apexmail.local',
            'description' => 'Primary Enterprise Corporate Domain',
            'is_active' => true,
            'is_verified' => true,
            'dkim_selector' => 'apexmail',
            'spf_record' => 'v=spf1 include:spf.brevo.com ~all',
            'dmarc_record' => 'v=DMARC1; p=none; rua=mailto:dmarc@apexmail.local',
            'mx_record' => 'mail.apexmail.local',
        ]);

        // 2. Preset Brevo SMTP Relay configuration
        $relay = SmtpRelay::create([
            'domain_id' => $domain->id,
            'name' => 'Brevo Production Relay',
            'provider' => 'brevo',
            'host' => 'smtp-relay.brevo.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'your-brevo-login@domain.com',
            'password' => 'xsmtp-your-brevo-api-key',
            'sender_email' => 'no-reply@apexmail.local',
            'sender_name' => 'ApexMail Notification',
            'is_active' => true,
            'is_default' => true,
        ]);

        // 3. Create Admin Account
        $admin = MailAccount::create([
            'domain_id' => $domain->id,
            'username' => 'admin',
            'email' => 'admin@apexmail.local',
            'display_name' => 'System Administrator',
            'password' => Hash::make('password'),
            'quota_bytes' => 53687091200, // 50 GB
            'is_admin' => true,
            'is_active' => true,
            'signature' => "--\nSystem Administrator\nApexMail Collaboration Suite",
        ]);
        $admin->createDefaultFolders();

        // 4. Create Demo User Account
        $user = MailAccount::create([
            'domain_id' => $domain->id,
            'username' => 'budi',
            'email' => 'budi@apexmail.local',
            'display_name' => 'Budi Pratama',
            'password' => Hash::make('password'),
            'quota_bytes' => 10737418240, // 10 GB
            'is_admin' => false,
            'is_active' => true,
            'signature' => "--\nBudi Pratama\nHead of Engineering",
        ]);
        $user->createDefaultFolders();

        // 5. Populate Sample Emails for Budi
        $inbox = $user->folders()->where('slug', 'inbox')->first();
        $sent = $user->folders()->where('slug', 'sent')->first();

        if ($inbox) {
            $inbox->emails()->create([
                'mail_account_id' => $user->id,
                'message_id' => '<welcome-001@apexmail.local>',
                'from_name' => 'ApexMail Team',
                'from_email' => 'system@apexmail.local',
                'to' => [['name' => $user->display_name, 'email' => $user->email]],
                'subject' => 'Selamat Datang di ApexMail Collaboration Suite',
                'snippet' => 'Selamat datang di email suite modern pengganti Zimbra dengan tampilan cerah dan kinerja kilat.',
                'body_text' => "Halo Budi,\n\nSelamat datang di ApexMail Suite! Nikmati pengalaman berkirim email yang bersih, cepat, tanpa tombol berlebih, dan didukung SMTP Relay kelas enterprise seperti Brevo.\n\nSalam,\nTim ApexMail",
                'body_html' => '<div style="font-family: sans-serif; line-height: 1.6; color: #1e293b;">
                    <h2 style="color: #2563eb;">Selamat Datang di ApexMail Collaboration Suite</h2>
                    <p>Halo <strong>Budi Pratama</strong>,</p>
                    <p>Selamat datang di platform email modern organisasi Anda! ApexMail dirancang sebagai alternatif elegan dari Zimbra dengan fokus pada:</p>
                    <ul>
                        <li><strong>Antarmuka Cerah & Minimalis</strong>: Bersih, elegan, tanpa tombol atau toolbar yang bertumpuk.</li>
                        <li><strong>Dukungan SMTP Relay</strong>: Terhubung lancar dengan Brevo, Amazon SES, dan provider cloud terpercaya.</li>
                        <li><strong>Groupware Terintegrasi</strong>: Kalender rapat, buku kontak (GAL), dan ruang penyimpanan berkas.</li>
                    </ul>
                    <p>Silakan jelajahi fitur yang tersedia atau mulai mengirimkan pesan baru.</p>
                    <p style="margin-top: 24px; color: #64748b;">Salam hangat,<br><strong>Tim ApexMail</strong></p>
                </div>',
                'is_read' => false,
                'is_starred' => true,
                'sent_at' => Carbon::now()->subMinutes(25),
                'received_at' => Carbon::now()->subMinutes(24),
            ]);

            $inbox->emails()->create([
                'mail_account_id' => $user->id,
                'message_id' => '<meeting-002@apexmail.local>',
                'from_name' => 'Sarah Wijaya',
                'from_email' => 'sarah@apexmail.local',
                'to' => [['name' => $user->display_name, 'email' => $user->email]],
                'subject' => 'Jadwal Rapat Evaluasi Arsitektur Mail Server Baru',
                'snippet' => 'Undangan rapat koordinasi teknis migrasi dari Zimbra ke ApexMail besok pagi pukul 10:00 WIB.',
                'body_text' => "Halo Mas Budi,\n\nMohon kehadirannya pada sesi evaluasi arsitektur mail server baru besok pagi pukul 10:00 WIB di Ruang Rapat Utama atau via video call.\n\nTerima kasih,\nSarah",
                'body_html' => '<div style="font-family: sans-serif; line-height: 1.6; color: #1e293b;">
                    <p>Halo Mas Budi,</p>
                    <p>Mohon kehadirannya pada sesi <strong>Evaluasi Arsitektur Mail Server Baru</strong> yang dijadwalkan besok pagi:</p>
                    <div style="background: #f1f5f9; padding: 12px 16px; border-radius: 8px; margin: 16px 0;">
                        📅 <strong>Waktu:</strong> Besok, 10:00 - 11:00 WIB<br>
                        📍 <strong>Lokasi:</strong> Ruang Rapat Lantai 3 / Online Meeting
                    </div>
                    <p>Terima kasih,<br><strong>Sarah Wijaya</strong></p>
                </div>',
                'is_read' => true,
                'is_starred' => false,
                'sent_at' => Carbon::now()->subHours(2),
                'received_at' => Carbon::now()->subHours(2),
            ]);
        }

        // 6. Populate Global Address List (GAL) Contacts
        Contact::create([
            'domain_id' => $domain->id,
            'name' => 'Ahmad Fauzi',
            'email' => 'ahmad@apexmail.local',
            'phone' => '+62 812-3456-7890',
            'company' => 'Apex Corporation',
            'job_title' => 'Chief Technology Officer',
            'is_gal' => true,
        ]);

        Contact::create([
            'domain_id' => $domain->id,
            'name' => 'Sarah Wijaya',
            'email' => 'sarah@apexmail.local',
            'phone' => '+62 813-9876-5432',
            'company' => 'Apex Corporation',
            'job_title' => 'Lead DevOps Engineer',
            'is_gal' => true,
        ]);

        // 7. Populate Calendar Event
        CalendarEvent::create([
            'mail_account_id' => $user->id,
            'title' => 'Rapat Evaluasi Arsitektur Mail Server',
            'description' => 'Membahas performa migrasi dari Zimbra ke ApexMail serta pengujian relay Brevo.',
            'location' => 'Ruang Rapat Lantai 3',
            'meeting_link' => 'https://meet.jit.si/apexmail-evaluasi-arsitektur',
            'start_time' => Carbon::tomorrow()->setTime(10, 0, 0),
            'end_time' => Carbon::tomorrow()->setTime(11, 0, 0),
            'is_all_day' => false,
            'color' => '#2563EB',
            'attendees' => ['budi@apexmail.local', 'sarah@apexmail.local', 'ahmad@apexmail.local'],
        ]);
    }
}
