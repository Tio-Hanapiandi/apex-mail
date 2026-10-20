@extends('layouts.app')

@section('title', 'Verifikasi 2 Langkah (Google Authenticator) - ApexMail')

@section('content')
<div class="flex-1 bg-[#f8fafc] overflow-y-auto p-3.5 sm:p-6 lg:p-8">
    <div class="max-w-4xl mx-auto space-y-6">

        <!-- Header Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200">
            <div>
                <div class="flex items-center space-x-2 text-xs text-slate-500 mb-1">
                    <a href="{{ route('webmail.index') }}" class="hover:text-blue-600 transition-colors">Kotak Surat</a>
                    <span>/</span>
                    <span class="text-slate-700 font-medium">Keamanan Akun</span>
                </div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight flex items-center space-x-2">
                    <i data-lucide="shield-check" class="w-6 h-6 text-blue-600"></i>
                    <span>Verifikasi 2 Langkah (Google Authenticator)</span>
                </h1>
                <p class="text-xs text-slate-500 mt-0.5">Tingkatkan keamanan akun email Anda dengan lapisan verifikasi ganda berbasis Time-based One-Time Password (TOTP).</p>
            </div>

            <!-- Status Badge -->
            <div>
                @if($user->two_factor_enabled)
                    <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 shadow-xs">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse mr-2"></span>
                        2FA Google Authenticator Aktif
                    </span>
                @else
                    <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                        <span class="w-2 h-2 rounded-full bg-slate-400 mr-2"></span>
                        2FA Belum Aktif
                    </span>
                @endif
            </div>
        </div>

        @if(!$user->two_factor_enabled)
            <!-- SETUP 2FA FORM (Belum Aktif) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Panduan & QR Code -->
                <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 mb-1">Langkah 1: Hubungkan Aplikasi Authenticator</h3>
                        <p class="text-xs text-slate-500 leading-relaxed">
                            Unduh aplikasi <strong>Google Authenticator</strong> (atau Microsoft Authenticator, Authy) di ponsel Anda melalui Google Play Store atau Apple App Store.
                        </p>
                    </div>

                    <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl flex flex-col sm:flex-row items-center gap-5">
                        <!-- QR Code Image -->
                        <div class="bg-white p-2.5 rounded-xl border border-slate-200 shadow-xs flex-shrink-0">
                            <img src="{{ $qrCodeUrl }}" alt="Scan QR Code Google Authenticator" class="w-40 h-40 object-contain rounded-lg">
                        </div>

                        <!-- Manual Secret Key -->
                        <div class="space-y-2 text-left w-full">
                            <div class="text-xs font-semibold text-slate-700">Pindai QR Code di samping atau masukkan kunci manual:</div>
                            <div class="p-2.5 bg-white border border-slate-200 rounded-xl font-mono text-xs text-blue-700 font-bold tracking-wider select-all break-all shadow-xs flex items-center justify-between">
                                <span id="secretKeyText">{{ $formattedSecret }}</span>
                                <button type="button" onclick="copySecretKey()" class="ml-2 px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded text-[10px] font-sans font-medium transition-colors flex items-center space-x-1" title="Salin Kunci">
                                    <i data-lucide="copy" class="w-3 h-3"></i>
                                    <span id="copyBtnText">Salin</span>
                                </button>
                            </div>
                            <p class="text-[11px] text-slate-500 leading-tight">
                                Tipe: Berbasis Waktu (TOTP)<br>
                                Akun: <span class="font-mono text-slate-700">{{ $user->email }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 pt-4 text-xs text-slate-500 space-y-2">
                        <div class="flex items-center space-x-2 text-slate-700 font-semibold">
                            <i data-lucide="info" class="w-4 h-4 text-blue-600"></i>
                            <span>Mengapa verifikasi 2 langkah penting?</span>
                        </div>
                        <p class="leading-relaxed">
                            Bahkan jika orang lain mengetahui kata sandi email Anda, mereka tidak akan dapat masuk tanpa kode fisik 6 digit yang hanya ada di ponsel Anda.
                        </p>
                    </div>
                </div>

                <!-- Form Konfirmasi Kode 6 Digit -->
                <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-5">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 mb-1">Langkah 2: Konfirmasi Kode</h3>
                        <p class="text-xs text-slate-500">
                            Masukkan 6 digit kode yang tampil di aplikasi Google Authenticator Anda untuk mengaktifkan fitur ini.
                        </p>
                    </div>

                    <form action="{{ route('security.two-factor.confirm') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Kode Verifikasi 6 Digit:</label>
                            <input type="text" name="code" maxlength="6" pattern="[0-9]{6}" required autofocus autocomplete="one-time-code"
                                   placeholder="Contoh: 123456"
                                   class="w-full text-center tracking-[0.4em] text-xl font-mono font-bold py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white text-slate-800 transition-all">
                        </div>

                        <button type="submit" 
                                class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-md shadow-blue-500/25 transition-all flex items-center justify-center space-x-1.5">
                            <i data-lucide="shield-check" class="w-4 h-4"></i>
                            <span>Aktifkan Verifikasi 2 Langkah</span>
                        </button>
                    </form>

                    <div class="p-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-[11px] leading-relaxed flex items-start space-x-2">
                        <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5"></i>
                        <span>Pastikan jam di perangkat smartphone Anda disinkronkan secara otomatis agar kode verifikasi tidak kedaluwarsa.</span>
                    </div>
                </div>
            </div>
        @else
            <!-- 2FA SUDAH AKTIF -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8 space-y-6">
                <div class="flex items-start space-x-4">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="shield-check" class="w-6 h-6"></i>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-base font-bold text-slate-900">Verifikasi 2 Langkah Google Authenticator Aktif</h3>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Akun Anda saat ini terlindungi dengan standar keamanan tingkat tinggi. Setiap kali Anda masuk ke ApexMail Web Client maupun Admin Console, sistem akan mewajibkan kode verifikasi 6 digit dari aplikasi Authenticator di ponsel Anda.
                        </p>
                        @if($user->two_factor_confirmed_at)
                            <div class="text-[11px] text-slate-400 pt-1 font-medium">
                                Diaktifkan pada: {{ $user->two_factor_confirmed_at->format('d M Y, H:i') }} WIB
                            </div>
                        @endif
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-6">
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-2 text-rose-600">Nonaktifkan Verifikasi 2 Langkah</h4>
                    <p class="text-xs text-slate-500 mb-4">
                        Jika Anda ingin mengganti perangkat ponsel atau tidak ingin lagi menggunakan Google Authenticator, Anda dapat menonaktifkannya di bawah ini dengan memasukkan kata sandi akun Anda.
                    </p>

                    <form id="disable2faForm" action="{{ route('security.two-factor.disable') }}" method="POST" class="max-w-md space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Konfirmasi Kata Sandi Akun:</label>
                            <input type="password" id="disable2faPassword" name="password" required placeholder="Masukkan kata sandi akun Anda"
                                   class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-rose-500 focus:bg-white text-slate-800 transition-all">
                        </div>

                        <button type="button" onclick="confirmDisable2fa()" 
                                class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-semibold shadow-xs transition-colors flex items-center space-x-1.5">
                            <i data-lucide="shield-off" class="w-3.5 h-3.5"></i>
                            <span>Nonaktifkan Verifikasi 2 Langkah</span>
                        </button>
                    </form>
                </div>
            </div>
        @endif

    </div>
</div>

@push('scripts')
<script>
    function copySecretKey() {
        const text = "{{ $secret ?? '' }}";
        if (!text) return;
        navigator.clipboard.writeText(text).then(() => {
            const btnText = document.getElementById('copyBtnText');
            btnText.textContent = 'Tersalin!';
            setTimeout(() => {
                btnText.textContent = 'Salin';
            }, 2500);
        });
    }

    function confirmDisable2fa() {
        const pass = document.getElementById('disable2faPassword').value;
        if (!pass) {
            showAppModal('warning', 'Perhatian', 'Silakan masukkan kata sandi akun Anda terlebih dahulu.');
            return;
        }
        showConfirmModal('Nonaktifkan 2FA?', 'Apakah Anda yakin ingin menonaktifkan Verifikasi 2 Langkah Google Authenticator pada akun ini?', () => {
            document.getElementById('disable2faForm').submit();
        }, 'Ya, Nonaktifkan 2FA', true);
    }
</script>
@endpush
@endsection
