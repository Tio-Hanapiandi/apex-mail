@extends('layouts.app')

@section('title', 'Pengaturan SMTP Relay & Direct MX - ApexMail Admin')

@section('content')
<div class="flex-1 bg-[#f8fafc] overflow-y-auto p-3.5 sm:p-6 lg:p-8">
    <div class="max-w-5xl mx-auto space-y-6">

        <!-- Header Admin Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200">
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Konfigurasi Pengiriman Outbound & SMTP Relay</h1>
                <p class="text-xs text-slate-500 mt-0.5">Kelola jalur pengiriman email keluar baik via Direct MX (DNS Port 25) maupun External SMTP Relay / Smart Host independen per-domain.</p>
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
                <a href="{{ route('admin.domains') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Domain & DNS
                </a>
                <a href="{{ route('admin.relays') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-600 text-white shadow-sm shadow-blue-500/20">
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

        <!-- Outbound Architecture Status Banner -->
        @if(($activeRelaysCount ?? 0) > 0)
            <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 sm:p-5 flex items-start space-x-3.5">
                <div class="w-9 h-9 rounded-xl bg-emerald-500 text-white flex items-center justify-center shrink-0 shadow-sm shadow-emerald-500/20">
                    <i data-lucide="share-2" class="w-5 h-5"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-bold text-emerald-900 uppercase tracking-wider">Mode Pengiriman Aktif</span>
                        <span class="px-2 py-0.5 rounded-full bg-emerald-200 text-emerald-800 font-bold text-[10px]">
                            External SMTP Relay / Smart Host ({{ $activeRelaysCount }} Profil Aktif)
                        </span>
                    </div>
                    <p class="text-xs text-emerald-800 mt-1 leading-relaxed">
                        Sistem memiliki <strong>{{ $activeRelaysCount }} profil relay aktif</strong>.
                        @if(($domainRelaysCount ?? 0) > 0)
                            Sebanyak <strong>{{ $domainRelaysCount }} domain</strong> menggunakan relay terdedikasi masing-masing.
                        @endif
                        @if($globalRelay)
                            Domain tanpa relay khusus dialirkan melalui Global Smart Host (<span class="font-mono font-medium">{{ $globalRelay->host }}:{{ $globalRelay->port }}</span>).
                        @else
                            Domain tanpa relay khusus akan dikirim via <strong>Direct MX Delivery (DNS Port 25)</strong>.
                        @endif
                        Setiap domain beroperasi secara mandiri tanpa saling mengganggu atau menimpa pengaturan domain lain.
                    </p>
                </div>
            </div>
        @else
            <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 sm:p-5 flex items-start space-x-3.5">
                <div class="w-9 h-9 rounded-xl bg-blue-600 text-white flex items-center justify-center shrink-0 shadow-sm shadow-blue-500/20">
                    <i data-lucide="globe" class="w-5 h-5"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center space-x-2">
                        <span class="text-xs font-bold text-blue-900 uppercase tracking-wider">Mode Pengiriman Aktif</span>
                        <span class="px-2 py-0.5 rounded-full bg-blue-200 text-blue-800 font-bold text-[10px]">Direct MX Delivery (DNS Port 25)</span>
                    </div>
                    <p class="text-xs text-blue-800 mt-1 leading-relaxed">
                        Email keluar dikirimkan <strong>langsung dari server ApexMail ke server tujuan</strong> (Google MX, dsb) secara otomatis via DNS MX lookup dan enkripsi STARTTLS pada port 25. Alamat pengirim tampil <strong>100% murni sesuai nama domain asli Anda</strong> tanpa perantara pihak ketiga.
                    </p>
                </div>
            </div>
        @endif

        <!-- Daftar Profil SMTP Relay yang Tersedia -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center space-x-2.5">
                    <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xs">
                        <i data-lucide="server" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-slate-800">Daftar Profil SMTP Relay & Domain Scope</h2>
                        <p class="text-[11px] text-slate-400">Atur relay independen untuk masing-masing domain (misal Brevo untuk domain-a, Mailgun untuk domain-b, atau Global).</p>
                    </div>
                </div>

                <div>
                    <button type="button" onclick="openAddRelayModal()" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold shadow-sm shadow-blue-500/25 transition-all flex items-center space-x-1.5">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                        <span>Tambah Profil Relay</span>
                    </button>
                </div>
            </div>

            @if($relays->isEmpty())
                <div class="p-8 text-center space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-slate-50 text-slate-400 mx-auto flex items-center justify-center">
                        <i data-lucide="inbox" class="w-6 h-6"></i>
                    </div>
                    <p class="text-xs font-medium text-slate-600">Belum ada profil SMTP Relay yang tersimpan.</p>
                    <p class="text-[11px] text-slate-400 max-w-sm mx-auto">Sistem saat ini mengirimkan email secara langsung menggunakan Direct MX Delivery (DNS Port 25).</p>
                    <button type="button" onclick="openAddRelayModal()" class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 text-xs font-semibold">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                        <span>Buat Profil Relay Sekarang</span>
                    </button>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50/80 text-slate-500 uppercase tracking-wider text-[10px] border-b border-slate-100">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Profil & Provider</th>
                                <th class="px-4 py-3 font-semibold">Cakupan Domain</th>
                                <th class="px-4 py-3 font-semibold">Host & Port</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold">Uji Terakhir</th>
                                <th class="px-4 py-3 font-semibold text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach($relays as $rel)
                                @php
                                    $isSelected = $activeRelay && $activeRelay->id === $rel->id;
                                @endphp
                                <tr class="hover:bg-slate-50/80 transition-colors {{ $isSelected ? 'bg-blue-50/40' : '' }}">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center space-x-2">
                                            @if($isSelected)
                                                <span class="w-1.5 h-1.5 rounded-full bg-blue-600"></span>
                                            @endif
                                            <div>
                                                <div class="font-bold text-slate-900 flex items-center space-x-1.5">
                                                    <span>{{ $rel->name }}</span>
                                                    @if($rel->is_default)
                                                        <span class="px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 text-[9px] font-bold">Default</span>
                                                    @endif
                                                </div>
                                                <span class="text-[10px] text-slate-400 capitalize">{{ $rel->provider }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($rel->domain)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-blue-50 text-blue-700 border border-blue-200 font-semibold text-[11px] font-mono">
                                                <i data-lucide="layers" class="w-3 h-3 mr-1"></i>
                                                {{ $rel->domain->name }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-purple-50 text-purple-700 border border-purple-200 font-semibold text-[11px]">
                                                <i data-lucide="globe" class="w-3 h-3 mr-1"></i>
                                                Semua Domain (Global Smart Host)
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-mono text-[11px] text-slate-600">
                                        {{ $rel->host }}:{{ $rel->port }}
                                        <span class="text-slate-400 uppercase text-[10px]">({{ $rel->encryption ?: 'none' }})</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($rel->is_active)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                                                Aktif
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 text-slate-600 border border-slate-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400 mr-1.5"></span>
                                                Nonaktif
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-[11px]">
                                        @if($rel->last_tested_at)
                                            @if($rel->last_test_status === 'success')
                                                <span class="text-emerald-700 font-medium flex items-center">
                                                    <i data-lucide="check" class="w-3.5 h-3.5 mr-1 text-emerald-500"></i>
                                                    Terhubung ({{ $rel->last_tested_at->diffForHumans() }})
                                                </span>
                                            @else
                                                <span class="text-rose-700 font-medium flex items-center">
                                                    <i data-lucide="alert-circle" class="w-3.5 h-3.5 mr-1 text-rose-500"></i>
                                                    Gagal ({{ $rel->last_tested_at->diffForHumans() }})
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-slate-400">Belum diuji</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end space-x-1.5">
                                            <a href="{{ route('admin.relays', ['relay_id' => $rel->id]) }}#editRelaySection" 
                                               class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all {{ $isSelected ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                                                {{ $isSelected ? 'Sedang Dipilih' : 'Edit / Pilih' }}
                                            </a>
                                            <button type="button" 
                                                    onclick="confirmDeleteRelay({{ $rel->id }}, '{{ addslashes($rel->name) }}')"
                                                    class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition-colors" 
                                                    title="Hapus Profil Relay">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                            </button>
                                            <form id="deleteRelayForm_{{ $rel->id }}" action="{{ route('admin.relays.destroy', $rel->id) }}" method="POST" class="hidden">
                                                @csrf
                                                @method('DELETE')
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Kartu Detail Konfigurasi & Uji Relay Terpilih -->
        @if($activeRelay)
            <div id="editRelaySection" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Kolom Kiri: Form Konfigurasi Relay -->
                <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-5">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100">
                        <div class="flex items-center space-x-2.5">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-xs">
                                <i data-lucide="send" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-800">Edit Profil: {{ $activeRelay->name }}</h3>
                                <p class="text-[11px] text-slate-400">
                                    Cakupan: 
                                    @if($activeRelay->domain)
                                        <strong class="text-blue-600 font-mono">Domain {{ $activeRelay->domain->name }}</strong>
                                    @else
                                        <strong class="text-purple-600">Semua Domain (Global Smart Host)</strong>
                                    @endif
                                </p>
                            </div>
                        </div>

                        <!-- Presets Dropdown / Quick Buttons -->
                        <div class="flex items-center space-x-1.5 text-xs">
                            <span class="text-[11px] text-slate-400">Preset:</span>
                            <button type="button" onclick="setStandardTlsPreset()" class="px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-medium hover:bg-blue-100 text-[11px]">
                                TLS (587)
                            </button>
                            <button type="button" onclick="setSecureSslPreset()" class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-medium hover:bg-slate-200 text-[11px]">
                                SSL (465)
                            </button>
                            <button type="button" onclick="setMtaPort25Preset()" class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-medium hover:bg-slate-200 text-[11px]">
                                MTA (25)
                            </button>
                        </div>
                    </div>

                    <form action="{{ route('admin.relays.update', $activeRelay->id) }}" method="POST" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Profil Relay:</label>
                                <input type="text" id="relayName" name="name" value="{{ old('name', $activeRelay->name) }}" required
                                       class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white font-medium">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Cakupan Domain (Domain Scope):</label>
                                <select id="relayDomainId" name="domain_id" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                                    <option value="" {{ empty($activeRelay->domain_id) ? 'selected' : '' }}>Semua Domain (Global Smart Host)</option>
                                    @foreach($domains as $dom)
                                        <option value="{{ $dom->id }}" {{ $activeRelay->domain_id == $dom->id ? 'selected' : '' }}>
                                            Khusus Domain: {{ $dom->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="text-[10px] text-slate-400 mt-1">Pilih domain tertentu jika relay ini hanya untuk domain tersebut. Domain lain tidak akan terpengaruh.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Relay Host / Smart Host:</label>
                                <input type="text" id="relayHost" name="host" value="{{ old('host', $activeRelay->host) }}" required placeholder="mail.relay-example.com"
                                       class="w-full px-3 py-1.5 text-xs font-mono bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Port:</label>
                                <input type="number" id="relayPort" name="port" value="{{ old('port', $activeRelay->port) }}" required
                                       class="w-full px-3 py-1.5 text-xs font-mono bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Tipe Provider:</label>
                                <select id="relayProvider" name="provider" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                                    <option value="smarthost" {{ $activeRelay->provider === 'smarthost' ? 'selected' : '' }}>Smart Host Relay</option>
                                    <option value="standard" {{ in_array($activeRelay->provider, ['standard', 'brevo']) ? 'selected' : '' }}>Standard SMTP Relay</option>
                                    <option value="custom" {{ in_array($activeRelay->provider, ['custom', 'ses', 'mailgun']) ? 'selected' : '' }}>Custom External MTA</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Enkripsi:</label>
                                <select id="relayEncryption" name="encryption" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                                    <option value="tls" {{ strtolower($activeRelay->encryption) === 'tls' ? 'selected' : '' }}>STARTTLS (Direkomendasikan)</option>
                                    <option value="ssl" {{ strtolower($activeRelay->encryption) === 'ssl' ? 'selected' : '' }}>SSL/TLS Wrapper (465)</option>
                                    <option value="" {{ empty($activeRelay->encryption) ? 'selected' : '' }}>Tanpa Enkripsi (Plain)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Username / Auth ID:</label>
                                <input type="text" name="username" value="{{ old('username', $activeRelay->username) }}" placeholder="Username SMTP (opsional)"
                                       class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Password / SMTP Key:</label>
                            <input type="password" name="password" placeholder="Biarkan kosong jika tidak mengubah password atau tidak menggunakan autentikasi"
                                   class="w-full px-3 py-1.5 text-xs font-mono bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Default Sender Email (Fallback):</label>
                                <input type="email" name="sender_email" value="{{ old('sender_email', $activeRelay->sender_email) }}" placeholder="no-reply@domain.com"
                                       class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Default Sender Name (Fallback):</label>
                                <input type="text" name="sender_name" value="{{ old('sender_name', $activeRelay->sender_name) }}" placeholder="ApexMail System"
                                       class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                            </div>
                        </div>

                        <!-- Checkbox Kontrol Relay Eksternal -->
                        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                            <label class="flex items-start space-x-3 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" {{ $activeRelay->is_active ? 'checked' : '' }}
                                       class="rounded text-blue-600 focus:ring-blue-500 w-4 h-4 mt-0.5">
                                <div>
                                    <span class="text-xs font-bold text-slate-800">Aktifkan External SMTP Relay (Smart Host)</span>
                                    <p class="text-[11px] text-slate-500 leading-normal">
                                        Bila dicentang, email keluar untuk cakupan domain ini diteruskan melalui host relay di atas. Bila <strong>tidak dicentang</strong>, domain ini akan beralih ke Direct MX Delivery (atau Global Relay jika ada).
                                    </p>
                                </div>
                            </label>

                            <label class="flex items-start space-x-3 cursor-pointer pt-1 border-t border-slate-200/60">
                                <input type="checkbox" name="is_default" value="1" {{ $activeRelay->is_default ? 'checked' : '' }}
                                       class="rounded text-blue-600 focus:ring-blue-500 w-4 h-4 mt-0.5">
                                <div>
                                    <span class="text-xs font-semibold text-slate-700">Jadikan Relay Utama (Default) untuk domain ini</span>
                                </div>
                            </label>
                        </div>

                        <div class="pt-2 flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold shadow-sm shadow-blue-500/25 transition-all flex items-center space-x-1.5">
                                <i data-lucide="save" class="w-3.5 h-3.5"></i>
                                <span>Simpan Konfigurasi Relay</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Kolom Kanan: Uji Koneksi & Live Diagnostics -->
                <div class="space-y-6">

                    <!-- Diagnostic 1: Uji Konektivitas Direct MX Port 25 -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                        <div class="flex items-center space-x-2">
                            <i data-lucide="globe" class="w-4 h-4 text-blue-600"></i>
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Uji Direct MX Port 25</h3>
                        </div>
                        <p class="text-xs text-slate-500 leading-relaxed">
                            Lakukan resolusi DNS MX dan uji jabat tangan port 25 STARTTLS langsung ke server penerima tujuan.
                        </p>

                        <form action="{{ route('admin.relays.test-direct') }}" method="POST" class="space-y-3">
                            @csrf
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Email Tujuan Uji Direct MX:</label>
                                <input type="email" name="test_email" required placeholder="tujuan@gmail.com"
                                       class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white font-medium">
                            </div>

                            <button type="submit" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold shadow-sm transition-all flex items-center justify-center space-x-1.5">
                                <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                                <span>Uji Resolusi & Port 25 MX</span>
                            </button>
                        </form>
                    </div>

                    <!-- Diagnostic 2: Uji Koneksi Relay Eksternal -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3">
                        <div class="flex items-center space-x-2">
                            <i data-lucide="zap" class="w-4 h-4 text-amber-500"></i>
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Uji Relay: {{ $activeRelay->name }}</h3>
                        </div>
                        <p class="text-xs text-slate-500 leading-relaxed">
                            Kirimkan email diagnostik melalui host relay <strong class="font-mono text-slate-700">{{ $activeRelay->host }}:{{ $activeRelay->port }}</strong> untuk memverifikasi autentikasi.
                        </p>

                        <form action="{{ route('admin.relays.test', $activeRelay->id) }}" method="POST" class="space-y-3">
                            @csrf
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Kirim Email Tes Ke:</label>
                                <input type="email" name="test_email" required placeholder="email.pribadi@gmail.com"
                                       class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white font-medium">
                            </div>

                            <button type="submit" class="w-full py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold shadow-sm transition-all flex items-center justify-center space-x-1.5">
                                <i data-lucide="activity" class="w-3.5 h-3.5 text-emerald-400"></i>
                                <span>Kirim Email Pengujian Relay</span>
                            </button>
                        </form>

                        <!-- Hasil Uji Terakhir Relay -->
                        @if($activeRelay->last_tested_at)
                            <div class="pt-3 border-t border-slate-100 space-y-1.5 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-400 text-[11px]">Status Uji Relay Terakhir:</span>
                                    @if($activeRelay->last_test_status === 'success')
                                        <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-bold text-[10px] border border-emerald-200">
                                            ✔ Sukses Terhubung
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 font-bold text-[10px] border border-rose-200">
                                            ✖ Gagal Terhubung
                                        </span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-slate-400">
                                    Waktu: {{ $activeRelay->last_tested_at->diffForHumans() }}
                                </div>
                                @if($activeRelay->last_test_message)
                                    <div class="p-2 rounded bg-slate-50 text-[11px] text-slate-600 font-mono break-words border border-slate-100">
                                        {{ $activeRelay->last_test_message }}
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    <!-- Panduan DNS SPF Singkat -->
                    <div class="bg-blue-50/60 rounded-2xl border border-blue-100 p-5 space-y-2 text-xs text-blue-900">
                        <div class="flex items-center space-x-2 font-bold text-blue-800">
                            <i data-lucide="info" class="w-4 h-4"></i>
                            <span>Catatan Deliverability & SPF</span>
                        </div>
                        <p class="text-[11px] text-blue-700 leading-relaxed">
                            Pastikan DNS SPF record di masing-masing domain publik Anda telah mencantumkan host relay yang digunakan:
                            <code class="block font-mono bg-white/80 p-1.5 rounded mt-1 text-[10px] text-slate-800 border border-blue-200">v=spf1 a mx include:{{ $activeRelay->host }} ~all</code>
                        </p>
                    </div>

                </div>

            </div>
        @endif

    </div>
</div>

<!-- Modal Tambah Profil SMTP Relay Baru -->
<div id="addRelayModal" class="hidden fixed inset-0 z-50 items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity duration-200">
    <div class="w-full max-w-xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden transform transition-all" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between p-5 border-b border-slate-100 bg-slate-50/50">
            <div class="flex items-center space-x-2.5">
                <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-xs">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Tambah Profil SMTP Relay Baru</h3>
                    <p class="text-[11px] text-slate-400">Konfigurasi relay khusus domain tertentu atau fallback global.</p>
                </div>
            </div>
            <button type="button" onclick="closeAddRelayModal()" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition-colors">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <form action="{{ route('admin.relays.store') }}" method="POST" class="p-5 space-y-4">
            @csrf

            <!-- Preset Buttons -->
            <div class="flex items-center justify-between p-2.5 bg-blue-50/60 border border-blue-100 rounded-xl text-xs">
                <span class="text-[11px] font-semibold text-blue-900">Isi Cepat dari Preset:</span>
                <div class="flex items-center space-x-1.5">
                    <button type="button" onclick="setModalPreset('tls')" class="px-2 py-0.5 rounded bg-white text-blue-700 font-semibold border border-blue-200 hover:bg-blue-50 text-[11px]">
                        TLS (587)
                    </button>
                    <button type="button" onclick="setModalPreset('ssl')" class="px-2 py-0.5 rounded bg-white text-slate-700 font-medium border border-slate-200 hover:bg-slate-50 text-[11px]">
                        SSL (465)
                    </button>
                    <button type="button" onclick="setModalPreset('mta')" class="px-2 py-0.5 rounded bg-white text-slate-700 font-medium border border-slate-200 hover:bg-slate-50 text-[11px]">
                        MTA (25)
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Profil Relay:</label>
                    <input type="text" id="modalRelayName" name="name" value="SMTP Relay Baru" required
                           class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white font-medium">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Cakupan Domain (Domain Scope):</label>
                    <select id="modalRelayDomainId" name="domain_id" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white font-medium">
                        <option value="">Semua Domain (Global Smart Host)</option>
                        @foreach($domains as $dom)
                            <option value="{{ $dom->id }}">Khusus Domain: {{ $dom->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Relay Host / Smart Host:</label>
                    <input type="text" id="modalRelayHost" name="host" required placeholder="smtp-relay.brevo.com"
                           class="w-full px-3 py-1.5 text-xs font-mono bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Port:</label>
                    <input type="number" id="modalRelayPort" name="port" value="587" required
                           class="w-full px-3 py-1.5 text-xs font-mono bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Tipe Provider:</label>
                    <select id="modalRelayProvider" name="provider" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                        <option value="smarthost">Smart Host Relay</option>
                        <option value="standard" selected>Standard SMTP Relay</option>
                        <option value="custom">Custom External MTA</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Enkripsi:</label>
                    <select id="modalRelayEncryption" name="encryption" class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                        <option value="tls" selected>STARTTLS (Direkomendasikan)</option>
                        <option value="ssl">SSL/TLS Wrapper (465)</option>
                        <option value="">Tanpa Enkripsi (Plain)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Username / Auth ID:</label>
                    <input type="text" name="username" placeholder="Username (opsional)"
                           class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Password / SMTP Key:</label>
                <input type="password" name="password" placeholder="Password atau API SMTP Key"
                       class="w-full px-3 py-1.5 text-xs font-mono bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Sender Email Fallback:</label>
                    <input type="email" name="sender_email" placeholder="no-reply@domain.com"
                           class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Sender Name Fallback:</label>
                    <input type="text" name="sender_name" value="ApexMail System" placeholder="ApexMail System"
                           class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                </div>
            </div>

            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                <label class="flex items-center space-x-2.5 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded text-blue-600 focus:ring-blue-500 w-4 h-4">
                    <span class="text-xs font-bold text-slate-800">Aktifkan Profil Relay Ini Langsung</span>
                </label>
                <label class="flex items-center space-x-2.5 cursor-pointer">
                    <input type="checkbox" name="is_default" value="1" checked class="rounded text-blue-600 focus:ring-blue-500 w-4 h-4">
                    <span class="text-xs font-medium text-slate-700">Jadikan Relay Utama (Default) untuk cakupan domain yang dipilih</span>
                </label>
            </div>

            <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeAddRelayModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold shadow-sm shadow-blue-500/25 transition-all flex items-center space-x-1.5">
                    <i data-lucide="check" class="w-3.5 h-3.5"></i>
                    <span>Simpan Profil Relay</span>
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function setStandardTlsPreset() {
        const nameInput = document.getElementById('relayName');
        if (nameInput) nameInput.value = 'Primary SMTP Relay';
        const providerInput = document.getElementById('relayProvider');
        if (providerInput) providerInput.value = 'standard';
        const portInput = document.getElementById('relayPort');
        if (portInput) portInput.value = '587';
        const encInput = document.getElementById('relayEncryption');
        if (encInput) encInput.value = 'tls';
    }

    function setSecureSslPreset() {
        const nameInput = document.getElementById('relayName');
        if (nameInput) nameInput.value = 'Secure SMTP Relay';
        const providerInput = document.getElementById('relayProvider');
        if (providerInput) providerInput.value = 'custom';
        const portInput = document.getElementById('relayPort');
        if (portInput) portInput.value = '465';
        const encInput = document.getElementById('relayEncryption');
        if (encInput) encInput.value = 'ssl';
    }

    function setMtaPort25Preset() {
        const nameInput = document.getElementById('relayName');
        if (nameInput) nameInput.value = 'Internal Smart Host MTA';
        const providerInput = document.getElementById('relayProvider');
        if (providerInput) providerInput.value = 'smarthost';
        const portInput = document.getElementById('relayPort');
        if (portInput) portInput.value = '25';
        const encInput = document.getElementById('relayEncryption');
        if (encInput) encInput.value = 'tls';
    }

    function openAddRelayModal() {
        const modal = document.getElementById('addRelayModal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    }

    function closeAddRelayModal() {
        const modal = document.getElementById('addRelayModal');
        if (modal) {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }
    }

    function setModalPreset(type) {
        const name = document.getElementById('modalRelayName');
        const host = document.getElementById('modalRelayHost');
        const port = document.getElementById('modalRelayPort');
        const prov = document.getElementById('modalRelayProvider');
        const enc = document.getElementById('modalRelayEncryption');

        if (type === 'tls') {
            if (name) name.value = 'Brevo / TLS Relay';
            if (host && !host.value) host.value = 'smtp-relay.brevo.com';
            if (port) port.value = '587';
            if (prov) prov.value = 'standard';
            if (enc) enc.value = 'tls';
        } else if (type === 'ssl') {
            if (name) name.value = 'Secure SSL Relay';
            if (host && !host.value) host.value = 'smtp.gmail.com';
            if (port) port.value = '465';
            if (prov) prov.value = 'custom';
            if (enc) enc.value = 'ssl';
        } else if (type === 'mta') {
            if (name) name.value = 'Internal Smart Host';
            if (port) port.value = '25';
            if (prov) prov.value = 'smarthost';
            if (enc) enc.value = 'tls';
        }
    }

    function confirmDeleteRelay(id, name) {
        if (typeof showConfirmModal === 'function') {
            showConfirmModal(
                'Hapus Profil Relay?',
                `Apakah Anda yakin ingin menghapus profil relay "${name}"? Domain yang menggunakan profil ini akan beralih ke Direct MX Delivery (atau Global Smart Host jika tersedia).`,
                () => {
                    const form = document.getElementById('deleteRelayForm_' + id);
                    if (form) form.submit();
                },
                'Ya, Hapus Relay',
                true
            );
        } else {
            if (confirm(`Hapus profil relay "${name}"?`)) {
                const form = document.getElementById('deleteRelayForm_' + id);
                if (form) form.submit();
            }
        }
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAddRelayModal();
        }
    });
</script>
@endpush
@endsection
