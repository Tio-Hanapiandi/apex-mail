@extends('layouts.app')

@section('title', 'Manajemen Spam & Pemblokiran Pengirim - ApexMail Admin')

@section('content')
<div class="flex-1 bg-[#f8fafc] overflow-y-auto p-3.5 sm:p-6 lg:p-8">
    <div class="max-w-6xl mx-auto space-y-6">

        <!-- Top Header Admin Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-rose-100 text-rose-800 border border-rose-200 uppercase">
                        Spam & Security
                    </span>
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Filter Spam & Pemblokiran Pengirim</h1>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Pantau alamat pengirim yang ditandai spam oleh pengguna atau diblokir sistem, serta pulihkan (*unblock*) jika terjadi salah tanda (*human error*).</p>
            </div>

            <!-- Sub Navigasi Menu Admin -->
            <div class="flex items-center space-x-1.5 overflow-x-auto no-scrollbar pb-1 max-w-full">
                <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50 whitespace-nowrap">
                    Ringkasan Sistem
                </a>
                <a href="{{ route('admin.accounts') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50 whitespace-nowrap">
                    Kelola Akun
                </a>
                <a href="{{ route('admin.aliases') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50 whitespace-nowrap">
                    Alias & Grup
                </a>
                <a href="{{ route('admin.domains') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50 whitespace-nowrap">
                    Domain & DNS
                </a>
                <a href="{{ route('admin.relays') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50 whitespace-nowrap">
                    SMTP Relay
                </a>
                <a href="{{ route('admin.inbound') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50 whitespace-nowrap">
                    Email Masuk (Inbound)
                </a>
                <a href="{{ route('admin.spam.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-600 text-white shadow-sm shadow-blue-500/20 whitespace-nowrap">
                    Spam & Pemblokiran
                </a>
                <a href="{{ route('admin.queue') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50 whitespace-nowrap">
                    Antrean & Log
                </a>
                <a href="{{ route('admin.migration') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50 whitespace-nowrap">
                    Migrasi Data
                </a>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 space-y-1">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-xs font-semibold">Total Aturan Pemblokiran</span>
                    <i data-lucide="shield-alert" class="w-4 h-4 text-rose-600"></i>
                </div>
                <div class="text-2xl font-bold text-slate-900">{{ $totalBlocked }}</div>
                <p class="text-[11px] text-slate-400">Pengirim yang saat ini terblokir dari kotak masuk</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 space-y-1">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-xs font-semibold">Ditandai Spam oleh Pengguna</span>
                    <i data-lucide="user-x" class="w-4 h-4 text-amber-600"></i>
                </div>
                <div class="text-2xl font-bold text-slate-900">{{ $userMarkedSpamCount }}</div>
                <p class="text-[11px] text-slate-400">Pemblokiran spesifik per mailbox pengguna</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 space-y-1">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-xs font-semibold">Pemblokiran Global (Admin)</span>
                    <i data-lucide="globe" class="w-4 h-4 text-indigo-600"></i>
                </div>
                <div class="text-2xl font-bold text-slate-900">{{ $globalBlockedCount }}</div>
                <p class="text-[11px] text-slate-400">Berlaku untuk seluruh akun di server</p>
            </div>
        </div>

        <!-- Layout Utama: Form Tambah & Tabel Daftar Terblokir -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Kolom Kiri: Form Tambah Blokir Manual -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center">
                    <i data-lucide="plus-circle" class="w-3.5 h-3.5 mr-1.5 text-blue-600"></i>
                    Tambah Pemblokiran Manual
                </h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Administrator dapat menambahkan alamat email pengirim mencurigakan ke daftar blokir secara manual.
                </p>

                <form action="{{ route('admin.spam.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Alamat Email Pengirim: <span class="text-rose-500">*</span></label>
                        <input type="email" name="sender_email" required placeholder="spammer@domain.com"
                               class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-mono">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Target Mailbox yang Memblokir:</label>
                        <select name="mail_account_id" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                            <option value="">-- Semua Akun / Global (Seluruh Server) --</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->email }} ({{ $acc->display_name }})</option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-slate-400 mt-1">Pilih akun tertentu jika pemblokiran hanya untuk 1 user, atau kosongkan untuk semua user.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Alasan / Catatan:</label>
                        <input type="text" name="reason" placeholder="Contoh: Pengirim phising / spam massal"
                               class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                    </div>

                    <button type="submit" class="w-full py-2.5 px-4 bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs rounded-xl shadow-sm transition-all flex items-center justify-center space-x-1.5">
                        <i data-lucide="shield-alert" class="w-4 h-4"></i>
                        <span>Blokir Alamat Pengirim</span>
                    </button>
                </form>
            </div>

            <!-- Kolom Kanan: Tabel Daftar Pengirim Terblokir -->
            <div class="lg:col-span-2 space-y-4">
                
                <!-- Filter & Search Bar -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                    <form action="{{ route('admin.spam.index') }}" method="GET" class="flex flex-col sm:flex-row items-center gap-2">
                        <div class="relative flex-1 w-full">
                            <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-3 text-slate-400"></i>
                            <input type="text" name="q" value="{{ $query }}" placeholder="Cari email pengirim, domain, atau user..."
                                   class="w-full pl-8.5 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:bg-white transition-all">
                        </div>

                        <select name="account_id" class="w-full sm:w-56 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-all">
                            <option value="">Semua Target User</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}" {{ $accountId == $acc->id ? 'selected' : '' }}>{{ $acc->email }}</option>
                            @endforeach
                        </select>

                        <div class="flex items-center space-x-1.5 w-full sm:w-auto">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-xs font-semibold rounded-xl hover:bg-blue-700 transition-colors w-full sm:w-auto">
                                Filter
                            </button>
                            @if($query || $accountId)
                                <a href="{{ route('admin.spam.index') }}" class="px-3 py-2 bg-slate-100 text-slate-600 text-xs font-semibold rounded-xl hover:bg-slate-200 transition-colors" title="Reset Filter">
                                    <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                <!-- Card Tabel Pengirim Terblokir -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">
                            Daftar Pengirim Terblokir ({{ $blockedSenders->total() }})
                        </span>
                        <span class="text-[11px] text-slate-400">Klik "Buka Blokir" untuk mengizinkan email masuk kembali</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50/70 border-b border-slate-200/80 text-slate-500 font-semibold">
                                <tr>
                                    <th class="py-3 px-4">Pengirim Terblokir</th>
                                    <th class="py-3 px-4">Target Mailbox User</th>
                                    <th class="py-3 px-4">Alasan Pemblokiran</th>
                                    <th class="py-3 px-4">Tanggal</th>
                                    <th class="py-3 px-4 text-right">Aksi Unblock</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($blockedSenders as $rule)
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="py-3 px-4">
                                            <div class="font-bold text-slate-900 font-mono">{{ $rule->sender_email }}</div>
                                            @if($rule->sender_domain)
                                                <div class="text-[10px] text-slate-400">Domain: {{ $rule->sender_domain }}</div>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4">
                                            @if($rule->account)
                                                <span class="inline-flex items-center space-x-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                                    <i data-lucide="user" class="w-3 h-3 text-blue-500"></i>
                                                    <span>{{ $rule->account->email }}</span>
                                                </span>
                                            @else
                                                <span class="inline-flex items-center space-x-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                                    <i data-lucide="globe" class="w-3 h-3 text-purple-500"></i>
                                                    <span>Semua Akun (Global)</span>
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="text-slate-600 text-xs">{{ $rule->reason ?: 'Ditandai Spam' }}</span>
                                        </td>
                                        <td class="py-3 px-4 text-slate-400 text-[11px] whitespace-nowrap">
                                            {{ $rule->created_at ? $rule->created_at->format('d M Y, H:i') : '-' }}
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            <form action="{{ route('admin.spam.unblock', $rule->id) }}" method="POST" class="inline" id="unblockForm_{{ $rule->id }}">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="restore_emails" value="1">
                                                <button type="button" 
                                                        onclick="confirmAdminUnblock('{{ $rule->id }}', '{{ addslashes($rule->sender_email) }}', '{{ addslashes(optional($rule->account)->email ?: 'Semua Akun') }}')"
                                                        class="inline-flex items-center space-x-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 transition-colors shadow-2xs"
                                                        title="Buka blokir pengirim ini">
                                                    <i data-lucide="unlock" class="w-3 h-3 text-emerald-600"></i>
                                                    <span>Buka Blokir</span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-12 text-center text-slate-400">
                                            <div class="w-12 h-12 rounded-2xl bg-slate-50 text-slate-400 flex items-center justify-center mx-auto mb-2">
                                                <i data-lucide="shield-check" class="w-6 h-6 text-emerald-500"></i>
                                            </div>
                                            <p class="font-bold text-slate-700 text-sm">Tidak ada alamat pengirim yang terblokir</p>
                                            <p class="text-xs text-slate-400 mt-1">Saat pengguna menandai email sebagai spam, alamat pengirim akan tercatat di sini dan dapat dibuka kembali kapan saja.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($blockedSenders->hasPages())
                        <div class="p-3 bg-white border-t border-slate-100">
                            {{ $blockedSenders->links() }}
                        </div>
                    @endif
                </div>

            </div>
        </div>

    </div>
