@extends('layouts.app')

@section('title', 'Admin Console - Ringkasan Sistem - ApexMail Admin')

@section('content')
<div class="flex-1 bg-[#f8fafc] overflow-y-auto p-3.5 sm:p-6 lg:p-8">
    <div class="max-w-6xl mx-auto space-y-6">

        <!-- Top Header Admin Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-blue-100 text-blue-800 border border-blue-200 uppercase">
                        Administration Mode
                    </span>
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Administration Web Console</h1>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Pusat kendali operasional server email, kuota mailbox, dan jalur relay pengiriman.</p>
            </div>

            <!-- Sub Navigasi Menu Admin -->
            <div class="flex items-center space-x-1.5 overflow-x-auto no-scrollbar pb-1 max-w-full">
                <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-600 text-white shadow-sm shadow-blue-500/20">
                    Ringkasan Sistem
                </a>
                <a href="{{ route('admin.accounts') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Kelola Akun ({{ $totalAccounts }})
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
                <a href="{{ route('admin.inbound') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Email Masuk (Inbound)
                </a>
                <a href="{{ route('admin.spam.index') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Spam & Pemblokiran
                </a>
                <a href="{{ route('admin.queue') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Antrean & Log
                </a>
                <a href="{{ route('admin.migration') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Migrasi Data
                </a>
            </div>
        </div>

        <!-- Metric Cards (Server Status Overview) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <!-- Total Kotak Surat -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 space-y-2">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-xs font-semibold">Total Kotak Surat</span>
                    <i data-lucide="users" class="w-4 h-4 text-blue-600"></i>
                </div>
                <div class="flex items-baseline space-x-2">
                    <span class="text-2xl font-bold text-slate-900">{{ $totalAccounts }}</span>
                    <span class="text-[11px] text-emerald-600 font-semibold">{{ $activeAccounts }} aktif</span>
                </div>
                <p class="text-[11px] text-slate-400">Tersebar di {{ $totalDomains }} domain resmi</p>
            </div>

            <!-- Kapasitas Storage Kuota (CoS) -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 space-y-2">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-xs font-semibold">Alokasi Penyimpanan</span>
                    <i data-lucide="hard-drive" class="w-4 h-4 text-indigo-600"></i>
                </div>
                @php
                    $allocGb = round($totalAllocatedQuota / 1073741824, 1);
                    $usedGb = round($totalUsedStorage / 1073741824, 2);
                @endphp
                <div class="flex items-baseline space-x-2">
                    <span class="text-2xl font-bold text-slate-900">{{ $allocGb }} GB</span>
                    <span class="text-[11px] text-slate-500">{{ $usedGb }} GB terpakai</span>
                </div>
                <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                    <div class="bg-indigo-600 h-1.5 rounded-full" style="width: 5%"></div>
                </div>
            </div>

            <!-- Trafik Email Hari Ini -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 space-y-2">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-xs font-semibold">Trafik Email Hari Ini</span>
                    <i data-lucide="send" class="w-4 h-4 text-emerald-600"></i>
                </div>
                <div class="flex items-baseline space-x-2">
                    <span class="text-2xl font-bold text-slate-900">{{ $emailsSentToday }}</span>
                    <span class="text-[11px] text-slate-400">Total: {{ $totalEmails }} pesan</span>
                </div>
                <p class="text-[11px] text-slate-400">100% tersalur via Relay / Lokal</p>
            </div>

            <!-- Antrean Surat & Status Tertahan -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 space-y-2">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-xs font-semibold">Antrean Surat (Queue)</span>
                    <i data-lucide="layers" class="w-4 h-4 text-amber-500"></i>
                </div>
                <div class="flex items-baseline space-x-2">
                    <span class="text-2xl font-bold text-slate-900">{{ $queueStats['total'] ?? $totalEmails }}</span>
                    @if(($queueStats['deferred'] ?? 0) > 0)
                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 animate-pulse">
                            {{ $queueStats['deferred'] }} Tertahan
                        </span>
                    @else
                        <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-700">
                            0 Tertahan
                        </span>
                    @endif
                </div>
                <div class="flex items-center justify-between text-[11px]">
                    <span class="text-slate-400">{{ $queueStats['delivered'] ?? 0 }} Terkirim</span>
                    <a href="{{ route('admin.queue') }}" class="text-blue-600 font-semibold hover:underline flex items-center space-x-0.5">
                        <span>Kelola</span>
                        <i data-lucide="chevron-right" class="w-3 h-3"></i>
                    </a>
                </div>
            </div>

            <!-- Status Jalur Pengiriman Outbound -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 space-y-2">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-xs font-semibold">Jalur Outbound</span>
                    <i data-lucide="send" class="w-4 h-4 text-blue-500"></i>
                </div>
                @if($activeRelay && $activeRelay->is_active)
                    <div class="flex items-center space-x-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="text-sm font-bold text-slate-900">External Relay Aktif</span>
                    </div>
                    <p class="text-[11px] text-slate-400 font-mono truncate">{{ $activeRelay->host . ':' . $activeRelay->port }}</p>
                @else
                    <div class="flex items-center space-x-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-500 animate-pulse"></span>
                        <span class="text-sm font-bold text-slate-900">Direct MX Delivery</span>
                    </div>
                    <p class="text-[11px] text-slate-400 font-mono truncate">Port 25 (Direct DNS Resolution)</p>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Kolom Kiri (2 Kolom): Service Health & Recent Delivery Logs -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Service Status Table (Server Services Health) -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center">
                            <i data-lucide="activity" class="w-4 h-4 mr-1.5 text-blue-600"></i>
                            Kesehatan Layanan Server (System Services)
                        </h3>
                        <span class="text-[11px] font-semibold text-emerald-600 flex items-center space-x-1">
                            <i data-lucide="check" class="w-3.5 h-3.5"></i>
                            <span>Semua Layanan Berjalan Normal</span>
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                        <div class="p-3 rounded-xl border border-slate-100 bg-slate-50/60 flex items-center justify-between">
                            <div class="flex items-center space-x-2.5">
                                <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                <div>
                                    <div class="text-xs font-bold text-slate-800">MTA (SMTP Service)</div>
                                    <div class="text-[10px] text-slate-400">Pengiriman Port 587 TLS</div>
                                </div>
                            </div>
                            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">RUNNING</span>
                        </div>

                        <div class="p-3 rounded-xl border border-slate-100 bg-slate-50/60 flex items-center justify-between">
                            <div class="flex items-center space-x-2.5">
                                <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                <div>
                                    <div class="text-xs font-bold text-slate-800">MDA / Store Engine</div>
                                    <div class="text-[10px] text-slate-400">Mailbox & Storage Database</div>
                                </div>
                            </div>
                            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">RUNNING</span>
                        </div>

                        <div class="p-3 rounded-xl border border-slate-100 bg-slate-50/60 flex items-center justify-between">
                            <div class="flex items-center space-x-2.5">
                                <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                <div>
                                    <div class="text-xs font-bold text-slate-800">Groupware (Cal/Card)</div>
                                    <div class="text-[10px] text-slate-400">Kalender, Kontak & GAL</div>
                                </div>
                            </div>
                            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">READY</span>
                        </div>

                        <div class="p-3 rounded-xl border border-slate-100 bg-slate-50/60 flex items-center justify-between">
                            <div class="flex items-center space-x-2.5">
                                <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                <div>
                                    <div class="text-xs font-bold text-slate-800">Security & Deliverability</div>
                                    <div class="text-[10px] text-slate-400">SPF, DKIM, DMARC Wizard</div>
                                </div>
                            </div>
                            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">VERIFIED</span>
                        </div>
                    </div>
                </div>

                <!-- Recent Mail Delivery Inspector -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Aktivitas Surat Terakhir</h3>
                        <a href="{{ route('admin.queue') }}" class="text-[11px] text-blue-600 font-semibold hover:underline">Lihat Semua Antrean →</a>
                    </div>

                    <div class="divide-y divide-slate-100 text-xs">
                        @forelse($recentEmails as $email)
                            <div class="py-2.5 flex items-center justify-between">
                                <div class="truncate pr-4">
                                    <div class="font-semibold text-slate-800 truncate">{{ $email->subject ?: '(Tanpa Subjek)' }}</div>
                                    <div class="text-[11px] text-slate-400 truncate">
                                        Dari: <span class="font-mono text-slate-600">{{ $email->from_email }}</span>
                                    </div>
                                </div>
                                <div class="text-right whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $email->delivery_status_badge_class }}">
                                        {{ $email->delivery_status_label }}
                                    </span>
                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                        {{ $email->sent_at ? $email->sent_at->diffForHumans() : '' }}
                                    </div>
                                    @if($email->delivery_error && in_array($email->delivery_status, ['deferred', 'held', 'bounced', 'failed']))
                                        <div class="text-[9px] text-rose-500 max-w-[150px] truncate mt-0.5" title="{{ $email->delivery_error }}">
                                            {{ $email->delivery_error }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-slate-400 py-4 text-center">Belum ada aktivitas lalu lintas email.</p>
                        @endforelse
                    </div>
                </div>

            </div>

            <!-- Kolom Kanan: Aksi Cepat Admin & Manajemen Akun Terbaru -->
            <div class="space-y-6">

                <!-- Quick Action Box -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Tindakan Cepat Admin</h3>
                    <div class="space-y-2">
                        <a href="{{ route('admin.accounts') }}" class="w-full flex items-center justify-between p-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors text-xs font-medium text-slate-700">
                            <span class="flex items-center space-x-2">
                                <i data-lucide="user-plus" class="w-4 h-4 text-blue-600"></i>
                                <span>Buat Akun Kotak Surat</span>
                            </span>
                            <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-slate-400"></i>
                        </a>

                        <a href="{{ route('admin.domains') }}" class="w-full flex items-center justify-between p-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors text-xs font-medium text-slate-700">
                            <span class="flex items-center space-x-2">
                                <i data-lucide="globe" class="w-4 h-4 text-indigo-600"></i>
                                <span>Tambah Domain Baru & DNS</span>
                            </span>
                            <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-slate-400"></i>
                        </a>

                        <a href="{{ route('admin.relays') }}" class="w-full flex items-center justify-between p-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors text-xs font-medium text-slate-700">
                            <span class="flex items-center space-x-2">
                                <i data-lucide="zap" class="w-4 h-4 text-amber-500"></i>
                                <span>Uji Koneksi SMTP Relay</span>
                            </span>
                            <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-slate-400"></i>
                        </a>

                        <a href="{{ route('admin.migration') }}" class="w-full flex items-center justify-between p-2.5 rounded-xl border border-indigo-200 bg-indigo-50/40 hover:bg-indigo-50 transition-colors text-xs font-semibold text-indigo-900">
                            <span class="flex items-center space-x-2">
                                <i data-lucide="archive" class="w-4 h-4 text-indigo-600"></i>
                                <span>Migrasi Mailbox & Akun</span>
                            </span>
                            <span class="px-1.5 py-0.5 rounded text-[10px] bg-indigo-600 text-white font-bold">Baru</span>
                        </a>
                    </div>
                </div>

                <!-- Akun Terdaftar Terbaru -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Akun Terdaftar Terbaru</h3>
                    <div class="divide-y divide-slate-100">
                        @foreach($recentAccounts as $acc)
                            <div class="py-2 flex items-center justify-between text-xs">
                                <div>
                                    <div class="font-bold text-slate-800">{{ $acc->display_name }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">{{ $acc->email }}</div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $acc->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $acc->is_active ? 'Aktif' : 'Suspended' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Aturan Penetapan & Kebijakan Keamanan Kata Sandi (Password Security Policy) -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                                <i data-lucide="shield-check" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Aturan Penetapan Sandi</h3>
                                <p class="text-[11px] text-slate-400">Password Security Policy</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1 animate-pulse"></span>
                            AKTIF
                        </span>
                    </div>

                    <!-- Daftar Rule / Ketentuan -->
                    <div class="space-y-2.5">
                        <!-- Rule 1: Min 8 Karakter -->
                        <div class="p-2.5 rounded-xl border border-slate-100 bg-slate-50/70 flex items-start justify-between">
                            <div class="flex items-start space-x-2.5">
                                <div class="w-5 h-5 rounded-md bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-[10px] mt-0.5 flex-shrink-0">
                                    <i data-lucide="hash" class="w-3 h-3"></i>
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-800">Panjang Minimal 8 Karakter</div>
                                    <div class="text-[10px] text-slate-500">Kata sandi wajib memiliki minimal {{ $passwordPolicy['min_length'] ?? 8 }} karakter.</div>
                                </div>
                            </div>
                            <span class="text-[10px] font-semibold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">Wajib</span>
                        </div>

                        <!-- Rule 2: Huruf Besar (A-Z) -->
                        <div class="p-2.5 rounded-xl border border-slate-100 bg-slate-50/70 flex items-start justify-between">
                            <div class="flex items-start space-x-2.5">
                                <div class="w-5 h-5 rounded-md bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-[10px] mt-0.5 flex-shrink-0">
                                    Aa
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-800">Wajib Huruf Besar (Kapital)</div>
                                    <div class="text-[10px] text-slate-500">Wajib mengandung minimal 1 karakter huruf besar (A-Z).</div>
                                </div>
                            </div>
                            <span class="text-[10px] font-semibold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">Wajib</span>
                        </div>

                        <!-- Rule 3: Simbol Unik -->
                        <div class="p-2.5 rounded-xl border border-slate-100 bg-slate-50/70 flex items-start justify-between">
                            <div class="flex items-start space-x-2.5">
                                <div class="w-5 h-5 rounded-md bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-[10px] mt-0.5 flex-shrink-0">
                                    #@
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-800">Wajib Simbol Unik / Karakter Khusus</div>
                                    <div class="text-[10px] text-slate-500">Wajib mengandung simbol khusus (!, @, #, $, %, ^, &, *, dll).</div>
                                </div>
                            </div>
                            <span class="text-[10px] font-semibold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">Wajib</span>
                        </div>

                        <!-- Rule 4: Tidak Boleh Sama dengan Sebelumnya -->
                        <div class="p-2.5 rounded-xl border border-slate-100 bg-slate-50/70 flex items-start justify-between">
                            <div class="flex items-start space-x-2.5">
                                <div class="w-5 h-5 rounded-md bg-rose-100 text-rose-700 flex items-center justify-center font-bold text-[10px] mt-0.5 flex-shrink-0">
                                    <i data-lucide="history" class="w-3 h-3"></i>
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-800">Cegah Sandi Sama Sebelumnya</div>
                                    <div class="text-[10px] text-slate-500">Sandi baru tidak boleh sama dengan kata sandi sebelumnya & riwayat {{ $passwordPolicy['history_limit'] ?? 5 }} sandi terakhir.</div>
                                </div>
                            </div>
                            <span class="text-[10px] font-semibold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">Aktif</span>
                        </div>

                        <!-- Rule 5: Verifikasi CAPTCHA Keamanan Login -->
                        <div class="p-2.5 rounded-xl border border-slate-100 bg-slate-50/70 flex items-start justify-between">
                            <div class="flex items-start space-x-2.5">
                                <div class="w-5 h-5 rounded-md bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-[10px] mt-0.5 flex-shrink-0">
                                    <i data-lucide="shield-check" class="w-3 h-3"></i>
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-800">Verifikasi CAPTCHA Login</div>
                                    <div class="text-[10px] text-slate-500">Proteksi bot & serangan brute-force pada halaman login Webmail dan Admin.</div>
                                </div>
                            </div>
                            <span class="text-[10px] font-semibold {{ ($captchaEnabled ?? true) ? 'text-emerald-600 bg-emerald-50 border-emerald-200' : 'text-slate-500 bg-slate-100 border-slate-200' }} px-1.5 py-0.5 rounded border">
                                {{ ($captchaEnabled ?? true) ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                    </div>

                    <!-- Tombol Sesuaikan / Kelola Kebijakan Modal -->
                    <div class="pt-1">
                        <button type="button" onclick="openPasswordPolicyModal()" 
                                class="w-full py-2 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition-all flex items-center justify-center space-x-1.5">
                            <i data-lucide="sliders" class="w-3.5 h-3.5 text-slate-500"></i>
                            <span>Kelola Aturan Kebijakan Sandi</span>
                        </button>
                    </div>
                </div>

            </div>

        </div>

    </div>
</div>

<!-- Modal Pengaturan Kebijakan Kata Sandi -->
<div id="passwordPolicyModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center font-bold">
                    <i data-lucide="shield" class="w-4 h-4"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Aturan Kebijakan Kata Sandi</h3>
                    <p class="text-[11px] text-slate-500">Konfigurasi standar keamanan password ApexMail</p>
                </div>
            </div>
            <button type="button" onclick="closePasswordPolicyModal()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <form action="{{ route('admin.password-policy.update') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="captcha_setting_submitted" value="1">

            <!-- Panjang Minimal -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Panjang Minimal Karakter:</label>
                <div class="relative">
                    <input type="number" name="min_length" value="{{ $passwordPolicy['min_length'] ?? 8 }}" min="6" max="32" required
                           class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white font-semibold text-slate-800">
                </div>
                <p class="text-[10px] text-slate-400 mt-1">Disarankan minimal 8 karakter sesuai standar keamanan enterprise.</p>
            </div>

            <!-- Checkbox Wajib Huruf Besar -->
            <div class="p-3 bg-slate-50 border border-slate-200/80 rounded-xl space-y-1">
                <label class="inline-flex items-center space-x-2 cursor-pointer">
                    <input type="checkbox" name="require_uppercase" value="1" {{ !empty($passwordPolicy['require_uppercase']) ? 'checked' : '' }} class="rounded text-blue-600 focus:ring-blue-500 w-4 h-4">
                    <span class="text-xs font-bold text-slate-800">Wajib Mengandung Huruf Besar (A-Z)</span>
                </label>
                <p class="text-[10px] text-slate-500 pl-6">Menolak pembuatan/perubahan sandi jika tidak terdapat huruf kapital.</p>
            </div>

            <!-- Checkbox Wajib Simbol Unik -->
            <div class="p-3 bg-slate-50 border border-slate-200/80 rounded-xl space-y-1">
                <label class="inline-flex items-center space-x-2 cursor-pointer">
                    <input type="checkbox" name="require_symbol" value="1" {{ !empty($passwordPolicy['require_symbol']) ? 'checked' : '' }} class="rounded text-blue-600 focus:ring-blue-500 w-4 h-4">
                    <span class="text-xs font-bold text-slate-800">Wajib Mengandung Simbol Unik</span>
                </label>
                <p class="text-[10px] text-slate-500 pl-6">Mengharuskan karakter khusus seperti ! @ # $ % ^ & * _ - ( ) dll.</p>
            </div>

            <!-- Checkbox Cegah Sandi Sama Sebelumnya -->
            <div class="p-3 bg-slate-50 border border-slate-200/80 rounded-xl space-y-1">
                <label class="inline-flex items-center space-x-2 cursor-pointer">
                    <input type="checkbox" name="prevent_reuse" value="1" {{ !empty($passwordPolicy['prevent_reuse']) ? 'checked' : '' }} class="rounded text-blue-600 focus:ring-blue-500 w-4 h-4">
                    <span class="text-xs font-bold text-slate-800">Cegah Penggunaan Sandi Sama Sebelumnya</span>
                </label>
                <p class="text-[10px] text-slate-500 pl-6">Memastikan kata sandi baru tidak boleh sama dengan kata sandi sebelumnya.</p>
            </div>

            <!-- Checkbox Verifikasi CAPTCHA Login -->
            <div class="p-3 bg-slate-50 border border-slate-200/80 rounded-xl space-y-1">
                <label class="inline-flex items-center space-x-2 cursor-pointer">
                    <input type="checkbox" name="login_captcha_enabled" value="1" {{ ($captchaEnabled ?? true) ? 'checked' : '' }} class="rounded text-blue-600 focus:ring-blue-500 w-4 h-4">
                    <span class="text-xs font-bold text-slate-800">Verifikasi CAPTCHA Login</span>
                </label>
                <p class="text-[10px] text-slate-500 pl-6">Tampilkan kode gambar CAPTCHA untuk menangkal bot pada login Webmail & Admin Console.</p>
            </div>

            <!-- Jumlah Riwayat Sandi -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Batas Riwayat Sandi Sebelumnya yang Dicegah:</label>
                <input type="number" name="history_limit" value="{{ $passwordPolicy['history_limit'] ?? 5 }}" min="1" max="20" required
                       class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white font-semibold text-slate-800">
                <p class="text-[10px] text-slate-400 mt-1">Sistem akan menyimpan dan memeriksa N sandi terakhir yang pernah dipakai pengguna.</p>
            </div>

            <div class="pt-2 flex items-center justify-end space-x-2">
                <button type="button" onclick="closePasswordPolicyModal()" class="px-3.5 py-2 text-xs text-slate-600 hover:bg-slate-100 rounded-xl font-medium">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-semibold shadow-xs">
                    Simpan Perubahan Aturan
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function openPasswordPolicyModal() {
        document.getElementById('passwordPolicyModal').classList.remove('hidden');
    }

    function closePasswordPolicyModal() {
        document.getElementById('passwordPolicyModal').classList.add('hidden');
    }
</script>
@endpush
@endsection
