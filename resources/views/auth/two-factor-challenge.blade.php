<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi 2 Langkah - ApexMail</title>
    
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
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center mx-auto shadow-lg shadow-blue-500/25">
                <i data-lucide="shield-check" class="w-7 h-7"></i>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Verifikasi <span class="text-blue-600">2 Langkah</span></h1>
            <p class="text-xs text-slate-500">Google Authenticator Two-Factor Authentication</p>
        </div>

        <!-- Card Form 2FA Challenge -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-xl shadow-slate-200/50 p-6 sm:p-8 space-y-5">
            <!-- Info Akun yang sedang diverifikasi -->
            <div class="p-3.5 bg-slate-50 border border-slate-200/80 rounded-2xl flex items-center space-x-3">
                <div class="w-9 h-9 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-xs flex-shrink-0">
                    {{ strtoupper(substr($account->username, 0, 2)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-xs font-bold text-slate-800 truncate">{{ $account->display_name }}</div>
                    <div class="text-[11px] text-slate-500 font-mono truncate">{{ $account->email }}</div>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-800">
                    2FA Aktif
                </span>
            </div>

            <p class="text-xs text-slate-600 text-center leading-relaxed">
                Buka aplikasi <strong>Google Authenticator</strong> pada smartphone Anda dan masukkan <strong>6 digit kode verifikasi</strong> yang ditampilkan:
            </p>

            <form action="{{ route('two-factor.verify') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-center text-xs font-semibold text-slate-700 mb-2">Kode Keamanan 6 Digit:</label>
                    <div class="relative">
                        <input type="text" name="code" maxlength="6" pattern="[0-9]{6}" required autofocus autocomplete="one-time-code"
                               placeholder="123456"
                               class="w-full text-center tracking-[0.45em] text-2xl font-mono font-bold py-3 bg-blue-50/40 border-2 border-blue-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white text-slate-900 transition-all shadow-inner">
                    </div>
                    <p class="text-[11px] text-slate-400 text-center mt-2 flex items-center justify-center space-x-1">
                        <i data-lucide="clock" class="w-3.5 h-3.5 text-slate-400"></i>
                        <span>Kode berganti otomatis setiap 30 detik</span>
                    </p>
                </div>

                <button type="submit" 
                        class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-md shadow-blue-500/25 transition-all flex items-center justify-center space-x-1.5">
                    <span>Verifikasi & Masuk Akun</span>
                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </button>
            </form>

            <div class="pt-2 border-t border-slate-100 text-center">
                <form action="{{ route('two-factor.cancel') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="text-xs text-slate-500 hover:text-slate-800 font-medium inline-flex items-center space-x-1">
                        <i data-lucide="arrow-left" class="w-3 h-3"></i>
                        <span>Batal dan gunakan akun lain</span>
                    </button>
                </form>
            </div>
        </div>

        <div class="text-center text-[11px] text-slate-400">
            Perlindungan Akun Aman dengan Standar Enkripsi TOTP RFC 6238
        </div>
    </div>

    <!-- Unified Alert Modal Component (Direct child of body, prevents space-y-6 margin leakage) -->
    @include('partials.alert-modal')

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
