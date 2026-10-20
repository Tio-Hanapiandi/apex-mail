@extends('layouts.app')

@section('title', 'Antrean Surat & Log Transmisi - ApexMail Admin')

@section('content')
<div class="flex-1 bg-[#f8fafc] overflow-y-auto p-3.5 sm:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto space-y-6">

        <!-- Top Header Admin Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200">
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Antrean Surat & Log Pengiriman (Mail Queue)</h1>
                <p class="text-xs text-slate-500 mt-0.5">Pantau status lalu lintas surat keluar/masuk, jumlah antrean, serta status tertahan (deferred/hold).</p>
            </div>

            <div class="flex items-center space-x-2">
                @if(($stats['deferred'] ?? 0) > 0)
                    <form action="{{ route('admin.queue.flush') }}" method="POST" onsubmit="return confirmFlushQueue(event, this)">
                        @csrf
                        <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-semibold text-xs shadow-sm transition-all flex items-center space-x-1.5 animate-pulse">
                            <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                            <span>Flush Antrean Tertahan ({{ $stats['deferred'] }})</span>
                        </button>
                    </form>
                @endif

                <div class="flex items-center space-x-1.5 overflow-x-auto no-scrollbar pb-1 max-w-full">
                    <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                        Ringkasan Sistem
                    </a>
                    <a href="{{ route('admin.accounts') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                        Kelola Akun
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
                    <a href="{{ route('admin.queue') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-600 text-white shadow-sm shadow-blue-500/20">
                        Antrean & Log
                    </a>
                    <a href="{{ route('admin.migration') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                        Migrasi Data
                    </a>
                </div>
            </div>
        </div>

        <!-- 1. STATISTIK ANTREAN SURAT (QUEUE OVERVIEW METRICS) -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
            <!-- Total Antrean & Log -->
            <a href="{{ route('admin.queue', ['status' => 'all']) }}" 
               class="bg-white rounded-2xl border p-4 space-y-1.5 shadow-sm transition-all hover:border-slate-300 {{ $statusFilter === 'all' ? 'ring-2 ring-blue-500/30 border-blue-500' : 'border-slate-200' }}">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-xs font-semibold">Total Antrean / Log</span>
                    <i data-lucide="layers" class="w-4 h-4 text-slate-600"></i>
                </div>
                <div class="text-2xl font-bold text-slate-900">{{ number_format($stats['total'] ?? 0) }}</div>
                <p class="text-[11px] text-slate-400">Seluruh riwayat transmisi</p>
            </a>

            <!-- Terkirim (Delivered) -->
            <a href="{{ route('admin.queue', ['status' => 'delivered']) }}" 
               class="bg-white rounded-2xl border p-4 space-y-1.5 shadow-sm transition-all hover:border-emerald-300 {{ $statusFilter === 'delivered' ? 'ring-2 ring-emerald-500/30 border-emerald-500 bg-emerald-50/20' : 'border-slate-200' }}">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-xs font-semibold text-emerald-800">Terkirim (Delivered)</span>
                    <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>
                </div>
                <div class="text-2xl font-bold text-emerald-700">{{ number_format($stats['delivered'] ?? 0) }}</div>
                <p class="text-[11px] text-slate-400">Sukses tersalurkan ke tujuan</p>
            </a>

            <!-- Tertahan (Deferred) -->
            <a href="{{ route('admin.queue', ['status' => 'deferred']) }}" 
               class="bg-white rounded-2xl border p-4 space-y-1.5 shadow-sm transition-all hover:border-amber-300 {{ $statusFilter === 'deferred' ? 'ring-2 ring-amber-500/30 border-amber-500 bg-amber-50/30' : 'border-slate-200' }}">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-xs font-semibold text-amber-800">Tertahan (Deferred)</span>
                    <i data-lucide="clock" class="w-4 h-4 text-amber-500"></i>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="text-2xl font-bold text-amber-700">{{ number_format($stats['deferred'] ?? 0) }}</span>
                    @if(($stats['deferred'] ?? 0) > 0)
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    @endif
                </div>
                <p class="text-[11px] text-slate-400">Menunggu retry pengiriman</p>
            </a>

            <!-- Ditahan Admin (On Hold) -->
            <a href="{{ route('admin.queue', ['status' => 'held']) }}" 
               class="bg-white rounded-2xl border p-4 space-y-1.5 shadow-sm transition-all hover:border-blue-300 {{ $statusFilter === 'held' ? 'ring-2 ring-blue-500/30 border-blue-500 bg-blue-50/20' : 'border-slate-200' }}">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-xs font-semibold text-blue-800">Ditahan (On Hold)</span>
                    <i data-lucide="pause-circle" class="w-4 h-4 text-blue-600"></i>
                </div>
                <div class="text-2xl font-bold text-blue-700">{{ number_format($stats['held'] ?? 0) }}</div>
                <p class="text-[11px] text-slate-400">Ditahan sementara oleh admin</p>
            </a>

            <!-- Gagal / Bounced -->
            <a href="{{ route('admin.queue', ['status' => 'bounced']) }}" 
               class="bg-white rounded-2xl border p-4 space-y-1.5 shadow-sm transition-all hover:border-rose-300 {{ $statusFilter === 'bounced' ? 'ring-2 ring-rose-500/30 border-rose-500 bg-rose-50/20' : 'border-slate-200' }}">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-xs font-semibold text-rose-800">Mental / Bounced</span>
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600"></i>
                </div>
                <div class="text-2xl font-bold text-rose-700">{{ number_format($stats['bounced'] ?? 0) }}</div>
                <p class="text-[11px] text-slate-400">Pesan gagal atau mental</p>
            </a>
        </div>

        <!-- 2. FILTER & PENCARIAN ANTREAN -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 flex flex-col sm:flex-row items-center justify-between gap-3">
            <!-- Filter Pills -->
            <div class="flex items-center space-x-1.5 overflow-x-auto no-scrollbar w-full sm:w-auto text-xs">
                <a href="{{ route('admin.queue', ['status' => 'all', 'q' => request('q')]) }}" 
                   class="px-3 py-1.5 rounded-xl font-semibold transition-all {{ $statusFilter === 'all' ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    Semua ({{ $stats['total'] }})
                </a>
                <a href="{{ route('admin.queue', ['status' => 'delivered', 'q' => request('q')]) }}" 
                   class="px-3 py-1.5 rounded-xl font-semibold transition-all {{ $statusFilter === 'delivered' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    Terkirim ({{ $stats['delivered'] }})
                </a>
                <a href="{{ route('admin.queue', ['status' => 'deferred', 'q' => request('q')]) }}" 
                   class="px-3 py-1.5 rounded-xl font-semibold transition-all flex items-center space-x-1 {{ $statusFilter === 'deferred' ? 'bg-amber-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    <span>Tertahan</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $statusFilter === 'deferred' ? 'bg-white/30 text-white' : 'bg-amber-100 text-amber-800' }}">
                        {{ $stats['deferred'] }}
                    </span>
                </a>
                <a href="{{ route('admin.queue', ['status' => 'held', 'q' => request('q')]) }}" 
                   class="px-3 py-1.5 rounded-xl font-semibold transition-all {{ $statusFilter === 'held' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    Ditahan ({{ $stats['held'] }})
                </a>
                <a href="{{ route('admin.queue', ['status' => 'bounced', 'q' => request('q')]) }}" 
                   class="px-3 py-1.5 rounded-xl font-semibold transition-all {{ $statusFilter === 'bounced' ? 'bg-rose-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    Bounced ({{ $stats['bounced'] }})
                </a>
            </div>

            <!-- Search Form & Relay Info -->
            <div class="flex items-center space-x-2.5 w-full sm:w-auto">
                <form action="{{ route('admin.queue') }}" method="GET" class="relative flex-1 sm:w-64">
                    <input type="hidden" name="status" value="{{ $statusFilter }}">
                    <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-2.5 text-slate-400"></i>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari subjek / alamat..." 
                           class="w-full pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white text-slate-800">
                </form>

                <div class="hidden lg:flex items-center space-x-1 text-xs">
                    <span class="inline-flex items-center space-x-1 px-2.5 py-1 rounded-full text-emerald-700 bg-emerald-50 border border-emerald-200 font-semibold text-[11px]">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        <span class="truncate max-w-[180px]">Host: {{ $activeRelay->host ?? 'smtp-relay.local' }}</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- 3. TABEL LOG & ANTREAN SURAT -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-6 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Daftar Antrean & Log Transmisi</h3>
                    <p class="text-[11px] text-slate-400">
                        Menampilkan {{ $recentEmails->total() }} email 
                        @if($statusFilter !== 'all')
                            dengan filter status <strong>{{ ucfirst($statusFilter) }}</strong>
                        @endif
                    </p>
                </div>

                @if(request('q'))
                    <a href="{{ route('admin.queue', ['status' => $statusFilter]) }}" class="text-xs text-blue-600 hover:underline flex items-center space-x-1">
                        <i data-lucide="x" class="w-3.5 h-3.5"></i>
                        <span>Reset Pencarian</span>
                    </a>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs divide-y divide-slate-100">
                    <thead>
                        <tr class="text-[11px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/60 rounded-lg">
                            <th class="py-2.5 px-3 rounded-l-lg">Waktu Transmisi</th>
                            <th class="py-2.5 px-3">Pengirim</th>
                            <th class="py-2.5 px-3">Penerima</th>
                            <th class="py-2.5 px-3">Subjek & Catatan</th>
                            <th class="py-2.5 px-3">Jalur Relay</th>
                            <th class="py-2.5 px-3 text-center">Status Antrean</th>
                            <th class="py-2.5 px-3 text-right rounded-r-lg">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($recentEmails as $mail)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <!-- Waktu -->
                                <td class="py-3 px-3 text-slate-500 whitespace-nowrap">
                                    <div class="font-medium text-slate-700">
                                        {{ $mail->sent_at ? $mail->sent_at->isoFormat('D MMM, HH:mm') : ($mail->created_at ? $mail->created_at->isoFormat('D MMM, HH:mm') : '-') }}
                                    </div>
                                    <div class="text-[10px] text-slate-400">
                                        {{ $mail->sent_at ? $mail->sent_at->diffForHumans() : ($mail->created_at ? $mail->created_at->diffForHumans() : '') }}
                                    </div>
                                </td>

                                <!-- Pengirim -->
                                <td class="py-3 px-3">
                                    <div class="font-mono font-medium text-slate-800 truncate max-w-[150px]" title="{{ $mail->from_email }}">
                                        {{ $mail->from_email }}
                                    </div>
                                    @if($mail->from_name && $mail->from_name !== $mail->from_email)
                                        <div class="text-[10px] text-slate-400 truncate max-w-[150px]">
                                            {{ $mail->from_name }}
                                        </div>
                                    @endif
                                </td>

                                <!-- Penerima -->
                                <td class="py-3 px-3 font-mono text-slate-600 truncate max-w-[180px]">
                                    @php
                                        $toDisplay = is_array($mail->to) 
                                            ? implode(', ', array_map(fn($t) => is_array($t) ? ($t['email'] ?? '') : $t, $mail->to))
                                            : $mail->to;
                                    @endphp
                                    <span title="{{ $toDisplay }}">{{ $toDisplay }}</span>
                                    @if(!empty($mail->cc))
                                        <span class="block text-[10px] text-slate-400 font-sans">
                                            + CC ({{ is_array($mail->cc) ? count($mail->cc) : 1 }})
                                        </span>
                                    @endif
                                </td>

                                <!-- Subjek & Catatan Error -->
                                <td class="py-3 px-3 max-w-[220px]">
                                    <div class="font-semibold text-slate-800 truncate" title="{{ $mail->subject }}">
                                        {{ $mail->subject ?: '(Tanpa Subjek)' }}
                                    </div>
                                    @if($mail->delivery_error)
                                        <div class="mt-1 p-1.5 rounded bg-rose-50 border border-rose-200/60 text-[10px] text-rose-700 font-mono break-words leading-tight" title="{{ $mail->delivery_error }}">
                                            <i data-lucide="alert-circle" class="w-3 h-3 inline mr-0.5 text-rose-500"></i>
                                            {{ Str::limit($mail->delivery_error, 80) }}
                                        </div>
                                    @elseif($mail->snippet)
                                        <div class="text-[10px] text-slate-400 truncate">
                                            {{ $mail->snippet }}
                                        </div>
                                    @endif
                                </td>

                                <!-- Jalur Relay -->
                                <td class="py-3 px-3 text-slate-500 whitespace-nowrap">
                                    <span class="inline-flex items-center space-x-1 text-[10px] font-bold bg-slate-100 text-slate-700 px-2 py-0.5 rounded">
                                        Relay Port {{ $activeRelay->port ?? 587 }}
                                    </span>
                                </td>

                                <!-- Status Antrean -->
                                <td class="py-3 px-3 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center space-x-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $mail->delivery_status_badge_class }}">
                                        @if($mail->delivery_status === 'deferred')
                                            <i data-lucide="clock" class="w-3 h-3 text-amber-600 mr-0.5"></i>
                                        @elseif($mail->delivery_status === 'held')
                                            <i data-lucide="pause-circle" class="w-3 h-3 text-blue-600 mr-0.5"></i>
                                        @elseif(in_array($mail->delivery_status, ['bounced', 'failed']))
                                            <i data-lucide="alert-triangle" class="w-3 h-3 text-rose-600 mr-0.5"></i>
                                        @else
                                            <i data-lucide="check" class="w-3 h-3 text-emerald-600 mr-0.5"></i>
                                        @endif
                                        <span>{{ $mail->delivery_status_label }}</span>
                                    </span>
                                </td>

                                <!-- Aksi -->
                                <td class="py-3 px-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end space-x-1">
                                        @if(in_array($mail->delivery_status, ['deferred', 'bounced', 'failed']))
                                            <!-- Retry / Kirim Ulang Sekarang -->
                                            <form action="{{ route('admin.queue.retry', $mail->id) }}" method="POST" title="Coba Kirim Ulang Sekarang">
                                                @csrf
                                                <button type="submit" class="p-1.5 rounded-lg text-emerald-600 hover:bg-emerald-50 transition-colors">
                                                    <i data-lucide="play" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </form>

                                            <!-- Tahan (Hold) -->
                                            <form action="{{ route('admin.queue.toggle-hold', $mail->id) }}" method="POST" title="Tahan di Antrean (Hold)">
                                                @csrf
                                                <button type="submit" class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors">
                                                    <i data-lucide="pause" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </form>
                                        @elseif($mail->delivery_status === 'held')
                                            <!-- Lepas Tahanan (Unhold) -->
                                            <form action="{{ route('admin.queue.toggle-hold', $mail->id) }}" method="POST" title="Lepaskan Tahanan (Unhold / Siap Kirim)">
                                                @csrf
                                                <button type="submit" class="p-1.5 rounded-lg text-amber-600 hover:bg-amber-50 transition-colors">
                                                    <i data-lucide="play-circle" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </form>
                                        @endif

                                        <!-- Hapus dari Antrean / Log -->
                                        <button type="button" 
                                                onclick="confirmDeleteQueueItem({{ $mail->id }}, '{{ addslashes($mail->subject ?: 'Pesan #' . $mail->id) }}')" 
                                                class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 transition-colors"
                                                title="Hapus dari Antrean">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400 text-xs space-y-2">
                                    <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-400 mx-auto flex items-center justify-center">
                                        <i data-lucide="check-circle" class="w-5 h-5 text-emerald-500"></i>
                                    </div>
                                    <p class="font-medium text-slate-600">Tidak ada antrean surat yang sesuai dengan kriteria filter.</p>
                                    <p class="text-[11px] text-slate-400">Seluruh antrean dalam kondisi bersih dan terkendali.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                {{ $recentEmails->links() }}
            </div>
        </div>

    </div>
</div>

<!-- Form tersembunyi untuk delete item -->
<form id="deleteQueueItemForm" method="POST" class="hidden">
    @csrf
    @method('DELETE')
</form>

@push('scripts')
<script>
    function confirmFlushQueue(e, form) {
        e.preventDefault();
        showConfirmModal(
            'Flush Seluruh Antrean Tertahan?',
            'Sistem akan segera memicu pengiriman ulang untuk seluruh email yang saat ini berstatus tertahan (deferred). Apakah Anda ingin melanjutkan?',
            function() {
                form.submit();
            },
            'Ya, Flush Antrean',
            false
        );
        return false;
    }

    function confirmDeleteQueueItem(id, subject) {
        const form = document.getElementById('deleteQueueItemForm');
        form.action = `/admin/queue/${id}`;
        showConfirmModal(
            'Hapus dari Antrean?',
            `Apakah Anda yakin ingin menghapus pesan "${subject}" dari antrean dan log transmisi?`,
            function() {
                form.submit();
            },
            'Ya, Hapus Pesan',
            true
        );
    }
</script>
@endpush
@endsection
