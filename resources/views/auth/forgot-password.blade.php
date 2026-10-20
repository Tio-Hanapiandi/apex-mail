<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Kata Sandi - ApexMail</title>
    
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
                <i data-lucide="key-round" class="w-6 h-6"></i>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Pemulihan <span class="text-blue-600">Kata Sandi</span></h1>
            <p class="text-xs text-slate-500">Masukkan alamat email akun ApexMail Anda untuk menerima kode OTP verifikasi.</p>
        </div>

        <!-- Card Form Request OTP -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-xl shadow-slate-200/50 p-6 sm:p-8 space-y-5">
            <form action="{{ route('password.send-otp') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Alamat Email ApexMail atau Email Pemulihan:</label>
                    <div class="relative">
                        <i data-lucide="at-sign" class="w-4 h-4 absolute left-3 top-3 text-slate-400"></i>
                        <input type="email" name="email" value="{{ old('email', request('email')) }}" required autofocus autocomplete="off"
                               placeholder="kucing@azkario.biz.id atau email pemulihan"
                               class="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white text-slate-800 font-medium transition-all">
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1.5 flex items-center">
                        <i data-lucide="shield-check" class="w-3.5 h-3.5 mr-1 text-blue-500 flex-shrink-0"></i>
                        Kode OTP akan dikirimkan ke email pemulihan akun yang terdaftar.
                    </p>
                </div>

                <button type="submit" 
                        class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-md shadow-blue-500/25 transition-all flex items-center justify-center space-x-1.5">
                    <span>Kirim Kode OTP Pemulihan</span>
                    <i data-lucide="send" class="w-3.5 h-3.5"></i>
                </button>
            </form>
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
