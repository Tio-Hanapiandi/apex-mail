<?php

namespace App\Console\Commands;

use App\Models\Email;
use App\Services\MailRelayService;
use Illuminate\Console\Command;

class ProcessDeferredQueueCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:process-deferred';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Proses dan kirim ulang antrean email keluar yang berstatus deferred';

    /**
     * Execute the console command.
     */
    public function handle(MailRelayService $relayService): int
    {
        $deferredEmails = Email::where('delivery_status', 'deferred')->get();

        if ($deferredEmails->isEmpty()) {
            $this->info('Tidak ada email yang tertahan (deferred) di antrean.');
            return 0;
        }

        $this->info("Memproses {$deferredEmails->count()} email tertahan di antrean...");
        $success = 0;
        $failed = 0;

        foreach ($deferredEmails as $email) {
            $result = $relayService->retryEmail($email);
            if ($result['success']) {
                $success++;
                $this->line("<info>✓</info> Email #{$email->id} ({$email->subject}) berhasil dikirim.");
            } else {
                $failed++;
                $this->line("<comment>✗</comment> Email #{$email->id} gagal: " . ($result['message'] ?? 'Error tidak diketahui'));
            }
        }

        $this->info("Selesai memproses antrean: {$success} berhasil, {$failed} masih tertahan.");
        return 0;
    }
}
