<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - ApexMail Web Client</title>
    
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
                <i data-lucide="mail" class="w-6 h-6"></i>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Apex<span class="text-blue-600">Mail</span></h1>
            <p class="text-xs text-slate-500">Smart Communication, Faster Workflows</p>
        </div>

        <!-- Card Login Form -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-xl shadow-slate-200/50 p-5 sm:p-8 space-y-5">
            <form action="{{ route('login') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Alamat Email:</label>
                    <div class="relative">
                        <i data-lucide="at-sign" class="w-4 h-4 absolute left-3 top-3 text-slate-400"></i>
                        <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="off"
                               placeholder="Masukkan email"
                               class="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white text-slate-800 font-medium transition-all">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-semibold text-slate-700">Kata Sandi:</label>
                    </div>
                    <div class="relative">
                        <i data-lucide="lock" class="w-4 h-4 absolute left-3 top-3 text-slate-400"></i>
                        <input type="password" name="password" required autocomplete="off"
                               placeholder="••••••••"
                               class="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white text-slate-800 transition-all">
                    </div>
                </div>

                @if($captchaEnabled ?? true)
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Kode Keamanan (CAPTCHA):</label>
                    <div class="flex items-center space-x-2">
                        <div class="relative overflow-hidden rounded-xl border border-slate-200 bg-slate-50 flex-shrink-0 cursor-pointer shadow-xs group" 
                             onclick="refreshCaptcha()" title="Klik untuk memuat ulang kode gambar">
                            <img id="captchaImage" src="{{ route('captcha.image') }}" alt="Kode CAPTCHA" class="h-10 w-[135px] object-cover select-none">
                            <div class="absolute inset-0 bg-slate-900/10 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <i data-lucide="rotate-cw" class="w-3.5 h-3.5 text-slate-700"></i>
                            </div>
                        </div>

                        <button type="button" onclick="refreshCaptcha()" title="Muat ulang kode keamanan"
                                class="p-2.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-500 hover:text-blue-600 transition-colors flex-shrink-0">
                            <i data-lucide="rotate-cw" class="w-4 h-4"></i>
                        </button>

                        <div class="relative flex-1">
                            <input type="text" name="captcha" required maxlength="6" autocomplete="off"
                                   placeholder="Ketik kode"
                                   class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white text-slate-900 font-mono font-bold tracking-widest uppercase transition-all">
                        </div>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1 flex items-center space-x-1">
                        <i data-lucide="shield-check" class="w-3 h-3 text-emerald-600"></i>
                        <span>Ketik kode di gambar. Klik tombol putar jika sulit dibaca.</span>
                    </p>
                </div>
                @endif

                <div class="flex items-center justify-between pt-1 text-xs">
                    <label class="inline-flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" name="remember" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-3.5 h-3.5">
                        <span class="text-slate-600 text-[11px]">Ingat sesi saya</span>
                    </label>
                    <a href="{{ route('password.forgot') }}" class="text-[11px] text-blue-600 hover:underline">Lupa sandi?</a>
                </div>

                <button type="submit" 
                        class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-md shadow-blue-500/25 transition-all flex items-center justify-center space-x-1.5">
                    <span>Masuk ke Kotak Surat</span>
                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Unified Alert Modal Component (Direct child of body, prevents space-y-6 margin leakage) -->
    @include('partials.alert-modal')

    <script>
        lucide.createIcons();

        function refreshCaptcha() {
            const img = document.getElementById('captchaImage');
            if (img) {
                img.src = '{{ route('captcha.image') }}?' + Date.now();
            }
        }
    </script>
</body>
</html>
