<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Perangkat Baru - ApexMail</title>
    
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

    <div class="w-full max-w-md space-y-5 my-8">
        <!-- Logo & Header -->
        <div class="text-center space-y-2">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-amber-500 to-blue-600 text-white flex items-center justify-center mx-auto shadow-lg shadow-amber-500/20">
                <i data-lucide="shield-alert" class="w-7 h-7"></i>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Verifikasi <span class="text-blue-600">Perangkat Baru</span></h1>
            <p class="text-xs text-slate-500">Pemeriksaan Keamanan & Otorisasi Akses Perangkat</p>
        </div>

        <!-- Card Form Challenge -->
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
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-800 border border-amber-200">
                    Perangkat Baru
                </span>
            </div>

            <!-- Email Notifikasi / OTP Notice -->
            <div class="p-3.5 bg-blue-50/60 border border-blue-100 rounded-2xl text-xs space-y-1">
                <div class="flex items-center space-x-1.5 text-blue-700 font-semibold">
                    <i data-lucide="mail" class="w-3.5 h-3.5"></i>
                    <span>Kode OTP telah dikirimkan ke:</span>
                </div>
                <div class="font-mono font-bold text-slate-800 pl-5">
                    {{ $maskedEmail }}
                </div>
                <div class="text-[10px] text-slate-500 pl-5">
                    {{ $isRecovery ? 'Alamat email pemulihan akun terdaftar' : 'Alamat email akun Anda' }}
                </div>
            </div>

            <!-- Rincian Perangkat & Lokasi Baru -->
            <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-3.5 space-y-2 text-[11px]">
                <div class="font-semibold text-slate-700 flex items-center justify-between pb-1 border-b border-slate-200">
                    <span class="flex items-center space-x-1.5">
                        <i data-lucide="info" class="w-3.5 h-3.5 text-slate-500"></i>
                        <span>Rincian Deteksi Login:</span>
                    </span>
                    <span class="text-slate-400 font-mono text-[10px]">{{ $formattedDate }}</span>
                </div>
                <div class="grid grid-cols-3 gap-1 pt-0.5">
                    <span class="text-slate-500 font-medium">IP Address:</span>
                    <span class="col-span-2 font-mono text-slate-800 font-semibold">{{ $ipAddress }}</span>
                </div>
                <div class="grid grid-cols-3 gap-1">
                    <span class="text-slate-500 font-medium">Perangkat:</span>
                    <span class="col-span-2 text-slate-700 break-all leading-tight text-[10px] font-mono">{{ $userAgent }}</span>
                </div>
            </div>

            <!-- OTP Form -->
            <form action="{{ route('device.verify') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-center text-xs font-semibold text-slate-700 mb-2">
                        Masukkan 6 Digit Kode OTP:
                    </label>
                    <div class="relative">
                        <input type="text" name="otp" maxlength="6" pattern="[0-9]{6}" required autofocus autocomplete="one-time-code"
                               placeholder="123456"
                               class="w-full text-center tracking-[0.45em] text-2xl font-mono font-bold py-3 bg-amber-50/40 border-2 border-amber-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 focus:bg-white text-slate-900 transition-all shadow-inner">
                    </div>
                    <p class="text-[11px] text-slate-400 text-center mt-2 flex items-center justify-center space-x-1">
                        <i data-lucide="clock" class="w-3.5 h-3.5 text-slate-400"></i>
                        <span>Kode OTP berlaku selama 15 menit</span>
                    </p>
                </div>

                <button type="submit" 
                        class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-md shadow-blue-500/25 transition-all flex items-center justify-center space-x-1.5">
                    <span>Verifikasi Perangkat & Masuk</span>
                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </button>
            </form>

            <!-- Actions: Resend & Cancel -->
            <div class="pt-3 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs">
                <form action="{{ route('device.resend') }}" method="POST" class="w-full sm:w-auto">
                    @csrf
                    <button type="submit" class="text-blue-600 hover:text-blue-700 font-semibold inline-flex items-center space-x-1">
                        <i data-lucide="rotate-cw" class="w-3 h-3"></i>
                        <span>Kirim Ulang Kode OTP</span>
                    </button>
                </form>

                <form action="{{ route('device.cancel') }}" method="POST" class="w-full sm:w-auto text-right">
                    @csrf
                    <button type="submit" class="text-slate-400 hover:text-slate-600 font-medium inline-flex items-center space-x-1">
                        <i data-lucide="arrow-left" class="w-3 h-3"></i>
                        <span>Batal & Kembali</span>
                    </button>
                </form>
            </div>
        </div>

        <div class="text-center text-[11px] text-slate-400">
            Perlindungan Akun Aman dengan Deteksi Perangkat & OTP ApexMail
        </div>
    </div>

    <!-- Unified Alert Modal Component (Direct child of body, prevents space-y margin leakage) -->
    @include('partials.alert-modal')

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
