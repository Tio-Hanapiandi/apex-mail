@extends('layouts.app')

@section('title', 'Pengaturan SMTP Relay & Direct MX - ApexMail Admin')

@section('content')
<div class="flex-1 bg-[#f8fafc] overflow-y-auto p-3.5 sm:p-6 lg:p-8">
    <div class="max-w-5xl mx-auto space-y-6">

        <!-- Header Admin Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200">
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Konfigurasi Pengiriman Outbound & SMTP Relay</h1>
                <p class="text-xs text-slate-500 mt-0.5">Kelola jalur pengiriman email keluar baik via Direct MX (DNS Port 25) maupun External SMTP Relay / Smart Host seperti pada Zimbra MTA.</p>
            </div>

            <!-- Sub Navigasi Admin -->
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
                <a href="{{ route('admin.relays') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-600 text-white shadow-sm shadow-blue-500/20">
                    SMTP Relay
                </a>
                <a href="{{ route('admin.inbound') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Email Masuk
                </a>
                <a href="{{ route('admin.queue') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Antrean & Log
                </a>
                <a href="{{ route('admin.migration') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Migrasi Data
                </a>
            </div>
        </div>

        <!-- Outbound Architecture Status Banner -->
        @if($activeRelay && $activeRelay->is_active)
            <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 sm:p-5 flex items-start space-x-3.5">
                <div class="w-9 h-9 rounded-xl bg-emerald-500 text-white flex items-center justify-center shrink-0 shadow-sm shadow-emerald-500/20">
                    <i data-lucide="share-2" class="w-5 h-5"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center space-x-2">
                        <span class="text-xs font-bold text-emerald-900 uppercase tracking-wider">Mode Pengiriman Aktif</span>
                        <span class="px-2 py-0.5 rounded-full bg-emerald-200 text-emerald-800 font-bold text-[10px]">External SMTP Relay / Smart Host</span>
                    </div>
                    <p class="text-xs text-emerald-800 mt-1 leading-relaxed">
                        Email keluar dialirkan melalui server relay eksternal <strong class="font-mono">{{ $activeRelay->host }}:{{ $activeRelay->port }}</strong>. Mode ini cocok jika server ISP memblokir port 25 atau domain membutuhkan upstream smart host terpusat.
                    </p>
                </div>
            </div>
        @else
            <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 sm:p-5 flex items-start space-x-3.5">
                <div class="w-9 h-9 rounded-xl bg-blue-600 text-white flex items-center justify-center shrink-0 shadow-sm shadow-blue-500/20">
                    <i data-lucide="globe" class="w-5 h-5"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center space-x-2">
                        <span class="text-xs font-bold text-blue-900 uppercase tracking-wider">Mode Pengiriman Aktif</span>
                        <span class="px-2 py-0.5 rounded-full bg-blue-200 text-blue-800 font-bold text-[10px]">Direct MX Delivery (DNS Port 25)</span>
                    </div>
                    <p class="text-xs text-blue-800 mt-1 leading-relaxed">
                        Email keluar dikirimkan <strong>langsung dari server ApexMail ke server tujuan</strong> (Google MX, dsb) secara otomatis via DNS MX lookup dan enkripsi STARTTLS pada port 25. Alamat pengirim tampil <strong>100% murni sesuai nama domain asli Anda</strong> tanpa perantara pihak ketiga.
                    </p>
                </div>
            </div>
        @endif

        <!-- Kartu Utama Konfigurasi SMTP Relay & Smart Host -->
        @if($activeRelay)
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Kolom Kiri: Form Konfigurasi Relay (Persis seperti Zimbra Relay MTA) -->
                <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-5">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div class="flex items-center space-x-2.5">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-xs">
                                <i data-lucide="send" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-800">{{ $activeRelay->name }}</h3>
                                <p class="text-[11px] text-slate-400">Konfigurasi Relay MTA / Smart Host Eksternal</p>
                            </div>
                        </div>

                        <!-- Presets Dropdown / Quick Buttons -->
                        <div class="flex items-center space-x-1.5 text-xs">
                            <span class="text-[11px] text-slate-400">Preset:</span>
                            <button type="button" onclick="setStandardTlsPreset()" class="px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-medium hover:bg-blue-100 text-[11px]">
                                TLS (587)
                            </button>
                            <button type="button" onclick="setSecureSslPreset()" class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-medium hover:bg-slate-200 text-[11px]">
                                SSL (465)
                            </button>
                            <button type="button" onclick="setMtaPort25Preset()" class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-medium hover:bg-slate-200 text-[11px]">
                                MTA (25)
                            </button>
                        </div>
                    </div>

                    <form action="{{ route('admin.relays.update', $activeRelay->id) }}" method="POST" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Profil Relay:</label>
                                <input type="text" id="relayName" name="name" value="{{ old('name', $activeRelay->name) }}" required
                                       class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white font-medium">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Cakupan Domain (Domain Scope):</label>
                                <select id="relayDomainId" name="domain_id" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                                    <option value="" {{ empty($activeRelay->domain_id) ? 'selected' : '' }}>Semua Domain (Global Smart Host)</option>
                                    @foreach($domains as $dom)
                                        <option value="{{ $dom->id }}" {{ $activeRelay->domain_id == $dom->id ? 'selected' : '' }}>
                                            Khusus Domain: {{ $dom->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="text-[10px] text-slate-400 mt-1">Pilih domain tertentu jika ingin relay hanya diterapkan pada domain tersebut.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Relay Host / Smart Host:</label>
                                <input type="text" id="relayHost" name="host" value="{{ old('host', $activeRelay->host) }}" required placeholder="mail.relay-example.com"
                                       class="w-full px-3 py-1.5 text-xs font-mono bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Port:</label>
                                <input type="number" id="relayPort" name="port" value="{{ old('port', $activeRelay->port) }}" required
                                       class="w-full px-3 py-1.5 text-xs font-mono bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Tipe Provider:</label>
                                <select id="relayProvider" name="provider" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                                    <option value="smarthost" {{ $activeRelay->provider === 'smarthost' ? 'selected' : '' }}>Smart Host Relay</option>
                                    <option value="standard" {{ in_array($activeRelay->provider, ['standard', 'brevo']) ? 'selected' : '' }}>Standard SMTP Relay</option>
                                    <option value="custom" {{ in_array($activeRelay->provider, ['custom', 'ses', 'mailgun']) ? 'selected' : '' }}>Custom External MTA</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Enkripsi:</label>
                                <select id="relayEncryption" name="encryption" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                                    <option value="tls" {{ strtolower($activeRelay->encryption) === 'tls' ? 'selected' : '' }}>STARTTLS (Direkomendasikan)</option>
                                    <option value="ssl" {{ strtolower($activeRelay->encryption) === 'ssl' ? 'selected' : '' }}>SSL/TLS Wrapper (465)</option>
                                    <option value="" {{ empty($activeRelay->encryption) ? 'selected' : '' }}>Tanpa Enkripsi (Plain)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Username / Auth ID:</label>
                                <input type="text" name="username" value="{{ old('username', $activeRelay->username) }}" placeholder="Username SMTP (opsional)"
                                       class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Password / SMTP Key:</label>
                            <input type="password" name="password" placeholder="Biarkan kosong jika tidak mengubah password atau tidak menggunakan autentikasi"
                                   class="w-full px-3 py-1.5 text-xs font-mono bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Default Sender Email (Fallback):</label>
                                <input type="email" name="sender_email" value="{{ old('sender_email', $activeRelay->sender_email) }}" placeholder="no-reply@domain.com"
                                       class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Default Sender Name (Fallback):</label>
                                <input type="text" name="sender_name" value="{{ old('sender_name', $activeRelay->sender_name) }}" placeholder="ApexMail System"
                                       class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                            </div>
                        </div>

                        <!-- Checkbox Kontrol Relay Eksternal -->
                        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                            <label class="flex items-start space-x-3 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" {{ $activeRelay->is_active ? 'checked' : '' }}
                                       class="rounded text-blue-600 focus:ring-blue-500 w-4 h-4 mt-0.5">
                                <div>
                                    <span class="text-xs font-bold text-slate-800">Aktifkan External SMTP Relay (Smart Host)</span>
                                    <p class="text-[11px] text-slate-500 leading-normal">
                                        Bila dicentang, seluruh email keluar diteruskan melalui host relay di atas. Bila <strong>tidak dicentang</strong>, sistem secara otomatis beralih ke mode <strong>Direct MX Delivery (Kirim Langsung)</strong>.
                                    </p>
                                </div>
                            </label>

                            <label class="flex items-start space-x-3 cursor-pointer pt-1 border-t border-slate-200/60">
                                <input type="checkbox" name="is_default" value="1" {{ $activeRelay->is_default ? 'checked' : '' }}
                                       class="rounded text-blue-600 focus:ring-blue-500 w-4 h-4 mt-0.5">
                                <div>
                                    <span class="text-xs font-semibold text-slate-700">Jadikan Relay Utama (Default)</span>
                                </div>
                            </label>
                        </div>

                        <div class="pt-2 flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold shadow-sm shadow-blue-500/25 transition-all flex items-center space-x-1.5">
                                <i data-lucide="save" class="w-3.5 h-3.5"></i>
                                <span>Simpan Konfigurasi Relay</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Kolom Kanan: Uji Koneksi & Live Diagnostics -->
                <div class="space-y-6">

                    <!-- Diagnostic 1: Uji Konektivitas Direct MX Port 25 -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                        <div class="flex items-center space-x-2">
                            <i data-lucide="globe" class="w-4 h-4 text-blue-600"></i>
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Uji Direct MX Port 25</h3>
                        </div>
                        <p class="text-xs text-slate-500 leading-relaxed">
                            Lakukan resolusi DNS MX dan uji jabat tangan port 25 STARTTLS langsung ke server penerima tujuan.
                        </p>

                        <form action="{{ route('admin.relays.test-direct') }}" method="POST" class="space-y-3">
                            @csrf
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Email Tujuan Uji Direct MX:</label>
                                <input type="email" name="test_email" required placeholder="tujuan@gmail.com"
                                       class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white font-medium">
                            </div>

                            <button type="submit" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold shadow-sm transition-all flex items-center justify-center space-x-1.5">
                                <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                                <span>Uji Resolusi & Port 25 MX</span>
                            </button>
                        </form>
                    </div>

                    <!-- Diagnostic 2: Uji Koneksi Relay Eksternal -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                        <div class="flex items-center space-x-2">
                            <i data-lucide="zap" class="w-4 h-4 text-amber-500"></i>
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Uji External SMTP Relay</h3>
                        </div>
                        <p class="text-xs text-slate-500 leading-relaxed">
                            Kirimkan email diagnostik melalui host relay eksternal di samping untuk menguji kredensial.
                        </p>

                        <form action="{{ route('admin.relays.test', $activeRelay->id) }}" method="POST" class="space-y-3">
                            @csrf
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Kirim Email Tes Ke:</label>
                                <input type="email" name="test_email" required placeholder="email.pribadi@gmail.com"
                                       class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white font-medium">
                            </div>

                            <button type="submit" class="w-full py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold shadow-sm transition-all flex items-center justify-center space-x-1.5">
                                <i data-lucide="activity" class="w-3.5 h-3.5 text-emerald-400"></i>
                                <span>Kirim Email Pengujian Relay</span>
                            </button>
                        </form>

                        <!-- Hasil Uji Terakhir Relay -->
                        @if($activeRelay->last_tested_at)
                            <div class="pt-3 border-t border-slate-100 space-y-1.5 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-400 text-[11px]">Status Uji Relay Terakhir:</span>
                                    @if($activeRelay->last_test_status === 'success')
                                        <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-bold text-[10px] border border-emerald-200">
                                            ✔ Sukses Terhubung
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 font-bold text-[10px] border border-rose-200">
                                            ✖ Gagal Terhubung
                                        </span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-slate-400">
                                    Waktu: {{ $activeRelay->last_tested_at->diffForHumans() }}
                                </div>
                                @if($activeRelay->last_test_message)
                                    <div class="p-2 rounded bg-slate-50 text-[11px] text-slate-600 font-mono break-words border border-slate-100">
                                        {{ $activeRelay->last_test_message }}
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    <!-- Panduan DNS SPF Singkat -->
                    <div class="bg-blue-50/60 rounded-2xl border border-blue-100 p-5 space-y-2 text-xs text-blue-900">
                        <div class="flex items-center space-x-2 font-bold text-blue-800">
                            <i data-lucide="info" class="w-4 h-4"></i>
                            <span>Catatan Deliverability & SPF</span>
                        </div>
                        <p class="text-[11px] text-blue-700 leading-relaxed">
                            Pastikan DNS SPF record di domain publik Anda telah mencantumkan IP server pengirim atau record MX domain:
                            <code class="block font-mono bg-white/80 p-1.5 rounded mt-1 text-[10px] text-slate-800 border border-blue-200">v=spf1 a mx ~all</code>
                        </p>
                    </div>

                </div>

            </div>
        @endif

    </div>
</div>

@push('scripts')
<script>
    function setStandardTlsPreset() {
        document.getElementById('relayName').value = 'Primary SMTP Relay';
        document.getElementById('relayProvider').value = 'standard';
        document.getElementById('relayPort').value = '587';
        document.getElementById('relayEncryption').value = 'tls';
    }

    function setSecureSslPreset() {
        document.getElementById('relayName').value = 'Secure SMTP Relay';
        document.getElementById('relayProvider').value = 'custom';
        document.getElementById('relayPort').value = '465';
        document.getElementById('relayEncryption').value = 'ssl';
    }

    function setMtaPort25Preset() {
        document.getElementById('relayName').value = 'Internal Smart Host MTA';
        document.getElementById('relayProvider').value = 'smarthost';
        document.getElementById('relayPort').value = '25';
        document.getElementById('relayEncryption').value = 'tls';
    }
</script>
@endpush
@endsection