</div>

<!-- Modal Konfirmasi Unblock -->
<div id="adminUnblockModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-md w-full p-5 space-y-4 shadow-xl border border-slate-200">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0">
                <i data-lucide="unlock" class="w-5 h-5"></i>
            </div>
            <div>
                <h3 class="text-sm font-bold text-slate-800">Buka Blokir Pengirim?</h3>
                <p class="text-xs text-slate-500">Email dari pengirim ini akan diizinkan masuk kembali ke mailbox.</p>
            </div>
        </div>

        <div class="p-3 bg-slate-50 rounded-xl text-xs space-y-1.5 border border-slate-200/80">
            <div>Pengirim: <strong id="modalSenderEmail" class="font-mono text-slate-900"></strong></div>
            <div>Target Mailbox: <strong id="modalAccountEmail" class="font-semibold text-blue-600"></strong></div>
        </div>

        <p class="text-xs text-slate-600 leading-relaxed">
            Jika pengguna tidak sengaja (*human error*) menandai pengirim ini sebagai spam, membuka blokir akan memastikan email berikutnya kembali masuk ke Kotak Masuk (*Inbox*), dan memulihkan email yang ada di folder Spam.
        </p>

        <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
            <button type="button" onclick="closeAdminUnblockModal()" class="px-3.5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">
                Batal
            </button>
            <button type="button" id="modalConfirmBtn" class="px-4 py-2 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-xs transition-colors">
                Ya, Buka Blokir Sekarang
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    let activeUnblockFormId = null;

    function confirmAdminUnblock(ruleId, senderEmail, accountEmail) {
        activeUnblockFormId = 'unblockForm_' + ruleId;
        document.getElementById('modalSenderEmail').textContent = senderEmail;
        document.getElementById('modalAccountEmail').textContent = accountEmail;
        document.getElementById('adminUnblockModal').classList.remove('hidden');
    }

    function closeAdminUnblockModal() {
        document.getElementById('adminUnblockModal').classList.add('hidden');
        activeUnblockFormId = null;
    }

    document.getElementById('modalConfirmBtn').addEventListener('click', function() {
        if (activeUnblockFormId) {
            document.getElementById(activeUnblockFormId).submit();
        }
    });
</script>
@endpush
@endsection
