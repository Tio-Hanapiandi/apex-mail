@extends('layouts.app')

@section('title', 'Kotak Surat - ' . ($currentAccount->email ?? 'ApexMail'))

@section('content')
@php
    // Helper function for deterministic, friendly pastel avatar colors
    if (!function_exists('getAvatarColor')) {
        function getAvatarColor($string) {
            $colors = [
                ['bg' => 'bg-blue-100 text-blue-700 border-blue-200', 'dot' => 'bg-blue-500'],
                ['bg' => 'bg-indigo-100 text-indigo-700 border-indigo-200', 'dot' => 'bg-indigo-500'],
                ['bg' => 'bg-emerald-100 text-emerald-800 border-emerald-200', 'dot' => 'bg-emerald-500'],
                ['bg' => 'bg-violet-100 text-violet-700 border-violet-200', 'dot' => 'bg-violet-500'],
                ['bg' => 'bg-amber-100 text-amber-900 border-amber-200', 'dot' => 'bg-amber-500'],
                ['bg' => 'bg-rose-100 text-rose-700 border-rose-200', 'dot' => 'bg-rose-500'],
                ['bg' => 'bg-cyan-100 text-cyan-800 border-cyan-200', 'dot' => 'bg-cyan-500'],
                ['bg' => 'bg-teal-100 text-teal-800 border-teal-200', 'dot' => 'bg-teal-500'],
            ];
            $hash = crc32($string ?? 'ApexMail');
            return $colors[abs($hash) % count($colors)];
        }
    }

    $activeFilter = $filter ?? 'all';
@endphp

