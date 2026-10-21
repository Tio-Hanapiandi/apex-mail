<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'ApexMail Collaboration Suite')</title>
    
    @include('partials.seo-head')
    
    <!-- Google Fonts: Inter & Noto Color Emoji -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Color+Emoji&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS (Play CDN for instant seamless styling) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'Noto Color Emoji', 'Apple Color Emoji', 'Segoe UI Emoji', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Twemoji Library for Full Universal Emoji Support -->
    <script src="https://cdn.jsdelivr.net/npm/@twemoji/api@latest/dist/twemoji.min.js" crossorigin="anonymous"></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body, input, textarea, select, button, .prose, .email-rendered-body { 
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Color Emoji", "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", sans-serif; 
        }
        /* Custom subtle scrollbar */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* Universal Emoji rendering (no missing boxes/tofu) */
        img.emoji {
            height: 1.25em !important;
            width: 1.25em !important;
            margin: 0 0.1em !important;
            vertical-align: -0.2em !important;
            display: inline-block !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            border: 0 !important;
        }

        /* Hide scrollbar for Chrome, Safari, Edge, Opera */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        /* Hide scrollbar for IE, Edge and Firefox */
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>
<body class="h-full flex flex-col antialiased text-slate-800 bg-[#f8fafc]">
    @php
        $activeAcc = auth()->user() 
            ?? \App\Models\MailAccount::find(session('active_account_id')) 
            ?? \App\Models\MailAccount::first();
    @endphp

    <!-- Main Clean Navigation Bar (Bright, Minimalist & Professional) -->
    <header class="h-14 bg-white border-b border-slate-200 px-3 sm:px-4 flex items-center justify-between z-20 flex-shrink-0">
        <div class="flex items-center space-x-2 sm:space-x-6">
            <!-- Mobile Menu Toggle Button -->
            <button type="button" onclick="toggleMobileNav()" 
                    class="md:hidden p-1.5 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-xl transition-colors" 
                    title="Buka Menu Navigasi" aria-label="Buka Menu Navigasi">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button>

            <!-- Brand Logo -->
            <a href="{{ route('webmail.index') }}" class="flex items-center space-x-2 text-slate-900 font-semibold tracking-tight text-base sm:text-lg">
                <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-blue-600 flex items-center justify-center text-white shadow-sm shadow-blue-500/30 flex-shrink-0">
                    <i data-lucide="mail" class="w-3.5 h-3.5 sm:w-4 sm:h-4"></i>
                </div>
                <span>Apex<span class="text-blue-600 font-bold">Mail</span></span>
            </a>

            <!-- Primary Nav Links (Desktop) -->
            <nav class="hidden md:flex items-center space-x-1">
                <a href="{{ route('webmail.index') }}" 
                   class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors {{ request()->routeIs('webmail.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                   Kotak Surat
                </a>
                <a href="{{ route('groupware.calendar') }}" 
                   class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors {{ request()->routeIs('groupware.calendar') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                   Kalender
                </a>
                @if(optional($activeAcc)->is_admin)
                    <a href="{{ route('groupware.contacts') }}" 
                       class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors {{ request()->routeIs('groupware.contacts') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                       Kontak & Direktori
                    </a>
                    <a href="{{ route('admin.dashboard') }}" 
                       class="px-3 py-1.5 text-sm font-semibold rounded-md transition-colors flex items-center space-x-1 {{ request()->is('admin*') ? 'bg-amber-100 text-amber-900 font-bold border border-amber-300' : 'text-amber-800 hover:text-amber-900 hover:bg-amber-50' }}">
                       <i data-lucide="shield" class="w-3.5 h-3.5 text-amber-600"></i>
                       <span>Admin Console</span>
                    </a>
                @endif
            </nav>
        </div>

        <!-- Right Side: Account Switcher & User Profile -->
        <div class="flex items-center space-x-2 sm:space-x-3">

            <!-- User Profile Selector & Logout -->
            <div class="relative">
                <button type="button" id="userProfileBtn" onclick="toggleUserDropdown(event)" 
                        class="flex items-center space-x-1.5 sm:space-x-2 p-1.5 rounded-lg hover:bg-slate-100 transition-colors text-left focus:outline-none">
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-500 text-white flex items-center justify-center font-semibold text-xs shadow-sm">
                        {{ strtoupper(substr($activeAcc->username ?? 'U', 0, 2)) }}
                    </div>
                    <div class="hidden lg:block text-xs">
                        <div class="font-medium text-slate-800 flex items-center space-x-1">
                            <span>{{ $activeAcc->display_name ?? 'Pengguna' }}</span>
                            @if(optional($activeAcc)->is_admin)
                                <span class="px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 text-[9px] font-bold">Admin</span>
                            @endif
                        </div>
                        <div class="text-slate-500 text-[11px]">{{ $activeAcc->email ?? '' }}</div>
                    </div>
                    <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400"></i>
                </button>

                <!-- Dropdown Menu (Click/Tap Toggle + Outside Click Listener) -->
                <div id="userProfileDropdown" class="hidden absolute right-0 mt-2 w-64 bg-white rounded-2xl shadow-xl border border-slate-200 py-2 z-50">
                    <div class="px-4 py-2 border-b border-slate-100 text-xs">
                        <div class="font-bold text-slate-800">{{ $activeAcc->display_name ?? 'Pengguna' }}</div>
                        <div class="text-[11px] text-slate-400 font-mono">{{ $activeAcc->email ?? '' }}</div>
                    </div>

                    @if(optional($activeAcc)->is_admin)
                        <div class="py-1">
                            <a href="{{ route('admin.dashboard') }}" class="flex items-center space-x-2 px-4 py-2 text-xs text-amber-700 hover:bg-amber-50 font-semibold">
                                <i data-lucide="shield" class="w-3.5 h-3.5 text-amber-600"></i>
                                <span>Administration Console</span>
                            </a>
                        </div>
                        <div class="border-t border-slate-100"></div>
                    @endif
                    <!-- Menu Pengaturan Keamanan & 2FA -->
                    <div class="py-1">
                        <a href="{{ route('security.two-factor') }}" class="flex items-center justify-between px-4 py-2 text-xs text-slate-700 hover:bg-slate-50 transition-colors">
                            <div class="flex items-center space-x-2">
                                <i data-lucide="shield-check" class="w-3.5 h-3.5 text-blue-600"></i>
                                <span class="font-medium">Keamanan & 2FA</span>
                            </div>
                            @if(optional($activeAcc)->two_factor_enabled)
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Aktif</span>
                            @else
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-medium bg-slate-100 text-slate-500">Nonaktif</span>
                            @endif
                        </a>

                        <a href="{{ route('webmail.forwarding') }}" class="flex items-center justify-between px-4 py-2 text-xs text-slate-700 hover:bg-slate-50 transition-colors">
                            <div class="flex items-center space-x-2">
                                <i data-lucide="forward" class="w-3.5 h-3.5 text-indigo-600"></i>
                                <span class="font-medium">Penerusan Email</span>
                            </div>
                            @if(optional($activeAcc)->forward_active)
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Aktif</span>
                            @else
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-medium bg-slate-100 text-slate-500">Nonaktif</span>
                            @endif
                        </a>
                    </div>
                    <div class="border-t border-slate-100"></div>

                    <div class="py-1">
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 text-xs text-rose-600 hover:bg-rose-50 flex items-center space-x-2">
                                <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                                <span>Keluar / Logout</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Mobile Navigation Drawer & Backdrop -->
    <div id="mobileNavBackdrop" onclick="closeMobileNav()" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-40 hidden md:hidden transition-opacity duration-200"></div>

    <aside id="mobileNavDrawer" class="fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] bg-white border-r border-slate-200 shadow-2xl flex flex-col justify-between transform -translate-x-full transition-transform duration-200 ease-in-out md:hidden">
        <!-- Drawer Header -->
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <a href="{{ route('webmail.index') }}" class="flex items-center space-x-2 text-slate-900 font-semibold tracking-tight text-base">
                <div class="w-7 h-7 rounded-lg bg-blue-600 flex items-center justify-center text-white shadow-sm shadow-blue-500/30">
                    <i data-lucide="mail" class="w-4 h-4"></i>
                </div>
                <span>Apex<span class="text-blue-600 font-bold">Mail</span></span>
            </a>
            <button type="button" onclick="closeMobileNav()" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100" title="Tutup Menu">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Drawer Content -->
        <div class="p-4 space-y-4 overflow-y-auto flex-1">
            <!-- User Profile Summary in Drawer -->
            <div class="p-3 rounded-2xl bg-gradient-to-r from-slate-50 to-blue-50/50 border border-slate-200/80">
                <div class="flex items-center space-x-2.5">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-bold text-xs flex items-center justify-center shadow-xs flex-shrink-0">
                        {{ strtoupper(substr($activeAcc->username ?? 'U', 0, 2)) }}
                    </div>
                    <div class="truncate text-left flex-1 min-w-0">
                        <div class="font-bold text-xs text-slate-800 truncate flex items-center space-x-1">
                            <span class="truncate">{{ $activeAcc->display_name ?? 'Pengguna' }}</span>
                            @if(optional($activeAcc)->is_admin)
                                <span class="px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 text-[9px] font-bold flex-shrink-0">Admin</span>
                            @endif
                        </div>
                        <div class="text-[11px] text-slate-500 font-mono truncate">{{ $activeAcc->email ?? '' }}</div>
                    </div>
                </div>
            </div>

            <!-- Menu Navigation Links -->
            <nav class="space-y-1">
                <div class="px-2 py-1 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Aplikasi Utama</div>
                <a href="{{ route('webmail.index') }}" 
                   class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs font-medium transition-colors {{ request()->routeIs('webmail.*') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                    <i data-lucide="mail" class="w-4 h-4 text-blue-600"></i>
                    <span>Kotak Surat</span>
                </a>
                <a href="{{ route('groupware.calendar') }}" 
                   class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs font-medium transition-colors {{ request()->routeIs('groupware.calendar') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                    <i data-lucide="calendar" class="w-4 h-4 text-indigo-600"></i>
                    <span>Kalender & Jadwal</span>
                </a>
                @if(optional($activeAcc)->is_admin)
                    <a href="{{ route('groupware.contacts') }}" 
                       class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs font-medium transition-colors {{ request()->routeIs('groupware.contacts') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                        <i data-lucide="users" class="w-4 h-4 text-emerald-600"></i>
                        <span>Kontak & Direktori</span>
                    </a>
                @endif

                <div class="pt-3 px-2 py-1 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Keamanan & Akun</div>
                <a href="{{ route('security.two-factor') }}" 
                   class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-medium transition-colors {{ request()->routeIs('security.*') ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                    <div class="flex items-center space-x-3">
                        <i data-lucide="shield-check" class="w-4 h-4 text-blue-600"></i>
                        <span>Keamanan & 2FA</span>
                    </div>
                    @if(optional($activeAcc)->two_factor_enabled)
                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Aktif</span>
                    @else
                        <span class="px-1.5 py-0.2 rounded text-[9px] font-medium bg-slate-100 text-slate-500">Nonaktif</span>
                    @endif
                </a>
                <a href="{{ route('webmail.forwarding') }}" 
                   class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-medium transition-colors {{ request()->routeIs('webmail.forwarding*') ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-700 hover:bg-slate-50' }}">
                    <div class="flex items-center space-x-3">
                        <i data-lucide="forward" class="w-4 h-4 text-indigo-600"></i>
                        <span>Penerusan Email</span>
                    </div>
                    @if(optional($activeAcc)->forward_active)
                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Aktif</span>
                    @else
                        <span class="px-1.5 py-0.2 rounded text-[9px] font-medium bg-slate-100 text-slate-500">Nonaktif</span>
                    @endif
                </a>
                @if(optional($activeAcc)->is_admin)
                    <a href="{{ route('admin.dashboard') }}" 
                       class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition-colors {{ request()->is('admin*') ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'text-amber-800 hover:bg-amber-50' }}">
                        <i data-lucide="shield" class="w-4 h-4 text-amber-600"></i>
                        <span>Administration Console</span>
                    </a>
                @endif
            </nav>
        </div>

        <!-- Drawer Footer: Logout -->
        <div class="p-4 border-t border-slate-100 bg-slate-50">
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="w-full flex items-center justify-center space-x-2 py-2.5 px-4 rounded-xl text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 transition-colors">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                    <span>Keluar / Logout</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content Container -->
    <main class="flex-1 overflow-hidden flex flex-col">
        @yield('content')
    </main>

    <!-- Unified Alert & Confirm Modal (Direct child of body, zero margin leakage) -->
    @include('partials.alert-modal')

    <!-- Init Lucide Icons & Twemoji Universal Emojis -->
    <script>
        function renderTwemoji(root) {
            if (window.twemoji) {
                window.twemoji.parse(root || document.body, {
                    base: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/'
                });
            }
        }

        // Mobile Nav Drawer Functions
        function toggleMobileNav() {
            const drawer = document.getElementById('mobileNavDrawer');
            const backdrop = document.getElementById('mobileNavBackdrop');
            if (drawer && backdrop) {
                const isOpen = !drawer.classList.contains('-translate-x-full');
                if (isOpen) {
                    closeMobileNav();
                } else {
                    drawer.classList.remove('-translate-x-full');
                    drawer.classList.add('translate-x-0');
                    backdrop.classList.remove('hidden');
                }
            }
        }

        function closeMobileNav() {
            const drawer = document.getElementById('mobileNavDrawer');
            const backdrop = document.getElementById('mobileNavBackdrop');
            if (drawer && backdrop) {
                drawer.classList.remove('translate-x-0');
                drawer.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
            }
        }

        // Tap/Click User Profile Dropdown Toggle
        function toggleUserDropdown(event) {
            if (event) {
                event.stopPropagation();
            }
            const menu = document.getElementById('userProfileDropdown');
            if (menu) {
                menu.classList.toggle('hidden');
            }
        }

        // Close dropdown when tapping anywhere else
        document.addEventListener('click', (event) => {
            const menu = document.getElementById('userProfileDropdown');
            const btn = document.getElementById('userProfileBtn');
            if (menu && !menu.classList.contains('hidden')) {
                if (btn && !btn.contains(event.target) && !menu.contains(event.target)) {
                    menu.classList.add('hidden');
                }
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide) {
                lucide.createIcons();
            }
            renderTwemoji();
        });
    </script>
    @stack('scripts')
</body>
</html>
