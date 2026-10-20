@extends('layouts.app')

@section('title', 'Manajemen Domain & Rekam DNS - ApexMail Admin')

@section('content')
<div class="flex-1 bg-[#f8fafc] overflow-y-auto p-3.5 sm:p-6 lg:p-8">
    <div class="max-w-6xl mx-auto space-y-6">

        <!-- Header Admin Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200">
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Manajemen Domain & Rekam DNS</h1>
                <p class="text-xs text-slate-500 mt-0.5">Generator otomatis DKIM 2048-bit, SPF, DMARC, dan panduan konfigurasi DNS Cloudflare / DNS Manager.</p>
            </div>

            <!-- Sub Navigasi Admin -->
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
                <a href="{{ route('admin.domains') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-600 text-white shadow-sm shadow-blue-500/20">
                    Domain & DNS
                </a>
                <a href="{{ route('admin.relays') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    SMTP Relay
                </a>
                <a href="{{ route('admin.inbound') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Email Masuk
                </a>
                <a href="{{ route('admin.queue') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Antrean & Log
                </a>
                <a href="{{ route('admin.migration') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Migrasi Data
                </a>
            </div>
        </div>

        @if(request('created') && $selectedDomain)
            <!-- Banner Sukses Domain Baru Ditambahkan -->
            <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl flex items-start space-x-3 text-emerald-900 shadow-sm animate-fade-in">
                <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5"></i>
                <div class="flex-1">
                    <h4 class="text-sm font-bold text-emerald-950">Domain '{{ $selectedDomain->name }}' Berhasil Ditambahkan & Digenerate!</h4>
                    <p class="text-xs text-emerald-800 mt-0.5">
                        Pasangan kunci kriptografi DKIM 2048-bit RSA, template SPF (IP Server 103.156.88.95 + Cloudflare), DMARC, dan MX otomatis diterbitkan. Anda tinggal menyalin record di tabel bawah ke Cloudflare / DNS Manager Anda tanpa perlu eksekusi apa pun di server.
                    </p>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Kolom Kiri: Form Tambah Domain Baru & Daftar Domain -->
            <div class="space-y-6">
                <!-- Form Tambah Domain Baru dengan Auto-Generate -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                            <i data-lucide="plus-circle" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Tambah Domain Baru</h3>
                            <p class="text-[11px] text-slate-500">Auto-generate DKIM, SPF, DMARC & MX</p>
                        </div>
                    </div>

                    <form action="{{ route('admin.domains.store') }}" method="POST" class="space-y-3.5">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Nama Domain <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="name" required placeholder="contoh: perusahaan.com" value="{{ old('name') }}"
                                   class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white font-medium text-slate-800 transition-all">
                            @error('name')
                                <p class="text-[11px] text-rose-600 mt-1 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                DKIM Selector <span class="text-slate-400 font-normal">(Default: apexmail)</span>
                            </label>
                            <input type="text" name="dkim_selector" placeholder="apexmail" value="{{ old('dkim_selector', 'apexmail') }}"
                                   class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white font-mono text-slate-700 transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Keterangan Organisasi:</label>
                            <input type="text" name="description" placeholder="Domain Resmi Organisasi" value="{{ old('description') }}"
                                   class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white text-slate-800 transition-all">
                        </div>

                        <!-- Card Info Fitur Otomatis -->
                        <div class="p-3 bg-blue-50/60 border border-blue-100 rounded-xl space-y-1.5 text-[11px] text-blue-900 leading-relaxed">
                            <div class="flex items-center space-x-1.5 font-bold text-blue-950">
                                <i data-lucide="zap" class="w-3.5 h-3.5 text-blue-600"></i>
                                <span>Generator Otomatis Server</span>
                            </div>
                            <p class="text-blue-800">
                                Sistem secara otomatis menciptakan pasangan kunci <strong>2048-bit RSA DKIM</strong>, template <strong>SPF</strong> (IP 103.156.88.95 + Cloudflare), <strong>DMARC</strong>, dan rekam <strong>MX</strong>. Anda hanya perlu menyalin nilainya ke DNS Cloudflare.
                            </p>
                        </div>

                        <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 active:scale-[0.99] text-white rounded-xl text-xs font-bold shadow-md shadow-blue-500/20 transition-all flex items-center justify-center space-x-1.5">
                            <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                            <span>Generate Kunci & Daftarkan Domain</span>
                        </button>
                    </form>
                </div>

                <!-- Daftar Domain Terdaftar -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Domain Terdaftar ({{ $domains->count() }})</h3>
                        <span class="text-[11px] text-slate-400">Pilih untuk melihat DNS</span>
                    </div>

                    <div class="divide-y divide-slate-100 max-h-96 overflow-y-auto no-scrollbar">
                        @forelse($domains as $d)
                            <a href="{{ route('admin.domains', ['domain_id' => $d->id]) }}"
                               class="py-3 px-2.5 rounded-xl flex items-center justify-between transition-colors {{ ($selectedDomain && $selectedDomain->id == $d->id) ? 'bg-blue-50/80 border border-blue-200 shadow-xs' : 'hover:bg-slate-50' }}">
                                <div class="space-y-0.5">
                                    <div class="font-bold text-xs {{ ($selectedDomain && $selectedDomain->id == $d->id) ? 'text-blue-700' : 'text-slate-800' }} flex items-center space-x-1.5">
                                        <span>{{ $d->name }}</span>
                                        @if($d->dkim_public_key)
                                            <span title="DKIM 2048-bit Aktif" class="inline-flex items-center text-[10px] text-emerald-600 font-semibold">
                                                <i data-lucide="key" class="w-3 h-3"></i>
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-slate-400">
                                        {{ $d->accounts_count }} akun kotak surat &bull; Selector: <code class="font-mono text-[10px] text-slate-600">{{ $d->dkim_selector ?: 'apexmail' }}</code>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $d->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $d->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </a>
                        @empty
                            <div class="py-6 text-center text-xs text-slate-400">
                                Belum ada domain yang didaftarkan.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan: DNS Management Hub & Cloudflare Ready Records -->
            <div class="lg:col-span-2 space-y-5">
                @if($selectedDomain)
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
                        <!-- Header Detail Domain & Toolbar Aksi -->
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-100">
                            <div>
                                <div class="flex items-center space-x-2">
                                    <h2 class="text-base font-bold text-slate-900">{{ $selectedDomain->name }}</h2>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                        DKIM 2048-bit RSA
                                    </span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        {{ $selectedDomain->accounts_count }} Akun
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Selector: <code class="font-mono font-bold text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded">{{ $selectedDomain->dkim_selector ?: 'apexmail' }}</code> &bull;
                                    IP Pengirim: <code class="font-mono font-bold text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded">103.156.88.95</code>
                                    @if($selectedDomain->description)
                                        &bull; {{ $selectedDomain->description }}
                                    @endif
                                </p>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex items-center flex-wrap gap-2">
                                <!-- Form Cek Status DNS Live -->
                                <form action="{{ route('admin.domains.check-dns', $selectedDomain->id) }}" method="POST" id="formCheckDns">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 shadow-xs flex items-center space-x-1.5 transition-all">
                                        <i data-lucide="activity" class="w-3.5 h-3.5 text-blue-600"></i>
                                        <span>Periksa Status DNS Live</span>
                                    </button>
                                </form>

                                <!-- Button Salin Semua Record -->
                                <button type="button" onclick="copyAllDnsRecords()" class="px-3 py-1.5 text-xs font-semibold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 flex items-center space-x-1.5 transition-all">
                                    <i data-lucide="copy" class="w-3.5 h-3.5 text-slate-600"></i>
                                    <span>Salin Semua Record</span>
                                </button>

                                <!-- Download Plaintext DNS -->
                                <a href="{{ route('admin.domains.export-dns', $selectedDomain->id) }}" class="px-3 py-1.5 text-xs font-semibold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 flex items-center space-x-1.5 transition-all" title="Unduh File DNS">
                                    <i data-lucide="download" class="w-3.5 h-3.5 text-slate-600"></i>
                                    <span>Unduh .txt</span>
                                </a>

                                <!-- Regenerasi Kunci DKIM -->
                                <form action="{{ route('admin.domains.dkim.regenerate', $selectedDomain->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin memperbarui pasangan kunci DKIM 2048-bit untuk domain ini? Anda harus mengupdate rekam TXT di DNS manager.');">
                                    @csrf
                                    <button type="submit" class="px-2.5 py-1.5 text-xs font-medium rounded-xl bg-white hover:bg-slate-50 text-slate-600 border border-slate-200 flex items-center space-x-1 transition-all" title="Regenerasi Kunci DKIM">
                                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                                    </button>
                                </form>

                                <!-- Hapus Domain -->
                                <form action="{{ route('admin.domains.destroy', $selectedDomain->id) }}" method="POST" onsubmit="return confirm('Hapus domain {{ $selectedDomain->name }} dari sistem? Pastikan tidak ada akun aktif.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-2.5 py-1.5 text-xs font-medium rounded-xl bg-white hover:bg-rose-50 text-rose-600 border border-slate-200 hover:border-rose-200 flex items-center space-x-1 transition-all" title="Hapus Domain">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Panel Hasil Diagnostik Live DNS (Jika ada hasil pemeriksaan) -->
                        @if(!empty($dnsCheckResult) && ($checkedDomainId ?? $selectedDomain->id) == $selectedDomain->id)
                            <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-2">
                                        <i data-lucide="radio" class="w-4 h-4 text-blue-600 animate-pulse"></i>
                                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Hasil Pemeriksaan DNS Real-Time (Live)</h4>
                                    </div>
                                    <span class="text-[11px] text-slate-500">Dicek pada: {{ $dnsCheckResult['checked_at'] ?? now()->format('H:i:s') }}</span>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <!-- Status DKIM -->
                                    <div class="p-3 bg-white border rounded-xl space-y-1.5 {{ $dnsCheckResult['dkim']['status'] === 'verified' ? 'border-emerald-200 bg-emerald-50/20' : 'border-amber-200 bg-amber-50/20' }}">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-slate-800 flex items-center space-x-1.5">
                                                <span>DKIM Record</span>
                                                <span class="font-mono text-[10px] text-slate-500">({{ $selectedDomain->dkim_selector }}._domainkey)</span>
                                            </span>
                                            @if($dnsCheckResult['dkim']['status'] === 'verified')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 flex items-center space-x-1">
                                                    <i data-lucide="check" class="w-3 h-3"></i>
                                                    <span>Terverifikasi Aktif</span>
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 flex items-center space-x-1">
                                                    <i data-lucide="clock" class="w-3 h-3"></i>
                                                    <span>Belum Terdeteksi</span>
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-[11px] text-slate-600 leading-relaxed">{{ $dnsCheckResult['dkim']['details'] }}</p>
                                    </div>

                                    <!-- Status SPF -->
                                    <div class="p-3 bg-white border rounded-xl space-y-1.5 {{ $dnsCheckResult['spf']['status'] === 'verified' ? 'border-emerald-200 bg-emerald-50/20' : 'border-amber-200 bg-amber-50/20' }}">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-slate-800 flex items-center space-x-1.5">
                                                <span>SPF Record</span>
                                                <span class="font-mono text-[10px] text-slate-500">(@)</span>
                                            </span>
                                            @if($dnsCheckResult['spf']['status'] === 'verified')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 flex items-center space-x-1">
                                                    <i data-lucide="check" class="w-3 h-3"></i>
                                                    <span>Terverifikasi Aktif</span>
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 flex items-center space-x-1">
                                                    <i data-lucide="clock" class="w-3 h-3"></i>
                                                    <span>Belum Terdeteksi</span>
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-[11px] text-slate-600 leading-relaxed">{{ $dnsCheckResult['spf']['details'] }}</p>
                                    </div>

                                    <!-- Status DMARC -->
                                    <div class="p-3 bg-white border rounded-xl space-y-1.5 {{ $dnsCheckResult['dmarc']['status'] === 'verified' ? 'border-emerald-200 bg-emerald-50/20' : 'border-amber-200 bg-amber-50/20' }}">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-slate-800 flex items-center space-x-1.5">
                                                <span>DMARC Record</span>
                                                <span class="font-mono text-[10px] text-slate-500">(_dmarc)</span>
                                            </span>
                                            @if($dnsCheckResult['dmarc']['status'] === 'verified')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 flex items-center space-x-1">
                                                    <i data-lucide="check" class="w-3 h-3"></i>
                                                    <span>Terverifikasi Aktif</span>
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 flex items-center space-x-1">
                                                    <i data-lucide="clock" class="w-3 h-3"></i>
                                                    <span>Belum Terdeteksi</span>
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-[11px] text-slate-600 leading-relaxed">{{ $dnsCheckResult['dmarc']['details'] }}</p>
                                    </div>

                                    <!-- Status MX -->
                                    <div class="p-3 bg-white border rounded-xl space-y-1.5 {{ $dnsCheckResult['mx']['status'] === 'verified' ? 'border-emerald-200 bg-emerald-50/20' : 'border-amber-200 bg-amber-50/20' }}">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-slate-800 flex items-center space-x-1.5">
                                                <span>MX Inbound Record</span>
                                                <span class="font-mono text-[10px] text-slate-500">(@)</span>
                                            </span>
                                            @if($dnsCheckResult['mx']['status'] === 'verified')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 flex items-center space-x-1">
                                                    <i data-lucide="check" class="w-3 h-3"></i>
                                                    <span>MX Terhubung</span>
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 flex items-center space-x-1">
                                                    <i data-lucide="clock" class="w-3 h-3"></i>
                                                    <span>Belum Terdeteksi</span>
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-[11px] text-slate-600 leading-relaxed">{{ $dnsCheckResult['mx']['details'] }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Petunjuk Penting Cloudflare Email Routing -->
                        <div class="p-3.5 bg-blue-50/70 border border-blue-200 rounded-xl flex items-start space-x-3 text-xs text-blue-900 leading-relaxed">
                            <i data-lucide="info" class="w-4 h-4 text-blue-600 shrink-0 mt-0.5"></i>
                            <div>
                                <span class="font-bold text-blue-950">Cara Memasukkan Record ke Cloudflare DNS Tanpa Error:</span>
                                <p class="mt-0.5 text-blue-800 text-[11px]">
                                    1. Di Cloudflare DNS, selalu klik tombol biru <strong>"Add record"</strong> (Jangan klik Edit pada record yang bertanda Managed by Email Routing).<br>
                                    2. Record <strong>DKIM (TXT)</strong> dan <strong>DMARC (TXT)</strong> memiliki nama host unik (<code class="bg-white/80 px-1 rounded font-mono">{{ $selectedDomain->dkim_selector }}._domainkey</code> dan <code class="bg-white/80 px-1 rounded font-mono">_dmarc</code>) sehingga <strong>tidak akan pernah bentrok</strong> dengan Cloudflare Email Routing.<br>
                                    3. Salin nilai record di bawah satu per satu menggunakan tombol <strong>Salin</strong>.
                                </p>
                            </div>
                        </div>

                        <!-- TABEL REKAM DNS SIAP SALIN (CLOUDFLARE READY) -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center space-x-1.5">
                                    <i data-lucide="table" class="w-3.5 h-3.5 text-blue-600"></i>
                                    <span>Tabel Rekam DNS untuk {{ $selectedDomain->name }}</span>
                                </h3>
                                <span class="text-[11px] text-slate-400">Total: {{ count($selectedDomain->all_dns_records_list) }} Rekam DNS</span>
                            </div>

                            <div class="border border-slate-200 rounded-xl overflow-hidden shadow-xs">
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-600 uppercase tracking-wider">
                                                <th class="py-2.5 px-3">Tipe</th>
                                                <th class="py-2.5 px-3">Nama / Host</th>
                                                <th class="py-2.5 px-3">Nilai / Isi Target</th>
                                                <th class="py-2.5 px-3">Prioritas / TTL</th>
                                                <th class="py-2.5 px-3 text-right">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                                            @foreach($selectedDomain->all_dns_records_list as $rec)
                                                <tr class="hover:bg-slate-50/70 transition-colors">
                                                    <!-- Kolom Tipe -->
                                                    <td class="py-3 px-3 align-top">
                                                        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold
                                                            {{ $rec['type'] === 'TXT' ? 'bg-blue-100 text-blue-800 border border-blue-200' : '' }}
                                                            {{ $rec['type'] === 'MX' ? 'bg-purple-100 text-purple-800 border border-purple-200' : '' }}
                                                            {{ $rec['type'] === 'A' ? 'bg-amber-100 text-amber-800 border border-amber-200' : '' }}">
                                                            {{ $rec['type'] }}
                                                        </span>
                                                        <div class="text-[10px] text-slate-400 mt-1 font-semibold">{{ $rec['category'] }}</div>
                                                    </td>

                                                    <!-- Kolom Host / Name -->
                                                    <td class="py-3 px-3 align-top">
                                                        <div class="flex items-center space-x-1.5">
                                                            <code class="font-mono font-bold text-slate-800 bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200 select-all">{{ $rec['name'] }}</code>
                                                            <button type="button" onclick="copyText('{{ $rec['name'] }}', 'Nama Host')" class="text-slate-400 hover:text-blue-600 transition-colors" title="Salin Nama Host">
                                                                <i data-lucide="copy" class="w-3 h-3"></i>
                                                            </button>
                                                        </div>
                                                        <div class="text-[10px] text-slate-400 mt-1">
                                                            {{ $rec['full_host'] }}
                                                        </div>
                                                    </td>

                                                    <!-- Kolom Value / Target -->
                                                    <td class="py-3 px-3 align-top max-w-xs md:max-w-md">
                                                        <div class="text-[11px] font-mono text-slate-800 bg-slate-50 border border-slate-200 p-2 rounded-lg break-all select-all leading-relaxed max-h-24 overflow-y-auto font-medium">
                                                            {{ $rec['value'] }}
                                                        </div>
                                                        <div class="text-[10px] text-slate-500 mt-1 leading-normal">
                                                            {{ $rec['description'] }}
                                                        </div>
                                                    </td>

                                                    <!-- Kolom Prioritas / TTL -->
                                                    <td class="py-3 px-3 align-top whitespace-nowrap">
                                                        @if(!empty($rec['priority']))
                                                            <div class="font-bold text-[11px] text-purple-700">Prioritas: {{ $rec['priority'] }}</div>
                                                        @endif
                                                        <div class="text-[11px] text-slate-500">TTL: {{ $rec['ttl'] }}</div>
                                                    </td>

                                                    <!-- Kolom Aksi Salin -->
                                                    <td class="py-3 px-3 align-top text-right whitespace-nowrap">
                                                        <button type="button" onclick="copyText('{{ addslashes($rec['value']) }}', 'Nilai {{ $rec['category'] }}')"
                                                                class="px-2.5 py-1 text-xs bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold rounded-lg border border-blue-200 transition-colors inline-flex items-center space-x-1">
                                                            <i data-lucide="copy" class="w-3 h-3"></i>
                                                            <span>Salin</span>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    </div>
                @else
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-12 text-center space-y-3">
                        <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto">
                            <i data-lucide="globe" class="w-6 h-6"></i>
                        </div>
                        <h3 class="text-sm font-bold text-slate-800">Pilih atau Tambahkan Domain</h3>
                        <p class="text-xs text-slate-500 max-w-sm mx-auto">
                            Pilih salah satu domain di panel kiri atau daftarkan domain baru untuk melihat rekam DNS otomatis (DKIM 2048-bit, SPF, DMARC, MX).
                        </p>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>

<!-- Hidden Zone Text for One-Click Copy All -->
@if($selectedDomain)
    <textarea id="hiddenAllDnsText" class="hidden">{{ $selectedDomain->generateZoneFileText() }}</textarea>
@endif

@push('scripts')
<script>
    function copyText(text, label) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(() => {
                showAppModal('success', 'Berhasil Disalin!', label + ' berhasil disalin ke clipboard Anda.');
            }).catch(() => {
                fallbackCopy(text, label);
            });
        } else {
            fallbackCopy(text, label);
        }
    }

    function copyAllDnsRecords() {
        const el = document.getElementById('hiddenAllDnsText');
        if (!el) return;
        copyText(el.value, 'Seluruh Rekam DNS (DKIM, SPF, DMARC, MX)');
    }

    function fallbackCopy(text, label) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            showAppModal('success', 'Berhasil Disalin!', label + ' berhasil disalin ke clipboard.');
        } catch (err) {
            showAppModal('error', 'Gagal Menyalin', 'Silakan blok teks dan salin secara manual.');
        }
        document.body.removeChild(textArea);
    }
</script>
@endpush
@endsection