<div class="flex-1 flex overflow-hidden relative select-none">

    <!-- Mobile Folder Drawer Backdrop -->
    <div id="mobileFolderBackdrop" onclick="closeFolderDrawer()" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-40 hidden lg:hidden transition-opacity duration-200"></div>

    <!-- ================= 1. SIDEBAR NAVIGASI & AKUN (Ramah & Bersih) ================= -->
    <aside id="folderSidebar" class="fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] bg-white border-r border-slate-200 flex flex-col justify-between flex-shrink-0 shadow-2xl transition-transform duration-200 ease-in-out -translate-x-full lg:translate-x-0 lg:static lg:w-64 lg:z-10 lg:shadow-xs">
        <div class="p-4 space-y-4 overflow-y-auto">
            
            <!-- Mobile Drawer Header -->
            <div class="flex lg:hidden items-center justify-between pb-3 border-b border-slate-100">
                <span class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center space-x-1.5">
                    <i data-lucide="folder" class="w-4 h-4 text-blue-600"></i>
                    <span>Folder Surat</span>
                </span>
                <button type="button" onclick="closeFolderDrawer()" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors" title="Tutup">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <!-- Kartu Info Akun Aktif (Profil Pengguna Login) -->
            <div class="p-2.5 rounded-2xl bg-gradient-to-r from-slate-50 to-blue-50/50 border border-slate-200/80">
                <div class="flex items-center space-x-2.5 truncate">
                    <div class="relative flex-shrink-0">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-bold text-xs flex items-center justify-center shadow-xs">
                            {{ strtoupper(substr($currentAccount->display_name ?: $currentAccount->username, 0, 2)) }}
                        </div>
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 border-2 border-white absolute -bottom-0.5 -right-0.5" title="Online / Aktif"></span>
                    </div>
                    <div class="truncate text-left">
                        <div class="font-bold text-xs text-slate-800 truncate leading-tight">
                            {{ $currentAccount->display_name ?: $currentAccount->username }}
                        </div>
                        <div class="text-[11px] text-slate-500 font-mono truncate">
                            {{ $currentAccount->email }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tombol Utama Tulis Pesan -->
            <button onclick="openComposer()" 
                    class="w-full flex items-center justify-between py-2.5 px-4 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold text-xs rounded-xl shadow-sm shadow-blue-500/25 transition-all transform active:scale-98 group">
                <div class="flex items-center space-x-2">
                    <i data-lucide="edit-3" class="w-4 h-4"></i>
                    <span>Tulis Pesan Baru</span>
                </div>
                <span class="text-[10px] font-mono opacity-70 bg-white/20 px-1.5 py-0.5 rounded">C</span>
            </button>

            <!-- Navigasi Folder Utama -->
            <div class="space-y-1 pt-1">
                <div class="flex items-center justify-between px-3 py-1">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Kotak Surat</span>
                    <button onclick="openCommandPalette()" title="Pintasan (Ctrl+K)" class="text-slate-400 hover:text-slate-600 text-[10px]">
                        <kbd class="font-mono bg-slate-100 px-1.5 py-0.5 rounded text-[10px] border border-slate-200">⌘K</kbd>
                    </button>
                </div>

                @foreach($folders as $folder)
                    @php
                        $isActive = ($currentFolder && $currentFolder->slug === $folder->slug);
                        $iconData = match($folder->slug) {
                            'inbox' => ['name' => 'inbox', 'color' => 'text-blue-600', 'active_bg' => 'bg-blue-50 text-blue-700 border-blue-200'],
                            'sent' => ['name' => 'send', 'color' => 'text-emerald-600', 'active_bg' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
                            'drafts' => ['name' => 'file-text', 'color' => 'text-amber-600', 'active_bg' => 'bg-amber-50 text-amber-800 border-amber-200'],
                            'archive' => ['name' => 'archive', 'color' => 'text-purple-600', 'active_bg' => 'bg-purple-50 text-purple-700 border-purple-200'],
                            'spam' => ['name' => 'shield-alert', 'color' => 'text-rose-500', 'active_bg' => 'bg-rose-50 text-rose-700 border-rose-200'],
                            'trash' => ['name' => 'trash-2', 'color' => 'text-slate-500', 'active_bg' => 'bg-slate-100 text-slate-800 border-slate-300'],
                            default => ['name' => 'folder', 'color' => 'text-slate-400', 'active_bg' => 'bg-slate-100 text-slate-800 border-slate-200']
                        };
                    @endphp
                    <a href="{{ route('webmail.index', ['folder' => $folder->slug]) }}" 
                       class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-medium transition-all group border {{ $isActive ? $iconData['active_bg'] . ' font-bold shadow-xs' : 'border-transparent text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                        <div class="flex items-center space-x-2.5 truncate">
                            <i data-lucide="{{ $iconData['name'] }}" class="w-4 h-4 flex-shrink-0 {{ $isActive ? $iconData['color'] : 'text-slate-400 group-hover:text-slate-600' }}"></i>
                            <span class="truncate">{{ $folder->name }}</span>
                        </div>
                        @if($folder->unread_count > 0)
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $isActive ? 'bg-blue-600 text-white' : 'bg-blue-100 text-blue-700' }}">
                                {{ $folder->unread_count }}
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>

            <!-- Quick Filter Links -->
            <div class="space-y-1 pt-3 border-t border-slate-100">
                <div class="px-3 py-1 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Filter Cepat</div>
                <a href="{{ route('webmail.index', ['folder' => $currentFolder->slug ?? 'inbox', 'filter' => 'unread']) }}" 
                   class="flex items-center justify-between px-3 py-1.5 rounded-lg text-xs {{ $activeFilter === 'unread' ? 'bg-blue-50 text-blue-700 font-bold' : 'text-slate-600 hover:bg-slate-100/70' }}">
                    <span class="flex items-center space-x-2">
                        <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                        <span>Belum Dibaca</span>
                    </span>
                    @if(optional($currentFolder)->unread_count > 0)
                        <span class="text-[10px] text-blue-600 font-semibold">{{ $currentFolder->unread_count }}</span>
                    @endif
                </a>
                <a href="{{ route('webmail.index', ['folder' => $currentFolder->slug ?? 'inbox', 'filter' => 'starred']) }}" 
                   class="flex items-center space-x-2 px-3 py-1.5 rounded-lg text-xs {{ $activeFilter === 'starred' ? 'bg-amber-50 text-amber-800 font-bold' : 'text-slate-600 hover:bg-slate-100/70' }}">
                    <i data-lucide="star" class="w-3.5 h-3.5 text-amber-500 fill-amber-400"></i>
                    <span>Berbintang</span>
                </a>
                <a href="{{ route('webmail.index', ['folder' => $currentFolder->slug ?? 'inbox', 'filter' => 'attachments']) }}" 
                   class="flex items-center space-x-2 px-3 py-1.5 rounded-lg text-xs {{ $activeFilter === 'attachments' ? 'bg-purple-50 text-purple-800 font-bold' : 'text-slate-600 hover:bg-slate-100/70' }}">
                    <i data-lucide="paperclip" class="w-3.5 h-3.5 text-purple-600"></i>
                    <span>Ada Lampiran</span>
                </a>
            </div>

        </div>

        <!-- Bagian Bawah: Kapasitas Kotak Surat & Pintasan -->
        <div class="p-4 border-t border-slate-200/80 bg-slate-50/70 space-y-3">
            @php
                $usedMb = round(($currentAccount->used_bytes ?? 0) / 1048576, 1);
                $totalGb = round(($currentAccount->quota_bytes ?? 10737418240) / 1073741824, 1);
                $totalMb = $totalGb * 1024;
                $percent = $totalMb > 0 ? min(round(($usedMb / $totalMb) * 100, 1), 100) : 0;
            @endphp
            <div class="space-y-1.5">
                <div class="flex items-center justify-between text-[11px] text-slate-500">
                    <span class="flex items-center space-x-1.5 font-medium">
                        <i data-lucide="pie-chart" class="w-3.5 h-3.5 text-blue-600"></i>
                        <span>Penyimpanan</span>
                    </span>
                    <span class="font-bold text-slate-700">{{ $usedMb }} MB / {{ $totalGb }} GB</span>
                </div>
                <div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-500 to-indigo-600 h-2 rounded-full transition-all" style="width: {{ max($percent, 2) }}%"></div>
                </div>
            </div>

            <!-- Pintasan Penerusan Email -->
            <a href="{{ route('webmail.forwarding') }}" 
               class="flex items-center justify-between p-2 rounded-xl text-xs {{ optional($currentAccount)->forward_active ? 'bg-indigo-50/80 text-indigo-700 border border-indigo-200/80' : 'text-slate-600 hover:bg-slate-100/70 border border-transparent' }} transition-colors"
               title="Buka pengaturan penerusan email otomatis">
                <span class="flex items-center space-x-2 font-medium">
                    <i data-lucide="forward" class="w-3.5 h-3.5 {{ optional($currentAccount)->forward_active ? 'text-indigo-600' : 'text-slate-400' }}"></i>
                    <span>Penerusan Email</span>
                </span>
                @if(optional($currentAccount)->forward_active)
                    <span class="px-1.5 py-0.2 rounded-full text-[9px] font-bold bg-indigo-600 text-white">Aktif</span>
                @else
                    <i data-lucide="chevron-right" class="w-3 h-3 text-slate-400"></i>
                @endif
            </a>

            <!-- Pintasan Keyboard Cepat -->
            <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between text-[10px] text-slate-400">
                <span>Pintasan:</span>
                <div class="flex items-center space-x-1 font-mono">
                    <kbd class="bg-white px-1.5 py-0.5 rounded border border-slate-200 text-slate-600 font-semibold shadow-2xs">C</kbd> Tulis
                    <kbd class="bg-white px-1.5 py-0.5 rounded border border-slate-200 text-slate-600 font-semibold shadow-2xs ml-1">/</kbd> Cari
                </div>
            </div>
        </div>
    </aside>

    <!-- ================= 2. DAFTAR EMAIL (Sangat Jelas, Mudah Dibaca & Friendly) ================= -->
    <section class="{{ $selectedEmail ? 'hidden lg:flex' : 'flex flex-1 w-full lg:flex-none' }} lg:w-96 xl:w-[420px] bg-slate-50/50 border-r border-slate-200 flex-col flex-shrink-0 z-0">
        
        <!-- Header Pencarian & Refresh Cepat -->
        <div class="p-3 bg-white border-b border-slate-200 space-y-2.5">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-1.5 sm:space-x-2">
                    <!-- Mobile Folder Drawer Button -->
                    <button type="button" onclick="openFolderDrawer()" 
                            class="lg:hidden p-1.5 -ml-1 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors flex items-center space-x-1 border border-slate-200 shadow-2xs" 
                            title="Buka Folder">
                        <i data-lucide="folder" class="w-3.5 h-3.5 text-blue-600"></i>
                        <span class="text-xs font-semibold text-slate-700">Folder</span>
                    </button>

                    <h2 class="font-bold text-sm text-slate-800 tracking-tight">
                        {{ $currentFolder->name ?? 'Kotak Surat' }}
                    </h2>
                    <span id="emailTotalBadge" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">
                        {{ $emails->total() }}
                    </span>
                </div>
                <!-- Tombol Action Kanan Header -->
                <div class="flex items-center space-x-1">
                    <!-- Tombol Tulis Surat Mobile -->
                    <button type="button" onclick="openComposer()" 
                            class="lg:hidden p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors" 
                            title="Tulis Pesan Baru">
                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                    </button>
                    <!-- Tombol Refresh / Sinkronisasi -->
                    <a href="{{ route('webmail.index', array_filter(['folder' => $currentFolder->slug ?? 'inbox', 'page' => request('page'), 'filter' => $activeFilter !== 'all' ? $activeFilter : null, 'q' => $query])) }}" 
                       title="Segarkan / Refresh Kotak Masuk"
                       class="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-slate-100 transition-colors">
                        <i data-lucide="rotate-cw" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>

            <!-- Input Pencarian Cerdas & Live Search -->
            <form id="searchForm" action="{{ route('webmail.index') }}" method="GET" onsubmit="event.preventDefault(); triggerSearchNow();" class="relative space-y-1.5">
                <input type="hidden" name="folder" value="{{ $currentFolder->slug ?? 'inbox' }}">
                @if($activeFilter !== 'all')
                    <input type="hidden" name="filter" value="{{ $activeFilter }}">
                @endif
                <div class="relative flex items-center">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-lucide="search" id="searchIcon" class="w-4 h-4 text-slate-400 transition-colors"></i>
                        <svg id="searchSpinner" class="hidden w-4 h-4 text-blue-600 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <input type="text" id="searchInput" name="q" value="{{ $query }}" autocomplete="off" spellcheck="false"
                           placeholder="Cari email cepat (tekan '/')..."
                           oninput="onSearchInputChange()"
                           class="w-full pl-9 pr-14 py-2 bg-slate-100/90 hover:bg-slate-100 focus:bg-white text-xs rounded-xl text-slate-800 placeholder:text-slate-400 border border-slate-200/80 focus:border-blue-500 focus:ring-3 focus:ring-blue-500/15 transition-all outline-none">
                    <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center space-x-1">
                        <button type="button" id="searchClearBtn" onclick="clearLiveSearch()"
                                class="{{ $query ? '' : 'hidden' }} p-1 text-slate-400 hover:text-slate-600 hover:bg-slate-200/60 rounded-md transition-colors"
                                title="Hapus pencarian (Esc)">
                            <i data-lucide="x" class="w-3.5 h-3.5"></i>
                        </button>
                        <kbd id="searchKbdBadge" class="{{ $query ? 'hidden' : 'hidden sm:inline-flex' }} items-center px-1.5 py-0.5 text-[10px] font-mono font-medium text-slate-400 bg-white border border-slate-200/80 rounded-md shadow-2xs select-none">
                            /
                        </kbd>
                    </div>
                </div>
                
                <!-- Live Search Status Bar -->
                <div id="searchStatusBar" class="{{ $query ? '' : 'hidden' }} flex items-center justify-between text-[11px] px-1 text-slate-500">
                    <span class="truncate">
                        Hasil untuk: <strong id="searchQueryLabel" class="text-blue-600 font-semibold">"{{ $query }}"</strong>
                    </span>
                    <span id="searchResultCount" class="text-slate-400 font-medium whitespace-nowrap ml-2">
                        {{ $emails->total() }} email
                    </span>
                </div>
            </form>

            <!-- Filter Pills Tab -->
            <div class="flex items-center space-x-1.5 overflow-x-auto no-scrollbar pb-0.5 text-[11px]">
                <a href="{{ route('webmail.index', ['folder' => $currentFolder->slug ?? 'inbox', 'q' => $query]) }}" 
                   data-filter="all"
                   class="filter-pill-link px-2.5 py-1 rounded-lg font-medium transition-colors whitespace-nowrap {{ $activeFilter === 'all' ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200/70' }}">
                    Semua
                </a>
                <a href="{{ route('webmail.index', ['folder' => $currentFolder->slug ?? 'inbox', 'filter' => 'unread', 'q' => $query]) }}" 
                   data-filter="unread"
                   class="filter-pill-link px-2.5 py-1 rounded-lg font-medium transition-colors whitespace-nowrap {{ $activeFilter === 'unread' ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200/70' }}">
                    Belum Dibaca
                </a>
                <a href="{{ route('webmail.index', ['folder' => $currentFolder->slug ?? 'inbox', 'filter' => 'starred', 'q' => $query]) }}" 
                   data-filter="starred"
                   class="filter-pill-link px-2.5 py-1 rounded-lg font-medium transition-colors whitespace-nowrap {{ $activeFilter === 'starred' ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200/70' }}">
                    Berbintang
                </a>
                <a href="{{ route('webmail.index', ['folder' => $currentFolder->slug ?? 'inbox', 'filter' => 'attachments', 'q' => $query]) }}" 
                   data-filter="attachments"
                   class="filter-pill-link px-2.5 py-1 rounded-lg font-medium transition-colors whitespace-nowrap {{ $activeFilter === 'attachments' ? 'bg-blue-600 text-white font-semibold shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200/70' }}">
                    Lampiran
                </a>
            </div>
        </div>

        <!-- Floating Contextual Batch Toolbar -->
        <div id="batchToolbar" class="hidden px-3.5 py-2.5 bg-blue-50 border-b border-blue-200/60 flex items-center justify-between text-xs text-blue-900 transition-all shadow-xs">
            <div class="flex items-center space-x-2">
                <span id="selectedCount" class="font-bold text-blue-700">0 terpilih</span>
            </div>
            <div class="flex items-center space-x-1">
                <button type="button" onclick="submitBatch('mark_read')" class="p-1.5 hover:bg-blue-100 rounded-lg text-blue-700" title="Tandai Sudah Dibaca">
                    <i data-lucide="check-check" class="w-3.5 h-3.5"></i>
                </button>
                <button type="button" onclick="submitBatch('mark_unread')" class="p-1.5 hover:bg-blue-100 rounded-lg text-blue-700" title="Tandai Belum Dibaca">
                    <i data-lucide="mail" class="w-3.5 h-3.5"></i>
                </button>
                <button type="button" onclick="submitBatch('star')" class="p-1.5 hover:bg-blue-100 rounded-lg text-amber-600" title="Beri Bintang">
                    <i data-lucide="star" class="w-3.5 h-3.5"></i>
                </button>
                <button type="button" onclick="submitBatch('delete')" class="p-1.5 hover:bg-rose-100 rounded-lg text-rose-600" title="Pindahkan ke Sampah">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                </button>
                <button type="button" onclick="deselectAll()" class="p-1.5 hover:bg-blue-100 rounded-lg text-slate-500" title="Batal Pilih">
                    <i data-lucide="x" class="w-3.5 h-3.5"></i>
                </button>
            </div>
        </div>

        <!-- Sub-header Pemilih Massal -->
        <div class="px-4 py-2 bg-slate-100/60 border-b border-slate-200/70 flex items-center justify-between text-[11px] text-slate-500">
            <label class="flex items-center space-x-2 cursor-pointer select-none">
                <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" 
                       class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-3.5 h-3.5 cursor-pointer">
                <span class="font-medium text-slate-600">Pilih Semua</span>
            </label>
            <span>Urutkan: <strong>Terbaru</strong></span>
        </div>

        <!-- Daftar Email Scrollable (Kartu Bersahabat) -->
        <div id="emailListContainer" class="flex-1 overflow-y-auto divide-y divide-slate-100/80 bg-white">
            @include('webmail.partials.email-list')
        </div>

        <!-- Pagination Ringkas -->
        <div id="emailPaginationContainer">
            @include('webmail.partials.pagination')
        </div>
        @if(!$selectedEmail)
            <!-- Floating Action Button for Mobile Compose -->
            <button onclick="openComposer()" 
                    class="lg:hidden fixed bottom-6 right-5 z-30 w-12 h-12 rounded-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-xl shadow-blue-500/40 flex items-center justify-center hover:scale-105 active:scale-95 transition-all" 
                    title="Tulis Pesan Baru">
                <i data-lucide="edit-3" class="w-5 h-5"></i>
            </button>
        @endif
    </section>

    <!-- ================= 3. PANEL BACA EMAIL (Sangat Lega, Nyaman & Mudah Dibaca) ================= -->
    <main class="{{ $selectedEmail ? 'flex flex-1 w-full' : 'hidden lg:flex flex-1' }} bg-[#f8fafc] overflow-y-auto flex-col justify-between z-0">
        @if($selectedEmail)
            @php
                $userEmail = $currentAccount->email ?? auth()->user()->email;
                $replySingleData = $selectedEmail->getReplySingleRecipients($userEmail);
                $replyAllData = $selectedEmail->getReplyAllRecipients($userEmail);
                $hasMultipleRecipients = $replyAllData['has_multiple'];
                $replyAllCount = $replyAllData['total_count'];
                $isSelfSender = $replySingleData['is_self_sender'];

                $forwardSubject = str_starts_with(strtolower($selectedEmail->subject ?? ''), 'fwd:') 
                    ? $selectedEmail->subject 
                    : 'Fwd: ' . ($selectedEmail->subject ?: 'Tanpa Subjek');

                $fromFormatted = $selectedEmail->from_name 
                    ? "{$selectedEmail->from_name} <{$selectedEmail->from_email}>" 
                    : $selectedEmail->from_email;

                $toFormatted = is_array($selectedEmail->to) 
                    ? implode(', ', array_map(function($t) { 
                        if (!is_array($t)) return (string) $t;
                        $e = $t['email'] ?? $t['address'] ?? '';
                        $n = $t['name'] ?? '';
                        return ($n && $e) ? "{$n} <{$e}>" : ($e ?: $n);
                    }, $selectedEmail->to)) 
                    : $selectedEmail->to;

                $dateFormatted = $selectedEmail->sent_at ? $selectedEmail->sent_at->isoFormat('dddd, D MMMM Y • HH:mm') . ' WIB' : '';

                $forwardHeaderBody = "\n\n---------- Pesan yang Diteruskan ----------\n"
                    . "Dari: {$fromFormatted}\n"
                    . "Tanggal: {$dateFormatted}\n"
                    . "Subjek: {$selectedEmail->subject}\n"
                    . "Kepada: {$toFormatted}\n\n"
                    . ($selectedEmail->body_text ?: strip_tags($selectedEmail->body_html));

                $forwardAttachments = $selectedEmail->attachments->map(function($att) {
                    return [
                        'id' => $att->id,
                        'name' => $att->filename,
                        'size' => round($att->size_bytes / 1024, 1) . ' KB',
                    ];
                })->values()->all();
            @endphp
            <script>
                window.currentEmailReplySingle = {
                    to: {!! json_encode($replySingleData['to_string']) !!},
                    subject: {!! json_encode(str_starts_with(strtolower($selectedEmail->subject ?? ''), 're:') ? $selectedEmail->subject : 'Re: ' . ($selectedEmail->subject ?: 'Tanpa Subjek')) !!}
                };
                window.currentEmailReplyAll = {
                    to: {!! json_encode($replyAllData['to_string']) !!},
                    cc: {!! json_encode($replyAllData['cc_string']) !!},
                    subject: {!! json_encode(str_starts_with(strtolower($selectedEmail->subject ?? ''), 're:') ? $selectedEmail->subject : 'Re: ' . ($selectedEmail->subject ?: 'Tanpa Subjek')) !!}
                };
                window.currentEmailForward = {
                    subject: {!! json_encode($forwardSubject) !!},
                    body: {!! json_encode($forwardHeaderBody) !!},
                    attachments: {!! json_encode($forwardAttachments) !!}
                };
            </script>
            <!-- Mobile Sticky Back Navigation Bar -->
            <div class="lg:hidden bg-white border-b border-slate-200 px-3 py-2 flex items-center justify-between sticky top-0 z-20 shadow-2xs">
                <a href="{{ route('webmail.index', array_filter(['folder' => $currentFolder->slug ?? 'inbox', 'page' => request('page'), 'q' => $query, 'filter' => $activeFilter])) }}" 
                   class="inline-flex items-center space-x-1.5 text-xs font-semibold text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 py-1.5 px-3 rounded-xl transition-colors">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    <span>Kembali ke Daftar</span>
                </a>
                <div class="flex items-center space-x-1">
                    <button onclick="scrollToQuickReply(false)" class="p-1.5 text-slate-600 hover:text-blue-600 rounded-lg hover:bg-slate-100 transition-colors" title="{{ $isSelfSender ? 'Kirim balasan/tindak lanjut ke penerima' : 'Balas Pengirim' }}">
                        <i data-lucide="reply" class="w-4 h-4"></i>
                    </button>
                    <button onclick="scrollToQuickReply(true)" class="p-1.5 {{ $hasMultipleRecipients ? 'text-indigo-600 bg-indigo-50 font-bold' : 'text-slate-600' }} hover:text-indigo-600 rounded-lg hover:bg-slate-100 transition-colors" title="Balas Semua ({{ $replyAllCount }} alamat)">
                        <i data-lucide="reply-all" class="w-4 h-4"></i>
                    </button>
                    <button onclick="openForwardModal()" class="p-1.5 text-slate-600 hover:text-indigo-600 rounded-lg hover:bg-slate-100 transition-colors" title="Teruskan Pesan">
                        <i data-lucide="forward" class="w-4 h-4 text-indigo-600"></i>
                    </button>
                    <button onclick="markCurrentAsUnread({{ $selectedEmail->id }})" class="p-1.5 text-slate-600 hover:text-blue-600 rounded-lg hover:bg-slate-100 transition-colors" title="Tandai Belum Dibaca">
                        <i data-lucide="mail" class="w-4 h-4"></i>
                    </button>
                    <form id="deleteEmailMobileTopForm_{{ $selectedEmail->id }}" action="{{ route('webmail.destroy', $selectedEmail->id) }}" method="POST" class="inline">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="page" value="{{ request('page') }}">
                        <input type="hidden" name="folder" value="{{ $currentFolder->slug ?? 'inbox' }}">
                        <button type="button" onclick="showConfirmModal('Pindahkan ke Sampah?', 'Apakah Anda yakin ingin memindahkan email ini ke folder Sampah?', () => document.getElementById('deleteEmailMobileTopForm_{{ $selectedEmail->id }}').submit(), 'Ya, Hapus ke Sampah', true)" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition-colors" title="Hapus ke Sampah">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </form>
                </div>
            </div>

            <div class="flex-1 p-3 sm:p-6 lg:p-8 max-w-4xl mx-auto w-full space-y-4 sm:space-y-6">
                
                <!-- ================= KOTAK UTUH EMAIL (Subjek, Pengirim, Bodi & Lampiran Menyatu) ================= -->
                <article class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                    
                    <!-- 1. Header Subjek, Metadata & Toolbar Aksi -->
                    <div class="p-4 sm:p-6 space-y-4">
                        <!-- Baris 1: Area Judul Subjek & Metadata (Full Width Tanpa Terhimpit) -->
                        <div class="space-y-2">
                            <h1 class="text-lg sm:text-2xl font-bold text-slate-900 tracking-tight leading-snug break-words">
                                {{ $selectedEmail->subject ?: '(Tanpa Subjek)' }}
                            </h1>
                            
                            <div class="flex items-center flex-wrap gap-2 text-xs text-slate-500">
                                <span class="px-2.5 py-1 rounded-lg bg-blue-50 font-semibold text-blue-700 text-[11px] border border-blue-100 flex items-center space-x-1">
                                    <i data-lucide="folder" class="w-3 h-3 text-blue-600"></i>
                                    <span>{{ $currentFolder->name ?? 'Kotak Masuk' }}</span>
                                </span>
                                
                                <span>•</span>
                                
                                <span class="flex items-center space-x-1 text-slate-600 font-medium">
                                    <i data-lucide="clock" class="w-3.5 h-3.5 text-slate-400"></i>
                                    <span>{{ $selectedEmail->sent_at ? $selectedEmail->sent_at->isoFormat('dddd, D MMMM Y • HH:mm') . ' WIB' : 'Waktu tidak diketahui' }}</span>
                                </span>

                                @if($selectedEmail->has_attachments)
                                    <span>•</span>
                                    <span class="inline-flex items-center space-x-1 text-purple-700 font-semibold text-[11px] bg-purple-50 px-2.5 py-0.5 rounded-lg border border-purple-100">
                                        <i data-lucide="paperclip" class="w-3 h-3 text-purple-600"></i>
                                        <span>{{ $selectedEmail->attachments->count() }} Lampiran</span>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Baris 2: Bilah Tombol Aksi (Dedicated Action Toolbar Baris Tersendiri) -->
                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between flex-wrap gap-2">
                            <!-- Grup Tombol Balasan & Teruskan -->
                            <div class="flex items-center space-x-2 flex-wrap gap-y-1.5">
                                <button onclick="scrollToQuickReply(false)" 
                                        class="inline-flex items-center space-x-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-blue-50 hover:text-blue-700 hover:border-blue-200 rounded-xl shadow-xs transition-colors"
                                        title="{{ $isSelfSender ? 'Kirim balasan/tindak lanjut ke penerima' : 'Balas ke pengirim saja' }}">
                                    <i data-lucide="reply" class="w-3.5 h-3.5 text-blue-600"></i>
                                    <span>Balas</span>
                                </button>

                                <button onclick="scrollToQuickReply(true)" 
                                        class="inline-flex items-center space-x-1.5 px-3 py-1.5 text-xs font-semibold {{ $hasMultipleRecipients ? 'text-indigo-700 bg-indigo-50/80 border-indigo-200 hover:bg-indigo-100 hover:text-indigo-800' : 'text-slate-700 bg-white border-slate-200 hover:bg-slate-50' }} border rounded-xl shadow-xs transition-colors"
                                        title="Balas ke semua (pengirim dan seluruh penerima)">
                                    <i data-lucide="reply-all" class="w-3.5 h-3.5 {{ $hasMultipleRecipients ? 'text-indigo-600' : 'text-slate-500' }}"></i>
                                    <span>Balas Semua</span>
                                    @if($hasMultipleRecipients)
                                        <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-indigo-600 text-white font-bold ml-0.5">{{ $replyAllCount }}</span>
                                    @endif
                                </button>

                                <button onclick="openForwardModal()" 
                                        class="inline-flex items-center space-x-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-indigo-50 hover:text-indigo-700 hover:border-indigo-200 rounded-xl shadow-xs transition-colors"
                                        title="Teruskan pesan ini ke alamat email lain">
                                    <i data-lucide="forward" class="w-3.5 h-3.5 text-indigo-600"></i>
                                    <span>Teruskan</span>
                                </button>
                            </div>

                            <!-- Grup Tombol Utilitas (Tandai Belum Dibaca, Cetak, Hapus) -->
                            <div class="flex items-center space-x-1.5 flex-wrap gap-y-1.5">
                                <button onclick="markCurrentAsUnread({{ $selectedEmail->id }})" 
                                        class="inline-flex items-center space-x-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 hover:text-blue-600 rounded-xl shadow-xs transition-colors"
                                        title="Tandai Belum Dibaca">
                                    <i data-lucide="mail" class="w-3.5 h-3.5 text-slate-500"></i>
                                    <span class="hidden sm:inline">Tandai Belum Dibaca</span>
                                </button>

                                <button onclick="window.print()" 
                                        class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition-colors hidden sm:inline-flex" title="Cetak Surat">
                                    <i data-lucide="printer" class="w-4 h-4"></i>
                                </button>

                                <form id="deleteEmailDetailForm_{{ $selectedEmail->id }}" action="{{ route('webmail.destroy', $selectedEmail->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="page" value="{{ request('page') }}">
                                    <input type="hidden" name="folder" value="{{ $currentFolder->slug ?? 'inbox' }}">
                                    <button type="button" onclick="showConfirmModal('Pindahkan ke Sampah?', 'Apakah Anda yakin ingin memindahkan email ini ke folder Sampah?', () => document.getElementById('deleteEmailDetailForm_{{ $selectedEmail->id }}').submit(), 'Ya, Hapus ke Sampah', true)" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-colors" title="Hapus ke Sampah">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Kartu Pengirim & Penerima Lengkap -->
                        <div class="pt-4 border-t border-slate-100 flex items-start space-x-3.5">
                            @php
                                $readAvatarStyle = getAvatarColor($selectedEmail->from_email ?: $selectedEmail->from_name);
                                $readInitials = strtoupper(substr($selectedEmail->from_name ?: $selectedEmail->from_email, 0, 2));
                            @endphp
                            <div class="w-11 h-11 rounded-2xl flex items-center justify-center font-bold text-sm border {{ $readAvatarStyle['bg'] }} shadow-xs flex-shrink-0">
                                {{ $readInitials }}
                            </div>

                            <div class="flex-1 min-w-0 text-xs">
                                <div class="flex items-baseline justify-between flex-wrap gap-1">
                                    <div class="font-bold text-sm text-slate-900">
                                        {{ $selectedEmail->from_name ?: $selectedEmail->from_email }}
                                    </div>
                                    <div class="text-[11px] text-slate-400 font-mono bg-slate-50 px-2 py-0.5 rounded border border-slate-200">
                                        &lt;{{ $selectedEmail->from_email }}&gt;
                                    </div>
                                </div>

                                <div class="text-slate-500 text-xs mt-1.5 flex items-start space-x-1.5 flex-wrap">
                                    <span class="font-semibold text-slate-600">Kepada:</span>
                                    @if(is_array($selectedEmail->to))
                                        @foreach($selectedEmail->to as $rec)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-mono text-[11px] mr-1 mb-1 border border-slate-200/60">
                                                {{ is_array($rec) ? ($rec['email'] ?? '') : $rec }}
                                            </span>
                                        @endforeach
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-mono text-[11px] border border-slate-200/60">
                                            {{ $selectedEmail->to }}
                                        </span>
                                    @endif

                                    @if(!empty($selectedEmail->cc))
                                        <span class="font-semibold text-slate-600 ml-2">Cc:</span>
                                        @foreach((array)$selectedEmail->cc as $cc)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-mono text-[11px] mr-1 mb-1 border border-slate-200/60">
                                                {{ is_array($cc) ? ($cc['email'] ?? '') : $cc }}
                                            </span>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Bodi Surat (Menyatu dalam satu wadah kotak yang sama) -->
                    <div class="p-4 sm:p-7 lg:p-9 border-t border-slate-100 overflow-x-auto">
                        <style>
                            .email-rendered-body {
                                word-break: break-word;
                                overflow-wrap: break-word;
                            }
                            .email-rendered-body img {
                                max-width: 100% !important;
                                height: auto !important;
                                border-radius: 0.75rem;
                                box-shadow: 0 2px 6px 0 rgba(0, 0, 0, 0.06);
                                margin-top: 0.75rem;
                                margin-bottom: 0.75rem;
                                display: inline-block;
                            }
                            .email-rendered-body table {
                                max-width: 100% !important;
                            }
                            .email-rendered-body pre {
                                white-space: pre-wrap !important;
                                word-break: break-word !important;
                            }
                        </style>
                        <div class="prose prose-slate max-w-none text-slate-800 text-[15px] leading-relaxed select-text font-normal email-rendered-body">
                            @if($selectedEmail->body_html)
                                {!! $selectedEmail->rendered_body_html !!}
                            @else
                                <pre class="font-sans whitespace-pre-wrap text-slate-800 text-[15px] leading-relaxed">{{ $selectedEmail->body_text }}</pre>
                            @endif
                        </div>
                    </div>

                    <!-- 3. Bagian Lampiran Berkas (Menyatu di bagian bawah bodi email) -->
                    @if($selectedEmail->attachments && $selectedEmail->attachments->count() > 0)
                        <div class="p-4 sm:p-6 border-t border-slate-100 bg-slate-50/50 space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center space-x-1.5">
                                    <i data-lucide="paperclip" class="w-4 h-4 text-blue-600"></i>
                                    <span>Lampiran Berkas ({{ $selectedEmail->attachments->count() }})</span>
                                </h3>
                                <span class="text-[11px] text-slate-400">Total: {{ round($selectedEmail->attachments->sum('size_bytes') / 1024, 1) }} KB</span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                                @foreach($selectedEmail->attachments as $att)
                                    @php
                                        $displayFilename = $att->download_filename ?: $att->filename;
                                        $ext = strtolower(pathinfo($displayFilename, PATHINFO_EXTENSION));
                                        $fileColor = match($ext) {
                                            'pdf' => 'bg-rose-100 text-rose-700 border-rose-200',
                                            'doc', 'docx' => 'bg-blue-100 text-blue-700 border-blue-200',
                                            'xls', 'xlsx' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                            'png', 'jpg', 'jpeg', 'webp' => 'bg-purple-100 text-purple-700 border-purple-200',
                                            'zip', 'rar', 'gz' => 'bg-amber-100 text-amber-800 border-amber-200',
                                            default => 'bg-slate-100 text-slate-700 border-slate-200'
                                        };
                                        $isPreviewable = in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'pdf', 'svg']);
                                    @endphp
                                    <div class="flex items-center justify-between p-3 rounded-xl border border-slate-200/80 bg-white hover:bg-slate-100/70 transition-all group">
                                        <div class="flex items-center space-x-3 truncate">
                                            <div class="w-10 h-10 rounded-xl flex items-center justify-center font-bold text-xs uppercase border {{ $fileColor }} flex-shrink-0 shadow-2xs">
                                                {{ $ext ?: 'FILE' }}
                                            </div>
                                            <div class="truncate text-xs">
                                                <div class="font-bold text-slate-800 truncate" title="{{ $displayFilename }}">
                                                    {{ $displayFilename }}
                                                </div>
                                                <div class="text-[11px] text-slate-400">
                                                    {{ round($att->size_bytes / 1024, 1) }} KB
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex items-center space-x-1.5 flex-shrink-0">
                                            @if($isPreviewable)
                                                <a href="{{ route('webmail.attachment.inline', $att->id) }}" target="_blank"
                                                   class="inline-flex items-center space-x-1 px-2.5 py-1.5 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200/80 rounded-lg transition-colors"
                                                   title="Buka pratinjau di tab baru">
                                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                                    <span class="hidden sm:inline">Lihat</span>
                                                </a>
                                            @endif
                                            <a href="{{ route('webmail.attachment.download', $att->id) }}" 
                                               class="inline-flex items-center space-x-1 px-3 py-1.5 text-xs font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors flex-shrink-0">
                                                <i data-lucide="download" class="w-3.5 h-3.5"></i>
                                                <span>Unduh</span>
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                </article>

                <!-- ================= INLINE QUICK REPLY (Chat-like, Cepat & Praktis) ================= -->
                <div id="quickReplySection" class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-4 sm:p-5 space-y-3.5 transition-all">
                    <form action="{{ route('webmail.quick-reply') }}" method="POST" enctype="multipart/form-data" class="space-y-3" id="quickReplyForm">
                        @csrf
                        <input type="hidden" name="email_id" value="{{ $selectedEmail->id }}">
                        <input type="hidden" name="is_reply_all" id="quickReplyIsReplyAll" value="0">

                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 border-b border-slate-100 pb-3">
                            <div class="flex items-center space-x-2 flex-wrap gap-y-1">
                                <div class="w-7 h-7 rounded-full bg-blue-600 text-white font-bold text-xs flex items-center justify-center shadow-2xs flex-shrink-0">
                                    {{ strtoupper(substr($currentAccount->username, 0, 1)) }}
                                </div>

                                <!-- Switcher Mode Balas / Balas Semua -->
                                <div class="inline-flex p-1 bg-slate-100/90 rounded-xl text-xs font-semibold">
                                    <button type="button" id="btnModeReplySingle" onclick="setQuickReplyMode(false)"
                                            class="px-3 py-1 rounded-lg bg-white text-blue-700 shadow-2xs font-bold transition-all flex items-center space-x-1.5 cursor-pointer">
                                        <i data-lucide="reply" class="w-3.5 h-3.5"></i>
                                        <span>Balas</span>
                                    </button>
                                    <button type="button" id="btnModeReplyAll" onclick="setQuickReplyMode(true)"
                                            class="px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900 transition-all flex items-center space-x-1.5 cursor-pointer">
                                        <i data-lucide="reply-all" class="w-3.5 h-3.5"></i>
                                        <span>Balas Semua</span>
                                        @if($hasMultipleRecipients)
                                            <span id="badgeReplyAllCount" class="px-1.5 py-0.2 rounded-full text-[10px] bg-indigo-100 text-indigo-700 font-bold ml-0.5">{{ $replyAllCount }}</span>
                                        @endif
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center space-x-3 text-xs">
                                <button type="button" onclick="openCurrentReplyInComposer()"
                                        class="inline-flex items-center space-x-1 text-slate-500 hover:text-blue-600 font-medium transition-colors cursor-pointer">
                                    <i data-lucide="maximize-2" class="w-3.5 h-3.5"></i>
                                    <span>Buka di Editor Lengkap</span>
                                </button>
                                <span class="text-[11px] text-slate-400 font-mono hidden sm:inline">• Ctrl + Enter</span>
                            </div>
                        </div>

                        <!-- Target Penerima Info Single -->
                        <div id="replyTargetSingle" class="text-xs text-slate-600 flex items-center space-x-1.5 flex-wrap">
                            <span class="font-semibold text-slate-500">Kepada:</span>
                            @foreach($replySingleData['to'] as $targetTo)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-blue-50 text-blue-700 font-mono text-[11px] border border-blue-200/60 font-semibold mr-1">
                                    {{ $targetTo }}
                                </span>
                            @endforeach
                        </div>

                        <!-- Target Penerima Info All -->
                        <div id="replyTargetAll" class="hidden text-xs text-slate-600 space-y-1.5 bg-slate-50/80 p-3 rounded-xl border border-slate-200/70">
                            <div class="flex items-start space-x-2 flex-wrap gap-y-1">
                                <span class="font-bold text-slate-600 text-[11px] pt-0.5">Kepada:</span>
                                @foreach($replyAllData['to'] as $toEmail)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-blue-50 text-blue-700 font-mono text-[11px] border border-blue-200/60 font-semibold">
                                        {{ $toEmail }}
                                    </span>
                                @endforeach
                            </div>
                            @if(!empty($replyAllData['cc']))
                                <div class="flex items-start space-x-2 flex-wrap gap-y-1 pt-1 border-t border-slate-200/50">
                                    <span class="font-bold text-slate-600 text-[11px] pt-0.5">Cc:</span>
                                    @foreach($replyAllData['cc'] as $ccEmail)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 font-mono text-[11px] border border-indigo-200/60 font-medium">
                                            {{ $ccEmail }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <textarea id="quickReplyBody" name="body_html" rows="4" required 
                                  placeholder="Ketik balasan Anda di sini..."
                                  class="w-full p-3.5 text-sm bg-slate-50/70 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:bg-white text-slate-800 leading-relaxed placeholder:text-slate-400 transition-all"></textarea>

                        <!-- Pratinjau Daftar Berkas Lampiran (Chips List) -->
                        <div id="replyFileList" class="hidden flex-wrap gap-2 pt-1 pb-1"></div>

                        <!-- Footer Action & Attachment Button -->
                        <div class="flex items-center justify-between pt-1">
                            <div class="flex items-center space-x-2">
                                <!-- Tombol Lampirkan Berkas -->
                                <label for="replyAttachmentInput" 
                                       class="cursor-pointer inline-flex items-center space-x-1.5 px-3 py-2 text-xs font-semibold text-slate-700 bg-slate-100/90 hover:bg-blue-50 hover:text-blue-700 hover:border-blue-200 border border-slate-200 rounded-xl transition-all shadow-2xs group"
                                       title="Tambah berkas lampiran">
                                    <i data-lucide="paperclip" class="w-4 h-4 text-slate-500 group-hover:text-blue-600 transition-colors"></i>
                                    <span>Lampirkan Berkas</span>
                                    <span id="replyAttachBadge" class="hidden px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-blue-600 text-white ml-0.5">0</span>
                                </label>
                                <input type="file" id="replyAttachmentInput" name="attachments[]" multiple class="hidden" onchange="handleReplyFilesChange(this)">

                                <span class="text-xs text-slate-400 hidden sm:inline">Bisa pilih lebih dari satu berkas</span>
                            </div>

                            <button type="submit" id="quickReplySubmitBtn"
                                    class="inline-flex items-center space-x-2 px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl text-xs font-bold shadow-sm shadow-blue-500/25 transition-all transform active:scale-98 cursor-pointer">
                                <i data-lucide="send" class="w-3.5 h-3.5"></i>
                                <span id="quickReplySubmitText">Kirim Balasan</span>
                            </button>
                    </form>
                </div>

            </div>
        @else
            <!-- Empty State yang Bersahabat & Ceria -->
            <div class="flex-1 flex flex-col items-center justify-center p-8 text-center max-w-md mx-auto">
                <div class="w-20 h-20 rounded-3xl bg-gradient-to-tr from-blue-100 to-indigo-100 text-blue-600 flex items-center justify-center mb-5 shadow-xs">
                    <i data-lucide="mail-open" class="w-10 h-10"></i>
                </div>
                <h3 class="text-base font-bold text-slate-800 mb-1.5">Pilih email untuk mulai membaca</h3>
                <p class="text-xs text-slate-400 leading-relaxed mb-6">
                    Pilih salah satu surat dari daftar di sebelah kiri untuk melihat isi pesan, atau tekan tombol pintasan <kbd class="font-mono bg-white px-2 py-0.5 rounded border border-slate-200 text-slate-700 font-bold shadow-2xs">C</kbd> untuk membuat pesan baru.
                </p>
                <div class="flex items-center space-x-3">
                    <button onclick="openComposer()" class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl text-xs font-bold shadow-sm hover:from-blue-700 hover:to-indigo-700 transition-all flex items-center space-x-2">
                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                        <span>Tulis Pesan Baru</span>
                    </button>
                </div>
            </div>
        @endif
    </main>
</div>

<!-- ================= 4. FLOATING DOCKED COMPOSER (Modern, Rapi & Elegan) ================= -->
<div id="composerModal" class="hidden fixed inset-x-0 bottom-0 sm:inset-x-auto sm:bottom-4 sm:right-6 w-full sm:max-w-xl bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl border border-slate-200 z-50 overflow-hidden flex flex-col transition-all max-h-[92vh] sm:max-h-[85vh]">
    <!-- Header Modal Docked -->
    <div class="bg-slate-900 text-white px-4 py-3 flex items-center justify-between">
        <div class="flex items-center space-x-2">
            <i data-lucide="edit-3" class="w-3.5 h-3.5 text-blue-400"></i>
            <span class="text-xs font-bold tracking-tight">Pesan Baru</span>
        </div>
        <div class="flex items-center space-x-1.5">
            <button onclick="minimizeComposer()" class="p-1 text-slate-400 hover:text-white rounded-lg hover:bg-slate-700 transition-colors" title="Perkecil / Minimize">
                <i data-lucide="minus" class="w-3.5 h-3.5"></i>
            </button>
            <button onclick="closeComposer()" class="p-1 text-slate-400 hover:text-white rounded-lg hover:bg-slate-700 transition-colors" title="Tutup">
                <i data-lucide="x" class="w-3.5 h-3.5"></i>
            </button>
        </div>
    </div>

    <!-- Form Compose Full -->
    <div id="composerBodyContainer" class="p-4 sm:p-5 space-y-4 overflow-y-auto max-h-[calc(92vh-55px)]">
        <form id="composeForm" action="{{ route('webmail.send') }}" method="POST" enctype="multipart/form-data" class="space-y-3.5">
            @csrf
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider">Kepada:</label>
                    <div class="flex items-center space-x-2">
                        <button type="button" onclick="toggleCcField()" id="toggleCcBtn" class="text-[11px] font-semibold text-blue-600 hover:text-blue-800 hover:underline">Cc</button>
                        <button type="button" onclick="toggleBccField()" id="toggleBccBtn" class="text-[11px] font-semibold text-blue-600 hover:text-blue-800 hover:underline">Bcc</button>
                    </div>
                </div>
                <input type="text" id="composeTo" name="to" required placeholder="penerima@perusahaan.com, rekan@gmail.com"
                       class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:bg-white font-medium text-slate-800 transition-all">
                <p class="text-[10px] text-slate-400 mt-1">Kirim ke banyak alamat sekaligus (pisahkan dengan koma <code>,</code> atau titik koma <code>;</code> atau spasi).</p>
                
                <!-- Quick Suggestion Chips from GAL -->
                @if(isset($galContacts) && $galContacts->count() > 0)
                    <div class="flex items-center space-x-1.5 mt-2 text-[10px] text-slate-400 flex-wrap gap-y-1">
                        <span class="font-semibold text-slate-500">Direktori GAL:</span>
                        @foreach($galContacts->take(4) as $c)
                            <button type="button" onclick="addRecipient('{{ $c->email }}')" class="hover:text-blue-700 bg-slate-100 hover:bg-blue-50 px-2 py-0.5 rounded-md text-slate-600 transition-colors border border-slate-200/60">
                                + {{ $c->name }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- CC Container -->
            <div id="ccContainer" class="hidden">
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider">Cc (Salinan):</label>
                    <button type="button" onclick="toggleCcField()" class="text-[10px] text-slate-400 hover:text-rose-600">&times; Sembunyikan</button>
                </div>
                <input type="text" id="composeCc" name="cc" placeholder="cc1@perusahaan.com, cc2@gmail.com"
                       class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:bg-white font-medium text-slate-800 transition-all">
            </div>

            <!-- BCC Container -->
            <div id="bccContainer" class="hidden">
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider">Bcc (Salinan Rahasia):</label>
                    <button type="button" onclick="toggleBccField()" class="text-[10px] text-slate-400 hover:text-rose-600">&times; Sembunyikan</button>
                </div>
                <input type="text" id="composeBcc" name="bcc" placeholder="bcc1@perusahaan.com, bcc2@gmail.com"
                       class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:bg-white font-medium text-slate-800 transition-all">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Subjek:</label>
                <input type="text" id="composeSubject" name="subject" required placeholder="Subjek email..."
                       class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:bg-white font-semibold text-slate-800 transition-all">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Isi Pesan:</label>
                <textarea id="composeBody" name="body_html" rows="7" required placeholder="Tuliskan pesan lengkap Anda di sini..."
                          class="w-full p-3.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:bg-white leading-relaxed text-slate-800 transition-all"></textarea>
            </div>

            <!-- Forward Attachments Section -->
            <div id="forwardAttachmentsContainer" class="hidden space-y-1.5 p-3 bg-indigo-50/70 border border-indigo-100 rounded-xl">
                <div class="flex items-center justify-between text-[11px] font-bold text-indigo-900">
                    <span class="flex items-center space-x-1.5">
                        <i data-lucide="paperclip" class="w-3.5 h-3.5 text-indigo-600"></i>
                        <span>Lampiran dari Email Asli yang Diteruskan:</span>
                    </span>
                    <span class="text-[10px] text-indigo-500 font-normal">Klik &times; jika ingin membatalkan lampiran</span>
                </div>
                <div id="forwardAttachmentsList" class="flex flex-wrap gap-1.5 pt-1"></div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Lampiran Berkas:</label>
                <input type="file" name="attachments[]" multiple class="text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[11px] file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
            </div>

            <!-- Footer Action -->
            <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                <button type="button" onclick="closeComposer()" class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-800 transition-colors">
                    Batal
                </button>

                <button type="submit" class="flex items-center space-x-2 px-5 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl text-xs font-bold shadow-sm shadow-blue-500/25 transition-all">
                    <i data-lucide="send" class="w-3.5 h-3.5"></i>
                    <span>Kirim Surat</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= 5. COMMAND PALETTE (Ctrl+K) ================= -->
<div id="commandPalette" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-xs z-50 flex items-start justify-center pt-20 p-4">
    <div class="w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden space-y-2 p-3" onclick="event.stopPropagation()">
        <div class="relative">
            <i data-lucide="search" class="w-4 h-4 absolute left-3 top-3 text-slate-400"></i>
            <input type="text" id="cmdInput" placeholder="Ketik perintah atau cari menu..." 
                   class="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 rounded-xl border border-slate-200 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white text-slate-800">
        </div>

        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-2 pt-1">Aksi Cepat</div>
        <div class="space-y-1 text-xs">
            <button onclick="closeCommandPalette(); openComposer();" class="w-full flex items-center justify-between px-3 py-2 rounded-xl hover:bg-blue-50 text-slate-700">
                <span class="flex items-center space-x-2">
                    <i data-lucide="edit" class="w-4 h-4 text-blue-600"></i>
                    <span>Tulis Pesan Baru</span>
                </span>
                <kbd class="font-mono text-[10px] bg-slate-100 border border-slate-200 px-1.5 py-0.5 rounded">C</kbd>
            </button>
            <a href="{{ route('groupware.calendar') }}" class="w-full flex items-center space-x-2 px-3 py-2 rounded-xl hover:bg-slate-100 text-slate-700">
                <i data-lucide="calendar" class="w-4 h-4 text-indigo-600"></i>
                <span>Buka Kalender & Jadwal Rapat</span>
            </a>
            @if(auth()->check() && auth()->user()->is_admin)
                <a href="{{ route('groupware.contacts') }}" class="w-full flex items-center space-x-2 px-3 py-2 rounded-xl hover:bg-slate-100 text-slate-700">
                    <i data-lucide="users" class="w-4 h-4 text-emerald-600"></i>
                    <span>Buka Direktori Kontak (GAL)</span>
                </a>
                <a href="{{ route('admin.dashboard') }}" class="w-full flex items-center space-x-2 px-3 py-2 rounded-xl hover:bg-slate-100 text-slate-700">
                    <i data-lucide="shield" class="w-4 h-4 text-amber-600"></i>
                    <span>Administration Console</span>
                </a>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
    // 0. Mobile Folder Drawer Controls
    function openFolderDrawer() {
        const sidebar = document.getElementById('folderSidebar');
        const backdrop = document.getElementById('mobileFolderBackdrop');
        if (sidebar && backdrop) {
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            backdrop.classList.remove('hidden');
        }
    }

    function closeFolderDrawer() {
        const sidebar = document.getElementById('folderSidebar');
        const backdrop = document.getElementById('mobileFolderBackdrop');
        if (sidebar && backdrop) {
            sidebar.classList.remove('translate-x-0');
            sidebar.classList.add('-translate-x-full');
            backdrop.classList.add('hidden');
        }
    }

    // 1. Composer Controls
    function openComposer() {
        document.getElementById('composerModal').classList.remove('hidden');
        document.getElementById('composerBodyContainer').classList.remove('hidden');
        const forwardAttContainer = document.getElementById('forwardAttachmentsContainer');
        const forwardAttList = document.getElementById('forwardAttachmentsList');
        if (forwardAttContainer) forwardAttContainer.classList.add('hidden');
        if (forwardAttList) forwardAttList.innerHTML = '';
        document.getElementById('composeTo').focus();
    }

    function closeComposer() {
        document.getElementById('composerModal').classList.add('hidden');
        const forwardAttContainer = document.getElementById('forwardAttachmentsContainer');
        const forwardAttList = document.getElementById('forwardAttachmentsList');
        if (forwardAttContainer) forwardAttContainer.classList.add('hidden');
        if (forwardAttList) forwardAttList.innerHTML = '';
    }

    function minimizeComposer() {
        const body = document.getElementById('composerBodyContainer');
        body.classList.toggle('hidden');
    }

    function toggleCcField() {
        const container = document.getElementById('ccContainer');
        if (container) {
            container.classList.toggle('hidden');
            if (!container.classList.contains('hidden')) {
                document.getElementById('composeCc').focus();
            }
        }
    }

    function toggleBccField() {
        const container = document.getElementById('bccContainer');
        if (container) {
            container.classList.toggle('hidden');
            if (!container.classList.contains('hidden')) {
                document.getElementById('composeBcc').focus();
            }
        }
    }

    function addRecipient(email, field = 'composeTo') {
        const toInput = document.getElementById(field);
        if (!toInput) return;
        const currentVal = toInput.value.trim();
        if (currentVal === '') {
            toInput.value = email;
        } else {
            toInput.value = currentVal.replace(/[,; ]+$/, '') + ', ' + email;
        }
        toInput.focus();
    }

    function scrollToQuickReply(isReplyAll = false) {
        setQuickReplyMode(isReplyAll);
        const section = document.getElementById('quickReplySection');
        if (section) {
            section.scrollIntoView({ behavior: 'smooth' });
            const body = document.getElementById('quickReplyBody');
            if (body) body.focus();
        }
    }

    function setQuickReplyMode(isReplyAll) {
        const input = document.getElementById('quickReplyIsReplyAll');
        const btnSingle = document.getElementById('btnModeReplySingle');
        const btnAll = document.getElementById('btnModeReplyAll');
        const targetSingle = document.getElementById('replyTargetSingle');
        const targetAll = document.getElementById('replyTargetAll');
        const submitText = document.getElementById('quickReplySubmitText');

        if (!input || !btnSingle || !btnAll) return;

        if (isReplyAll) {
            input.value = "1";
            btnAll.className = "px-3 py-1 rounded-lg bg-indigo-600 text-white shadow-2xs font-bold transition-all flex items-center space-x-1.5 cursor-pointer";
            btnSingle.className = "px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900 transition-all flex items-center space-x-1.5 cursor-pointer";
            if (targetSingle) targetSingle.classList.add('hidden');
            if (targetAll) targetAll.classList.remove('hidden');
            if (submitText) submitText.textContent = "Kirim ke Semua";
        } else {
            input.value = "0";
            btnSingle.className = "px-3 py-1 rounded-lg bg-white text-blue-700 shadow-2xs font-bold transition-all flex items-center space-x-1.5 cursor-pointer";
            btnAll.className = "px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900 transition-all flex items-center space-x-1.5 cursor-pointer";
            if (targetSingle) targetSingle.classList.remove('hidden');
            if (targetAll) targetAll.classList.add('hidden');
            if (submitText) submitText.textContent = "Kirim Balasan";
        }
        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    function openReplyAllComposer(to, cc, subject) {
        document.getElementById('composerModal').classList.remove('hidden');
        document.getElementById('composerBodyContainer').classList.remove('hidden');
        const toInput = document.getElementById('composeTo');
        if (toInput) toInput.value = to;

        const ccContainer = document.getElementById('ccContainer');
        const ccInput = document.getElementById('composeCc');
        if (cc && cc.trim() !== '') {
            if (ccContainer) ccContainer.classList.remove('hidden');
            if (ccInput) ccInput.value = cc;
        }

        const subjInput = document.getElementById('composeSubject');
        if (subjInput) subjInput.value = subject;

        const bodyInput = document.getElementById('composeBody');
        if (bodyInput) bodyInput.focus();
    }

    function openForwardModal() {
        if (!window.currentEmailForward) return;
        document.getElementById('composerModal').classList.remove('hidden');
        document.getElementById('composerBodyContainer').classList.remove('hidden');

        const toInput = document.getElementById('composeTo');
        if (toInput) {
            toInput.value = '';
            toInput.focus();
        }

        const ccContainer = document.getElementById('ccContainer');
        if (ccContainer) ccContainer.classList.add('hidden');
        const ccInput = document.getElementById('composeCc');
        if (ccInput) ccInput.value = '';

        const bccContainer = document.getElementById('bccContainer');
        if (bccContainer) bccContainer.classList.add('hidden');
        const bccInput = document.getElementById('composeBcc');
        if (bccInput) bccInput.value = '';

        const subjInput = document.getElementById('composeSubject');
        if (subjInput) subjInput.value = window.currentEmailForward.subject;

        const bodyInput = document.getElementById('composeBody');
        if (bodyInput) bodyInput.value = window.currentEmailForward.body;

        const forwardAttContainer = document.getElementById('forwardAttachmentsContainer');
        const forwardAttList = document.getElementById('forwardAttachmentsList');
        if (forwardAttContainer && forwardAttList) {
            forwardAttList.innerHTML = '';
            if (window.currentEmailForward.attachments && window.currentEmailForward.attachments.length > 0) {
                forwardAttContainer.classList.remove('hidden');
                window.currentEmailForward.attachments.forEach(att => {
                    const badge = document.createElement('div');
                    badge.className = 'inline-flex items-center space-x-1.5 bg-white text-indigo-700 border border-indigo-200 px-2.5 py-1 rounded-lg text-xs font-medium shadow-2xs';
                    badge.innerHTML = `
                        <i data-lucide="paperclip" class="w-3.5 h-3.5 text-indigo-500"></i>
                        <span>${att.name}</span>
                        <span class="text-[10px] text-slate-400">(${att.size})</span>
                        <input type="hidden" name="forward_attachment_ids[]" value="${att.id}">
                        <button type="button" onclick="this.parentElement.remove()" class="text-slate-400 hover:text-rose-600 transition-colors ml-1 font-bold" title="Hapus berkas ini">&times;</button>
                    `;
                    forwardAttList.appendChild(badge);
                });
                if (window.lucide) window.lucide.createIcons();
            } else {
                forwardAttContainer.classList.add('hidden');
            }
        }
    }

    function openCurrentReplyInComposer() {
        const isReplyAll = document.getElementById('quickReplyIsReplyAll')?.value === '1';
        if (isReplyAll && window.currentEmailReplyAll) {
            openReplyAllComposer(
                window.currentEmailReplyAll.to,
                window.currentEmailReplyAll.cc,
                window.currentEmailReplyAll.subject
            );
        } else if (window.currentEmailReplySingle) {
            openReplyAllComposer(
                window.currentEmailReplySingle.to,
                '',
                window.currentEmailReplySingle.subject
            );
        }
    }

    // 2. Command Palette (Ctrl+K)
    function openCommandPalette() {
        document.getElementById('commandPalette').classList.remove('hidden');
        document.getElementById('cmdInput').focus();
    }

    function closeCommandPalette() {
        document.getElementById('commandPalette').classList.add('hidden');
    }

    document.getElementById('commandPalette').addEventListener('click', closeCommandPalette);

    // 3. Keyboard Shortcuts Listener
    document.addEventListener('keydown', (e) => {
        const activeTag = document.activeElement.tagName.toLowerCase();
        const isInput = (activeTag === 'input' || activeTag === 'textarea');

        // Ctrl + K -> Open Command Palette
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            openCommandPalette();
            return;
        }

        // Ctrl + Enter inside Quick Reply -> Submit Form
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            const qrBody = document.getElementById('quickReplyBody');
            if (document.activeElement === qrBody && qrBody.form) {
                e.preventDefault();
                qrBody.form.submit();
            }
        }

        // Escape -> Close Modals or Clear Search
        if (e.key === 'Escape') {
            closeComposer();
            closeCommandPalette();
            const sInput = document.getElementById('searchInput');
            if (document.activeElement === sInput) {
                if (sInput.value !== '') {
                    clearLiveSearch();
                } else {
                    sInput.blur();
                }
            }
        }

        // Single-key shortcuts when not typing:
        if (!isInput) {
            if (e.key.toLowerCase() === 'c') {
                e.preventDefault();
                openComposer();
            } else if (e.key.toLowerCase() === 'r') {
                e.preventDefault();
                scrollToQuickReply(false);
            } else if (e.key.toLowerCase() === 'a') {
                e.preventDefault();
                scrollToQuickReply(true);
            } else if (e.key === '/') {
                e.preventDefault();
                const sInput = document.getElementById('searchInput');
                if (sInput) {
                    sInput.focus();
                    sInput.select();
                }
            }
        }
    });

    // 4. Live Search & Asynchronous Pagination
    let searchDebounceTimer = null;
    let activeSearchController = null;

    function onSearchInputChange() {
        const input = document.getElementById('searchInput');
        const clearBtn = document.getElementById('searchClearBtn');
        const kbdBadge = document.getElementById('searchKbdBadge');
        if (!input) return;

        const val = input.value;
        if (val.trim().length > 0) {
            if (clearBtn) clearBtn.classList.remove('hidden');
            if (kbdBadge) kbdBadge.classList.add('hidden');
        } else {
            if (clearBtn) clearBtn.classList.add('hidden');
            if (kbdBadge) kbdBadge.classList.remove('hidden');
        }

        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => {
            performLiveSearch(val);
        }, 280);
    }

    function triggerSearchNow() {
        clearTimeout(searchDebounceTimer);
        const input = document.getElementById('searchInput');
        if (input) performLiveSearch(input.value);
    }

    function clearLiveSearch() {
        const input = document.getElementById('searchInput');
        const clearBtn = document.getElementById('searchClearBtn');
        const kbdBadge = document.getElementById('searchKbdBadge');
        if (input) {
            input.value = '';
            if (clearBtn) clearBtn.classList.add('hidden');
            if (kbdBadge) kbdBadge.classList.remove('hidden');
            input.focus();
        }
        performLiveSearch('');
    }

    function performLiveSearch(term) {
        if (activeSearchController) {
            activeSearchController.abort();
        }
        activeSearchController = new AbortController();

        const searchIcon = document.getElementById('searchIcon');
        const searchSpinner = document.getElementById('searchSpinner');
        const statusBar = document.getElementById('searchStatusBar');
        const queryLabel = document.getElementById('searchQueryLabel');
        const resultCount = document.getElementById('searchResultCount');
        const totalBadge = document.getElementById('emailTotalBadge');
        const listContainer = document.getElementById('emailListContainer');
        const paginationContainer = document.getElementById('emailPaginationContainer');

        if (searchIcon) searchIcon.classList.add('hidden');
        if (searchSpinner) searchSpinner.classList.remove('hidden');

        const currentFolder = "{{ $currentFolder->slug ?? 'inbox' }}";
        const currentFilter = "{{ $activeFilter }}";

        const params = new URLSearchParams();
        if (currentFolder) params.set('folder', currentFolder);
        if (currentFilter && currentFilter !== 'all') params.set('filter', currentFilter);
        if (term.trim() !== '') params.set('q', term.trim());

        const url = `{{ route('webmail.index') }}${params.toString() ? '?' + params.toString() : ''}`;

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            signal: activeSearchController.signal
        })
        .then(res => {
            if (!res.ok) throw new Error('Search request failed');
            return res.json();
        })
        .then(data => {
            if (listContainer) {
                listContainer.innerHTML = data.html;
            }
            if (paginationContainer) {
                paginationContainer.innerHTML = data.paginationHtml || '';
            }
            if (totalBadge) {
                totalBadge.textContent = data.total;
            }

            if (term.trim() !== '') {
                if (statusBar) statusBar.classList.remove('hidden');
                if (queryLabel) queryLabel.textContent = `"${term.trim()}"`;
                if (resultCount) resultCount.textContent = `${data.total} email`;
            } else {
                if (statusBar) statusBar.classList.add('hidden');
            }

            // Sync browser URL
            window.history.replaceState({}, '', url);

            // Update filter pill URLs
            const filterPills = document.querySelectorAll('.filter-pill-link');
            filterPills.forEach(pill => {
                const f = pill.getAttribute('data-filter') || 'all';
                const p = new URLSearchParams();
                if (currentFolder) p.set('folder', currentFolder);
                if (f !== 'all') p.set('filter', f);
                if (term.trim() !== '') p.set('q', term.trim());
                pill.href = `{{ route('webmail.index') }}?${p.toString()}`;
            });

            // Reset batch checkbox selection
            const master = document.getElementById('selectAllCheckbox');
            if (master) master.checked = false;
            updateBatchBar();

            // Re-render Lucide icons
            if (window.lucide) {
                window.lucide.createIcons();
            }
        })
        .catch(err => {
            if (err.name !== 'AbortError') {
                console.error('Pencarian gagal:', err);
            }
        })
        .finally(() => {
            if (searchSpinner) searchSpinner.classList.add('hidden');
            if (searchIcon) searchIcon.classList.remove('hidden');
        });
    }

    // Intercept pagination clicks for async navigation
    document.addEventListener('click', (e) => {
        const pagLink = e.target.closest('#emailPaginationContainer a');
        if (pagLink && pagLink.href) {
            e.preventDefault();
            loadPaginationAjax(pagLink.href);
        }
    });

    function loadPaginationAjax(url) {
        const listContainer = document.getElementById('emailListContainer');
        const paginationContainer = document.getElementById('emailPaginationContainer');
        const searchSpinner = document.getElementById('searchSpinner');
        const searchIcon = document.getElementById('searchIcon');

        if (searchIcon) searchIcon.classList.add('hidden');
        if (searchSpinner) searchSpinner.classList.remove('hidden');

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (listContainer) {
                listContainer.innerHTML = data.html;
                listContainer.scrollTop = 0;
            }
            if (paginationContainer) {
                paginationContainer.innerHTML = data.paginationHtml || '';
            }
            window.history.replaceState({}, '', url);
            const master = document.getElementById('selectAllCheckbox');
            if (master) master.checked = false;
            updateBatchBar();
            if (window.lucide) window.lucide.createIcons();
        })
        .finally(() => {
            if (searchSpinner) searchSpinner.classList.add('hidden');
            if (searchIcon) searchIcon.classList.remove('hidden');
        });
    }

    // 5. AJAX Toggle Star
    function toggleStarAjax(id, btn) {
        fetch(`/webmail/${id}/toggle-star`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const icon = btn.querySelector('svg');
                if (data.is_starred) {
                    icon.classList.add('fill-amber-400', 'text-amber-500');
                    icon.classList.remove('text-slate-300');
                } else {
                    icon.classList.remove('fill-amber-400', 'text-amber-500');
                    icon.classList.add('text-slate-300');
                }
            }
        });
    }

    // 6. AJAX Toggle Read/Unread
    function toggleReadAjax(id, btn) {
        fetch(`/webmail/${id}/toggle-read`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }

    // 7. Batch Actions & Selection
    function updateBatchBar() {
        const checkboxes = document.querySelectorAll('.email-checkbox:checked');
        const count = checkboxes.length;
        const toolbar = document.getElementById('batchToolbar');
        const countLabel = document.getElementById('selectedCount');

        if (count > 0) {
            toolbar.classList.remove('hidden');
            countLabel.textContent = `${count} pesan dipilih`;
        } else {
            toolbar.classList.add('hidden');
        }
    }

    function toggleSelectAll(masterCheckbox) {
        const checkboxes = document.querySelectorAll('.email-checkbox');
        checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
        updateBatchBar();
    }

    function deselectAll() {
        const checkboxes = document.querySelectorAll('.email-checkbox');
        checkboxes.forEach(cb => cb.checked = false);
        const master = document.getElementById('selectAllCheckbox');
        if (master) master.checked = false;
        updateBatchBar();
    }

    function submitBatch(action) {
        const checkedBoxes = document.querySelectorAll('.email-checkbox:checked');
        const ids = Array.from(checkedBoxes).map(cb => cb.value);

        if (ids.length === 0) return;

        if (action === 'delete') {
            showConfirmModal('Hapus Email Terpilih?', `Apakah Anda yakin ingin memindahkan ${ids.length} email yang dipilih ke folder Sampah?`, () => {
                executeBatchAction(action, ids);
            }, 'Ya, Hapus Semua', true);
            return;
        }

        executeBatchAction(action, ids);
    }

    function executeBatchAction(action, ids) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route('webmail.batch-action') }}';

        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = '{{ csrf_token() }}';
        form.appendChild(csrf);

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = action;
        form.appendChild(actionInput);

        ids.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'email_ids[]';
            input.value = id;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    }

    function handleRowClick(event, url) {
        if (event.target.closest('input[type="checkbox"]') || event.target.closest('button')) {
            return;
        }
        window.location.href = url;
    }

    function markCurrentAsUnread(id) {
        fetch('/webmail/' + id + '/toggle-read', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        }).then(() => {
            window.location.href = "{{ route('webmail.index', array_filter(['folder' => $currentFolder->slug ?? 'inbox', 'page' => request('page'), 'filter' => $activeFilter !== 'all' ? $activeFilter : null, 'q' => $query])) }}";
        });
    }

    // 8. Quick Reply Attachments
    function handleReplyFilesChange(input) {
        const container = document.getElementById('replyFileList');
        const badge = document.getElementById('replyAttachBadge');
        if (!container || !badge) return;

        container.innerHTML = '';

        if (!input.files || input.files.length === 0) {
            container.classList.add('hidden');
            container.classList.remove('flex');
            badge.classList.add('hidden');
            badge.textContent = '0';
            return;
        }

        container.classList.remove('hidden');
        container.classList.add('flex');
        badge.classList.remove('hidden');
        badge.textContent = input.files.length;

        Array.from(input.files).forEach((file, idx) => {
            const chip = document.createElement('div');
            chip.className = 'inline-flex items-center space-x-2 px-2.5 py-1.5 rounded-lg bg-blue-50 border border-blue-200 text-xs text-blue-900 shadow-2xs';

            const sizeKb = Math.round(file.size / 1024);
            const sizeDisplay = sizeKb > 1024 ? (sizeKb / 1024).toFixed(1) + ' MB' : sizeKb + ' KB';

            chip.innerHTML = `
                <i data-lucide="paperclip" class="w-3.5 h-3.5 text-blue-600 flex-shrink-0"></i>
                <span class="font-medium truncate max-w-[200px]" title="${file.name}">${file.name}</span>
                <span class="text-[10px] text-blue-600/70 font-mono">(${sizeDisplay})</span>
                <button type="button" onclick="removeReplyFile(${idx})" class="text-blue-400 hover:text-rose-600 p-0.5 rounded transition-colors ml-1" title="Hapus berkas ini">
                    <i data-lucide="x" class="w-3.5 h-3.5"></i>
                </button>
            `;
            container.appendChild(chip);
        });

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    function removeReplyFile(index) {
        const input = document.getElementById('replyAttachmentInput');
        if (!input) return;

        const dt = new DataTransfer();
        Array.from(input.files).forEach((file, idx) => {
            if (idx !== index) {
                dt.items.add(file);
            }
        });
        input.files = dt.files;
        handleReplyFilesChange(input);
    }

    // Drag & Drop files into Quick Reply box
    document.addEventListener('DOMContentLoaded', () => {
        const dropArea = document.getElementById('quickReplySection');
        if (dropArea) {
            ['dragenter', 'dragover'].forEach(name => {
                dropArea.addEventListener(name, (e) => {
                    e.preventDefault();
                    dropArea.classList.add('ring-2', 'ring-blue-500/40', 'bg-blue-50/20');
                });
            });
            ['dragleave', 'drop'].forEach(name => {
                dropArea.addEventListener(name, (e) => {
                    e.preventDefault();
                    dropArea.classList.remove('ring-2', 'ring-blue-500/40', 'bg-blue-50/20');
                });
            });
            dropArea.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                if (dt && dt.files && dt.files.length > 0) {
                    const input = document.getElementById('replyAttachmentInput');
                    if (input) {
                        const dataTransfer = new DataTransfer();
                        Array.from(input.files).forEach(f => dataTransfer.items.add(f));
                        Array.from(dt.files).forEach(f => dataTransfer.items.add(f));
                        input.files = dataTransfer.files;
                        handleReplyFilesChange(input);
                    }
                }
            });
        }
    });
</script>
@endpush
@endsection
