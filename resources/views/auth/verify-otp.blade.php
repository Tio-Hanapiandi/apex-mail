<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi OTP & Reset Sandi - ApexMail</title>
    
    @include('partials.seo-head')
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-full flex items-center justify-center p-4 bg-[#f8fafc] text-slate-800 font-sans">

    <div class="w-full max-w-md space-y-6">
        <!-- Logo & Header -->
        <div class="text-center space-y-2">
            <div class="w-12 h-12 rounded-2xl bg-blue-600 text-white flex items-center justify-center mx-auto shadow-md shadow-blue-500/30">
                <i data-lucide="shield-check" class="w-6 h-6"></i>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Verifikasi <span class="text-blue-600">Kode OTP</span></h1>
            <p class="text-xs text-slate-500">
                Kode OTP telah dikirimkan ke email pemulihan: <br>
                <strong class="font-mono text-slate-700 bg-slate-100 px-2 py-0.5 rounded text-[11px]">{{ $maskedRecovery ?? 'email pemulihan Anda' }}</strong>
            </p>
        </div>

        <!-- Card Form Verify OTP & New Password -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-xl shadow-slate-200/50 p-6 sm:p-8 space-y-5">
            <form action="{{ route('password.reset-otp') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="email" value="{{ $account->email }}">

                <!-- Input OTP 6-Digit -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-semibold text-slate-700">Kode OTP 6 Digit:</label>
                        <span class="text-[11px] text-amber-600 font-medium">Berlaku 15 menit</span>
                    </div>
                    <div class="relative">
                        <input type="text" name="otp" maxlength="6" pattern="[0-9]{6}" required autofocus autocomplete="one-time-code"
                               placeholder="123456"
                               class="w-full text-center tracking-[0.4em] text-lg font-mono font-bold py-2.5 bg-blue-50/50 border border-blue-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white text-blue-700 transition-all">
                    </div>
                </div>

                <!-- Input Kata Sandi Baru -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Kata Sandi Baru:</label>
                    <div class="relative">
                        <i data-lucide="lock" class="w-4 h-4 absolute left-3 top-3 text-slate-400"></i>
                        <input type="password" name="password" required minlength="{{ $passwordPolicy['min_length'] ?? 8 }}" autocomplete="new-password"
                               placeholder="Minimal {{ $passwordPolicy['min_length'] ?? 8 }} karakter (huruf besar & simbol)"
                               class="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white text-slate-800 transition-all font-mono">
                    </div>
                    <!-- Badges Ketentuan Kata Sandi -->
                    <div class="flex flex-wrap gap-1 mt-1.5 text-[10px]">
                        <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-medium">Min. {{ $passwordPolicy['min_length'] ?? 8 }} Karakter</span>
                        <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-medium">Huruf Besar (A-Z)</span>
                        <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-medium">Simbol Unik (!@#$)</span>
                        <span class="px-1.5 py-0.5 rounded bg-rose-50 text-rose-600 font-medium border border-rose-100">Bukan Sandi Sebelumnya</span>
                    </div>
                </div>

                <!-- Konfirmasi Kata Sandi Baru -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Ulangi Kata Sandi Baru:</label>
                    <div class="relative">
                        <i data-lucide="check-check" class="w-4 h-4 absolute left-3 top-3 text-slate-400"></i>
                        <input type="password" name="password_confirmation" required minlength="{{ $passwordPolicy['min_length'] ?? 8 }}" autocomplete="new-password"
                               placeholder="Ketik ulang kata sandi baru"
                               class="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white text-slate-800 transition-all font-mono">
                    </div>
                </div>

                <button type="submit" 
                        class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-md shadow-blue-500/25 transition-all flex items-center justify-center space-x-1.5">
                    <span>Verifikasi & Simpan Sandi Baru</span>
                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </button>
            </form>

            <!-- Kirim Ulang OTP -->
            <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span>Tidak menerima kode?</span>
                <form action="{{ route('password.send-otp') }}" method="POST" class="inline">
                    @csrf
                    <input type="hidden" name="email" value="{{ $account->email }}">
                    <button type="submit" class="text-blue-600 hover:underline font-semibold flex items-center space-x-1">
                        <i data-lucide="rotate-ccw" class="w-3 h-3"></i>
                        <span>Kirim Ulang Kode</span>
                    </button>
                </form>
            </div>
        </div>

        <div class="text-center pt-2">
            <a href="{{ route('login') }}" 
               class="inline-flex items-center space-x-1.5 text-xs text-slate-500 hover:text-slate-800 font-medium transition-colors">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                <span>Kembali ke Halaman Masuk</span>
            </a>
        </div>
    </div>

    <!-- Unified Alert Modal Component (Direct child of body, prevents space-y-6 margin leakage) -->
    @include('partials.alert-modal')

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
