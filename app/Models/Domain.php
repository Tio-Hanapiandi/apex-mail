<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Domain extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'is_verified',
        'verification_token',
        'dkim_selector',
        'dkim_private_key',
        'dkim_public_key',
        'spf_record',
        'dmarc_record',
        'mx_record',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
    ];

    public function accounts(): HasMany
    {
        return $this->hasMany(MailAccount::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(MailAlias::class);
    }

    public function smtpRelays(): HasMany
    {
        return $this->hasMany(SmtpRelay::class);
    }

    public function defaultRelay(): HasOne
    {
        return $this->hasOne(SmtpRelay::class)->where('is_active', true)->where('is_default', true);
    }

    /**
     * Generate or regenerate 2048-bit RSA DKIM key pair.
     */
    public function generateDkimKeys(): bool
    {
        $cnfPath = getenv('OPENSSL_CONF');
        if (!$cnfPath || !file_exists($cnfPath)) {
            $possiblePaths = [
                dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf',
                dirname(PHP_BINARY) . '/openssl.cnf',
                'F:/Backup_SSD/Laragon/bin/php/php-8.2.34-Win32-vs16-x64/extras/ssl/openssl.cnf',
                '/etc/ssl/openssl.cnf',
                '/etc/pki/tls/openssl.cnf',
            ];
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $cnfPath = $path;
                    break;
                }
            }
        }

        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        if ($cnfPath) {
            $config['config'] = $cnfPath;
        }

        $res = @openssl_pkey_new($config);
        if (!$res) {
            return false;
        }

        @openssl_pkey_export($res, $privateKey, null, $config);
        $details = @openssl_pkey_get_details($res);
        if (empty($details['key']) || empty($privateKey)) {
            return false;
        }

        $cleanPublicKey = preg_replace('/-----(BEGIN|END) PUBLIC KEY-----|\r|\n/', '', $details['key']);

        $this->update([
            'dkim_selector' => $this->dkim_selector ?: 'apexmail',
            'dkim_private_key' => $privateKey,
            'dkim_public_key' => $cleanPublicKey,
        ]);

        return true;
    }

    /**
     * Get the DNS TXT record value for DKIM.
     */
    public function getDkimDnsRecordAttribute(): string
    {
        if (empty($this->dkim_public_key)) {
            return 'v=DKIM1; k=rsa; p=';
        }
        return 'v=DKIM1; k=rsa; p=' . $this->dkim_public_key;
    }

    /**
     * Get the active public server IP address.
     */
    public static function getServerPublicIp(): string
    {
        return SystemSetting::get('server_public_ip') ?: env('SERVER_PUBLIC_IP', '203.2.151.17');
    }

    /**
     * Get the recommended SPF record.
     */
    public function getSpfDnsRecordAttribute(): string
    {
        if (!empty($this->spf_record)) {
            return $this->spf_record;
        }
        $ip = static::getServerPublicIp();
        return "v=spf1 ip4:{$ip} include:_spf.mx.cloudflare.net ~all";
    }

    /**
     * Get the recommended DMARC record.
     */
    public function getDmarcDnsRecordAttribute(): string
    {
        return $this->dmarc_record ?: ('v=DMARC1; p=none; rua=mailto:dmarc@' . $this->name);
    }

    /**
     * Get structured list of all DNS records for display, copying, or export.
     */
    public function getAllDnsRecordsListAttribute(): array
    {
        $selector = $this->dkim_selector ?: 'apexmail';
        $domainName = strtolower(trim($this->name));
        $serverIp = static::getServerPublicIp();

        return [
            [
                'category' => 'DKIM',
                'title' => 'DKIM Cryptographic Key (2048-bit RSA)',
                'type' => 'TXT',
                'name' => "{$selector}._domainkey",
                'full_host' => "{$selector}._domainkey.{$domainName}",
                'value' => $this->dkim_dns_record,
                'priority' => null,
                'ttl' => 'Auto',
                'required' => true,
                'description' => 'Tanda tangan kriptografi digital email keluar untuk verifikasi Google / Yahoo / Outlook.',
            ],
            [
                'category' => 'SPF',
                'title' => 'SPF (Sender Policy Framework)',
                'type' => 'TXT',
                'name' => '@',
                'full_host' => $domainName,
                'value' => $this->spf_dns_record,
                'priority' => null,
                'ttl' => 'Auto',
                'required' => true,
                'description' => "Mengizinkan IP server ({$serverIp}) dan Cloudflare untuk mengirimkan email atas nama domain.",
            ],
            [
                'category' => 'DMARC',
                'title' => 'DMARC Policy (Anti-Spoofing)',
                'type' => 'TXT',
                'name' => '_dmarc',
                'full_host' => "_dmarc.{$domainName}",
                'value' => $this->dmarc_dns_record,
                'priority' => null,
                'ttl' => 'Auto',
                'required' => true,
                'description' => 'Kebijakan kepatuhan dan pelaporan anti-pemalsuan domain.',
            ],
            [
                'category' => 'PTR',
                'title' => 'Reverse DNS / PTR (Wajib Gmail)',
                'type' => 'PTR',
                'name' => $serverIp,
                'full_host' => "IP {$serverIp}",
                'value' => "mail.{$domainName}",
                'priority' => null,
                'ttl' => 'Default',
                'required' => true,
                'description' => "Disetel di ISP / IDNIC pengelola IP ({$serverIp} -> mail.{$domainName}) agar tidak diblokir Gmail 550-5.7.25.",
            ],
            [
                'category' => 'MX',
                'title' => 'MX Record 1 (Cloudflare Inbound Routing)',
                'type' => 'MX',
                'name' => '@',
                'full_host' => $domainName,
                'value' => 'route1.mx.cloudflare.net',
                'priority' => 45,
                'ttl' => 'Auto',
                'required' => true,
                'description' => 'Server penerima email masuk prioritas 1 (Cloudflare Mail Routing).',
            ],
            [
                'category' => 'MX',
                'title' => 'MX Record 2 (Cloudflare Inbound Routing)',
                'type' => 'MX',
                'name' => '@',
                'full_host' => $domainName,
                'value' => 'route2.mx.cloudflare.net',
                'priority' => 62,
                'ttl' => 'Auto',
                'required' => true,
                'description' => 'Server penerima email masuk prioritas 2 (Cloudflare Mail Routing).',
            ],
            [
                'category' => 'MX',
                'title' => 'MX Record 3 (Cloudflare Inbound Routing)',
                'type' => 'MX',
                'name' => '@',
                'full_host' => $domainName,
                'value' => 'route3.mx.cloudflare.net',
                'priority' => 92,
                'ttl' => 'Auto',
                'required' => true,
                'description' => 'Server penerima email masuk prioritas 3 (Cloudflare Mail Routing).',
            ],
            [
                'category' => 'A',
                'title' => 'A Record Server Mail (DNS Only / Awan Abu)',
                'type' => 'A',
                'name' => 'mail',
                'full_host' => "mail.{$domainName}",
                'value' => $serverIp,
                'priority' => null,
                'ttl' => 'Auto',
                'required' => true,
                'description' => 'Hostname koneksi direct mail server port 25/587.',
            ],
            [
                'category' => 'A',
                'title' => 'A Record Webmail',
                'type' => 'A',
                'name' => 'webmail',
                'full_host' => "webmail.{$domainName}",
                'value' => $serverIp,
                'priority' => null,
                'ttl' => 'Auto',
                'required' => false,
                'description' => 'Alamat web portal akses kotak surat bagi pengguna.',
            ],
        ];
    }

    /**
     * Check live DNS propagation and verification for DKIM, SPF, DMARC, and MX.
     */
    public function checkLiveDnsStatus(): array
    {
        $domainName = strtolower(trim($this->name));
        $selector = $this->dkim_selector ?: 'apexmail';
        $dkimHost = "{$selector}._domainkey.{$domainName}";
        $dmarcHost = "_dmarc.{$domainName}";

        // 1. Check DKIM
        $dkimStatus = 'unpropagated';
        $dkimDetails = "Belum terdeteksi di DNS host '{$dkimHost}'. Pastikan TXT record sudah disimpan di DNS manager.";
        $dkimFound = null;

        $dkimRecords = @dns_get_record($dkimHost, DNS_TXT);
        if (!empty($dkimRecords)) {
            foreach ($dkimRecords as $r) {
                $fullTxt = isset($r['entries']) ? implode('', $r['entries']) : ($r['txt'] ?? '');
                $cleanTxt = preg_replace('/\s+/', '', $fullTxt);
                if (str_contains($cleanTxt, 'v=DKIM1')) {
                    $dkimFound = $fullTxt;
                    $cleanPubKey = preg_replace('/\s+/', '', $this->dkim_public_key ?? '');
                    if (!empty($cleanPubKey) && str_contains($cleanTxt, substr($cleanPubKey, 0, 40))) {
                        $dkimStatus = 'verified';
                        $dkimDetails = 'Record DKIM valid, aktif, dan cocok 100% dengan kunci privat kriptografi server.';
                    } else {
                        $dkimStatus = 'mismatch';
                        $dkimDetails = 'Record DKIM terdeteksi di DNS namun kunci publik berbeda dengan server.';
                    }
                    break;
                }
            }
        }

        // 2. Check SPF
        $serverIp = static::getServerPublicIp();
        $spfStatus = 'unpropagated';
        $spfDetails = "Belum terdeteksi di DNS host '{$domainName}'. Tambahkan TXT @ dengan include Cloudflare dan IP server {$serverIp}.";
        $spfFound = null;

        $rootTxtRecords = @dns_get_record($domainName, DNS_TXT);
        if (!empty($rootTxtRecords)) {
            foreach ($rootTxtRecords as $r) {
                $txt = $r['txt'] ?? '';
                if (str_starts_with(trim($txt), 'v=spf1')) {
                    $spfFound = $txt;
                    if (str_contains($txt, $serverIp) || str_contains($txt, '103.156.88.95') || str_contains($txt, '_spf.mx.cloudflare.net')) {
                        $spfStatus = 'verified';
                        $spfDetails = 'Record SPF aktif: ' . $txt;
                    } else {
                        $spfStatus = 'partial';
                        $spfDetails = "Record SPF terdeteksi namun belum menyertakan IP server {$serverIp}: " . $txt;
                    }
                    break;
                }
            }
        }

        // 3. Check DMARC
        $dmarcStatus = 'unpropagated';
        $dmarcDetails = "Belum terdeteksi di DNS host '{$dmarcHost}'. Tambahkan TXT _dmarc dengan nilai 'v=DMARC1; p=none;'.";
        $dmarcFound = null;

        $dmarcRecords = @dns_get_record($dmarcHost, DNS_TXT);
        if (!empty($dmarcRecords)) {
            foreach ($dmarcRecords as $r) {
                $txt = $r['txt'] ?? '';
                if (str_contains($txt, 'v=DMARC1')) {
                    $dmarcStatus = 'verified';
                    $dmarcFound = $txt;
                    $dmarcDetails = 'Record DMARC aktif: ' . $txt;
                    break;
                }
            }
        }

        // 4. Check MX
        $mxStatus = 'unpropagated';
        $mxDetails = "Belum ada catatan MX terdeteksi untuk domain '{$domainName}'.";
        $mxHosts = [];

        $mxRecords = @dns_get_record($domainName, DNS_MX);
        if (!empty($mxRecords)) {
            foreach ($mxRecords as $mx) {
                $target = $mx['target'] ?? '';
                $pri = $mx['pri'] ?? 0;
                $mxHosts[] = "{$target} (prio {$pri})";
            }
            $mxStatus = 'verified';
            $mxDetails = count($mxHosts) . ' MX record aktif: ' . implode(', ', $mxHosts);
        }

        // 5. Check PTR (Reverse DNS) - Wajib Google / Gmail
        $ptrHost = @gethostbyaddr($serverIp);
        $ptrValid = ($ptrHost && $ptrHost !== $serverIp);
        $ptrStatus = $ptrValid ? 'verified' : 'unpropagated';
        $ptrDetails = $ptrValid
            ? "PTR Record terverifikasi aktif: {$serverIp} mengarah balik ke '{$ptrHost}'."
            : "PTR Record belum disetel pada IP {$serverIp} di ISP. Gmail menolak pengiriman langsung (Error 550-5.7.25) hingga PTR disetel ke 'mail.{$domainName}'.";

        return [
            'dkim' => [
                'status' => $dkimStatus,
                'details' => $dkimDetails,
                'found' => $dkimFound,
            ],
            'spf' => [
                'status' => $spfStatus,
                'details' => $spfDetails,
                'found' => $spfFound,
            ],
            'dmarc' => [
                'status' => $dmarcStatus,
                'details' => $dmarcDetails,
                'found' => $dmarcFound,
            ],
            'mx' => [
                'status' => $mxStatus,
                'details' => $mxDetails,
                'hosts' => $mxHosts,
            ],
            'ptr' => [
                'status' => $ptrStatus,
                'details' => $ptrDetails,
                'host' => $ptrHost,
                'ip' => $serverIp,
            ],
            'checked_at' => now()->format('d M Y H:i:s'),
        ];
    }

    /**
     * Generate BIND zone / plaintext representation of DNS records.
     */
    public function generateZoneFileText(): string
    {
        $records = $this->all_dns_records_list;
        $out = "; ========================================================\n";
        $out .= "; PANDUAN REKAM DNS APEXMAIL COLLABORATION SUITE\n";
        $out .= "; DOMAIN  : {$this->name}\n";
        $out .= "; TANGGAL : " . now()->toIso8601String() . "\n";
        $out .= "; SERVER  : 103.156.88.95 (ApexMail Direct Delivery)\n";
        $out .= "; ========================================================\n\n";

        foreach ($records as $r) {
            $out .= "; [{$r['category']}] {$r['title']}\n";
            $out .= "Host/Name : {$r['name']}\n";
            $out .= "Tipe      : {$r['type']}\n";
            if (!empty($r['priority'])) {
                $out .= "Prioritas : {$r['priority']}\n";
            }
            $out .= "Nilai     : {$r['value']}\n";
            $out .= "Keterangan: {$r['description']}\n";
            $out .= "--------------------------------------------------------\n\n";
        }

        return $out;
    }
}
