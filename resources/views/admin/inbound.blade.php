@extends('layouts.app')

@section('title', 'Penerimaan Email Masuk (Inbound Webhook) - Admin Console')

@section('content')
<div class="flex-1 bg-[#f8fafc] overflow-y-auto p-3.5 sm:p-6 lg:p-8">
    <div class="max-w-6xl mx-auto space-y-6">

        <!-- Top Header Admin Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-blue-100 text-blue-800 border border-blue-200 uppercase">
                        Inbound Mail Engine
                    </span>
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Penerimaan Email Masuk (Inbound Webhook)</h1>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Konfigurasi Webhook Inbound Parsing dan simulasi penerimaan email ke kotak masuk pengguna.</p>
            </div>

            <!-- Sub Navigasi Menu Admin -->
            <div class="flex items-center space-x-1.5 overflow-x-auto no-scrollbar pb-1 max-w-full">
                <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Ringkasan Sistem
                </a>
                <a href="{{ route('admin.accounts') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Kelola Akun
                </a>
                <a href="{{ route('admin.aliases') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Alias & Grup
                </a>
                <a href="{{ route('admin.domains') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Domain & DNS
                </a>
                <a href="{{ route('admin.relays') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    SMTP Relay
                </a>
                <a href="{{ route('admin.inbound') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-600 text-white shadow-sm shadow-blue-500/20">
                    Email Masuk (Inbound)
                </a>
                <a href="{{ route('admin.queue') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Antrean & Log
                </a>
                <a href="{{ route('admin.migration') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Migrasi Data
                </a>
            </div>
        </div>



        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- ================= KOLOM KIRI (7 Kolom): Konfigurasi Webhook & DNS ================= -->
            <div class="lg:col-span-7 space-y-6">

                <!-- Kartu 1: Endpoint URL Webhook -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                                <i data-lucide="webhook" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <h2 class="text-sm font-bold text-slate-900">Endpoint Webhook Inbound Parsing</h2>
                                <p class="text-[11px] text-slate-500">Gunakan URL ini di pengaturan Webhook Mail Gateway atau Cloudflare Email Routing.</p>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            Aktif & Siap Menerima
                        </span>
                    </div>

                    <!-- Webhook URL Display -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-slate-700">Webhook Target URL (POST):</label>
                        <div class="flex items-center space-x-2">
                            <input type="text" id="webhookUrlInput" readonly value="{{ $webhookUrl }}" 
                                   class="flex-1 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-mono text-xs text-slate-800 focus:outline-none select-all">
                            <button type="button" onclick="copyWebhookUrl()" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl border border-slate-200 transition-colors flex items-center space-x-1.5">
                                <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                                <span id="copyBtnText">Salin</span>
                            </button>
                        </div>
                        <p class="text-[11px] text-slate-400">Endpoint publik ini menerima payload JSON MIME hasil parsing email dari gateway inbound dan otomatis mendistribusikannya ke kotak masuk akun lokal.</p>
                    </div>

                    <!-- Security Token -->
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 flex items-center justify-between text-xs">
                        <div class="flex items-center space-x-2">
                            <i data-lucide="key" class="w-4 h-4 text-slate-400"></i>
                            <span class="text-slate-600 font-medium">Security Secret Token:</span>
                        </div>
                        <code class="font-mono text-slate-800 bg-white px-2 py-0.5 rounded border border-slate-200 text-[11px] font-semibold">
                            {{ $webhookToken ?: 'Tidak diproteksi token (Terbuka)' }}
                        </code>
                    </div>
                </div>

                <!-- Kartu 2: Panduan Konfigurasi Mail Gateway & DNS Inbound -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
                    <div class="flex items-center space-x-2 pb-3 border-b border-slate-100">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                            <i data-lucide="book-open" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">Cara Menghubungkan Domain & Inbound Gateway</h2>
                            <p class="text-[11px] text-slate-500">Langkah menghubungkan domain <span class="font-semibold text-blue-600">azkario.biz.id</span> agar email masuk diteruskan ke ApexMail.</p>
                        </div>
                    </div>

                    <div class="space-y-3.5 text-xs text-slate-700">
                        <!-- Langkah 1 -->
                        <div class="flex items-start space-x-3">
                            <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center text-[10px] flex-shrink-0 mt-0.5">1</span>
                            <div>
                                <p class="font-semibold text-slate-800">Arahkan MX Record Domain ke Mail Gateway</p>
                                <p class="text-slate-500 mt-0.5">Di DNS hosting domain Anda (cPanel/Cloudflare), arahkan MX record domain Anda ke server penerima gateway email:</p>
                                <div class="mt-2 p-2.5 bg-slate-900 text-slate-100 rounded-lg font-mono text-[11px] space-y-1">
                                    <div>Type: <span class="text-emerald-400">MX</span></div>
                                    <div>Host / Name: <span class="text-emerald-400">@</span> (atau <span class="text-emerald-400">azkario.biz.id</span>)</div>
                                    <div>Priority: <span class="text-amber-400">10</span></div>
                                    <div>Value / Target: <span class="text-blue-300">inbound-smtp.mailgateway.net</span> (atau hostname gateway Anda)</div>
                                </div>
                            </div>
                        </div>

                        <!-- Langkah 2 -->
                        <div class="flex items-start space-x-3">
                            <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center text-[10px] flex-shrink-0 mt-0.5">2</span>
                            <div>
                                <p class="font-semibold text-slate-800">Aktifkan Inbound Webhook di Provider Gateway</p>
                                <p class="text-slate-500 mt-0.5">Buka dashboard provider email gateway Anda &rarr; Masuk ke menu <strong>Inbound Webhook / Parsing Settings</strong>.</p>
                                <p class="text-slate-500 mt-1">Tambahkan domain Anda (<code class="bg-slate-100 px-1 py-0.5 rounded font-mono">azkario.biz.id</code>) dan masukkan URL Webhook di atas pada kolom <strong>Target Webhook URL</strong>.</p>
                            </div>
                        </div>

                        <!-- Langkah 3 -->
                        <div class="flex items-start space-x-3">
                            <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center text-[10px] flex-shrink-0 mt-0.5">3</span>
                            <div>
                                <p class="font-semibold text-slate-800">Otomatisasi Distribusi ke Akun Lokal</p>
                                <p class="text-slate-500 mt-0.5">Setiap email masuk yang dikirim ke <code class="bg-slate-100 px-1 py-0.5 rounded font-mono">user@azkario.biz.id</code> akan otomatis diterima gateway, diubah menjadi JSON, dikirim ke ApexMail, dan masuk langsung ke folder <strong>Kotak Masuk</strong> penerima.</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ================= KOLOM KANAN (5 Kolom): Simulator Email Masuk ================= -->
            <div class="lg:col-span-5 space-y-6">

                <!-- Kartu 3: Form Simulasi Email Masuk -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
                    <div class="flex items-center space-x-2 pb-3 border-b border-slate-100">
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <i data-lucide="play-circle" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">Simulasi Email Masuk (Live Test)</h2>
                            <p class="text-[11px] text-slate-500">Uji coba penerimaan email seolah-olah dikirim dari Gmail eksternal.</p>
                        </div>
                    </div>

                    <form action="{{ route('admin.inbound.simulate') }}" method="POST" enctype="multipart/form-data" class="space-y-3.5">
                        @csrf

                        <!-- Dari (Pengirim Luar) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-700 mb-1">Pengirim Luar (Email):</label>
                                <input type="email" name="from_email" value="client.external@gmail.com" required
                                       class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-700 mb-1">Nama Pengirim:</label>
                                <input type="text" name="from_name" value="Bapak Hendra (Klien)"
                                       class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500">
                            </div>
                        </div>

                        <!-- Kepada (Akun di ApexMail) -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-700 mb-1">Kirimkan Ke (Akun Mailbox ApexMail):</label>
                            <select name="to_email" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500 font-medium">
                                @foreach($accounts as $acc)
                                    <option value="{{ $acc->email }}">{{ $acc->display_name }} ({{ $acc->email }})</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Subjek -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-700 mb-1">Subjek Surat:</label>
                            <input type="text" name="subject" value="Tanggapan Penawaran & Konfirmasi Jadwal Meeting" required
                                   class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        </div>

                        <!-- Isi Surat HTML -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-700 mb-1">Isi Pesan (HTML / Teks):</label>
                            <textarea name="body_html" rows="4" required
                                      class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500 font-sans leading-relaxed"><p>Halo Tim,</p><p>Terima kasih atas email informasinya. Kami telah mereview dokumen penawaran dan setuju untuk melanjutkan ke tahap diskusi teknis.</p><p>Mohon konfirmasikan waktu pertemuan yang tersedia minggu ini.</p><p>Salam hangat,<br><strong>Hendra Wijaya</strong></p></textarea>
                        </div>

                        <!-- Lampiran Test (Opsional) -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-700 mb-1">Lampiran Berkas Uji (Opsional):</label>
                            <input type="file" name="attachments[]" multiple
                                   class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        </div>

                        <!-- Tombol Aksi -->
                        <button type="submit" class="w-full py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs rounded-xl shadow-sm shadow-emerald-500/20 transition-all flex items-center justify-center space-x-2">
                            <i data-lucide="send" class="w-4 h-4"></i>
                            <span>Kirimkan Simulasi Email Masuk Sekarang</span>
                        </button>
                    </form>
                </div>

            </div>

        </div>

        <!-- ================= BAGIAN BAWAH: Riwayat Email Masuk Terakhir ================= -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center">
                        <i data-lucide="inbox" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Riwayat Email Masuk Terbaru (Inbound Logs)</h2>
                        <p class="text-[11px] text-slate-500">Daftar email yang baru saja diterima di folder Kotak Masuk seluruh pengguna.</p>
                    </div>
                </div>
                <a href="{{ route('webmail.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700 flex items-center space-x-1">
                    <span>Buka Webmail</span>
                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                </a>
            </div>

            @if($recentInboundEmails->isEmpty())
                <div class="text-center py-8 text-slate-400 text-xs">
                    <i data-lucide="mail-open" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                    <p>Belum ada email masuk yang tercatat di sistem.</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Gunakan form simulasi di atas untuk mencoba menerima email pertama.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                <th class="pb-2.5">Waktu Diterima</th>
                                <th class="pb-2.5">Dari (Pengirim)</th>
                                <th class="pb-2.5">Kepada (Penerima Lokal)</th>
                                <th class="pb-2.5">Subjek</th>
                                <th class="pb-2.5 text-center">Lampiran</th>
                                <th class="pb-2.5 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach($recentInboundEmails as $mail)
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-3 font-mono text-[11px] text-slate-500 whitespace-nowrap">
                                        {{ $mail->received_at ? $mail->received_at->format('d M Y, H:i') : '-' }}
                                    </td>
                                    <td class="py-3 whitespace-nowrap">
                                        <div class="font-semibold text-slate-800">{{ $mail->from_name ?: $mail->from_email }}</div>
                                        <div class="text-[11px] text-slate-400 font-mono">{{ $mail->from_email }}</div>
                                    </td>
                                    <td class="py-3 whitespace-nowrap">
                                        <span class="px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 font-mono text-[11px]">
                                            {{ $mail->account->email ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <div class="font-medium text-slate-800 truncate max-w-xs">{{ $mail->subject ?: '(Tanpa Subjek)' }}</div>
                                        <div class="text-[11px] text-slate-400 truncate max-w-xs">{{ $mail->snippet }}</div>
                                    </td>
                                    <td class="py-3 text-center">
                                        @if($mail->has_attachments)
                                            <span class="inline-flex items-center text-slate-600 bg-slate-100 px-2 py-0.5 rounded text-[10px] font-medium">
                                                <i data-lucide="paperclip" class="w-3 h-3 mr-1 text-slate-500"></i> Ada Berkas
                                            </span>
                                        @else
                                            <span class="text-slate-300">-</span>
                                        @endif
                                    </td>
                                    <td class="py-3 text-right">
                                        @if($mail->is_read)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600">Sudah Dibaca</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-100 text-blue-700">Baru (Belum Dibaca)</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>
</div>

<script>
    function copyWebhookUrl() {
        const input = document.getElementById('webhookUrlInput');
        input.select();
        input.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(input.value).then(() => {
            const btnText = document.getElementById('copyBtnText');
            btnText.innerText = 'Disalin!';
            setTimeout(() => {
                btnText.innerText = 'Salin';
            }, 2500);
        });
    }
</script>
@endsection
