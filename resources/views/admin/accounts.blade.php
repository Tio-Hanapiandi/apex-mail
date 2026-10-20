@extends('layouts.app')

@section('title', 'Manajemen Akun Kotak Surat - ApexMail Admin')

@section('content')
<div class="flex-1 bg-[#f8fafc] overflow-y-auto p-3.5 sm:p-6 lg:p-8">
    <div class="max-w-6xl mx-auto space-y-6">

        <!-- Header Admin Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200">
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Manajemen Akun Kotak Surat</h1>
                <p class="text-xs text-slate-500 mt-0.5">Kelola akun email pengguna, kata sandi, penangguhan (*suspend*), dan kuota penyimpanan.</p>
            </div>

            <!-- Sub Navigasi Admin -->
            <div class="flex items-center space-x-1.5 overflow-x-auto no-scrollbar pb-1 max-w-full">
                <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Ringkasan Sistem
                </a>
                <a href="{{ route('admin.accounts') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-600 text-white shadow-sm shadow-blue-500/20">
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
                <a href="{{ route('admin.queue') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Antrean & Log
                </a>
                <a href="{{ route('admin.migration') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Migrasi Data
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Form Buat Akun Baru -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center">
                    <i data-lucide="user-plus" class="w-3.5 h-3.5 mr-1.5 text-blue-600"></i>
                    Buat Akun Kotak Surat
                </h3>
                <form action="{{ route('admin.accounts.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Domain:</label>
                        <select name="domain_id" required class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white font-medium">
                            @foreach($domains as $dom)
                                <option value="{{ $dom->id }}">{{ $dom->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Username:</label>
                        <input type="text" name="username" required placeholder="contoh: ahmad"
                               class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white font-mono">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Tampilan:</label>
                        <input type="text" name="display_name" required placeholder="Ahmad Wijaya"
                               class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-semibold text-slate-700">Email Pemulihan (Wajib):</label>
                            <span class="text-[10px] text-blue-600 font-semibold flex items-center">
                                <i data-lucide="shield-check" class="w-3 h-3 mr-0.5"></i> Untuk Reset OTP
                            </span>
                        </div>
                        <input type="email" name="recovery_email" value="{{ old('recovery_email') }}" required placeholder="contoh: user@gmail.com"
                               class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white text-slate-800">
                        <p class="text-[10px] text-slate-400 mt-1">Alamat email eksternal (Gmail, Yahoo, dll) untuk pengiriman kode OTP saat lupa kata sandi.</p>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-semibold text-slate-700">Kata Sandi Awal:</label>
                            <span class="text-[10px] text-blue-600 font-semibold flex items-center">
                                <i data-lucide="shield-alert" class="w-3 h-3 mr-0.5"></i> Standar Keamanan
                            </span>
                        </div>
                        <input type="password" name="password" required minlength="{{ $passwordPolicy['min_length'] ?? 8 }}" 
                               placeholder="Min. {{ $passwordPolicy['min_length'] ?? 8 }} karakter (huruf besar & simbol)"
                               class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white font-mono">
                        <div class="flex flex-wrap gap-1 mt-1.5 text-[10px]">
                            <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-medium">Min. {{ $passwordPolicy['min_length'] ?? 8 }} Karakter</span>
                            <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-medium">Huruf Besar (A-Z)</span>
                            <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-medium">Simbol Unik (!@#$)</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Alokasi Kuota Penyimpanan (GB):</label>
                        <input type="number" name="quota_gb" value="10" min="1" max="100" required
                               class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white font-medium">
                    </div>

                    <div class="pt-1">
                        <label class="inline-flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" name="is_admin" value="1" class="rounded text-blue-600 focus:ring-blue-500 w-4 h-4">
                            <span class="text-xs font-medium text-slate-700">Berikan Hak Administrator</span>
                        </label>
                    </div>

                    <button type="submit" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold shadow-sm transition-all">
                        Buat Kotak Surat
                    </button>
                </form>
            </div>

            <!-- Daftar Akun Kotak Surat & Aksi Kelola -->
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Kotak Surat Terdaftar ({{ $accounts->total() }})</h3>
                    
                    <!-- Search box -->
                    <form action="{{ route('admin.accounts') }}" method="GET" class="relative">
                        <i data-lucide="search" class="w-3.5 h-3.5 absolute left-2.5 top-2 text-slate-400"></i>
                        <input type="text" name="q" value="{{ $query ?? '' }}" placeholder="Cari nama atau email..."
                               class="pl-7 pr-3 py-1 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500">
                    </form>
                </div>

                <div class="divide-y divide-slate-100">
                    @foreach($accounts as $acc)
                        @php
                            $quotaGb = round($acc->quota_bytes / 1073741824, 1);
                            $usedGb = round($acc->used_bytes / 1073741824, 2);
                        @endphp
                        <div class="py-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="flex items-center space-x-3">
                                <div class="w-9 h-9 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-xs flex-shrink-0">
                                    {{ strtoupper(substr($acc->username, 0, 2)) }}
                                </div>
                                <div>
                                    <div class="flex items-center space-x-2">
                                        <span class="font-bold text-xs text-slate-800">{{ $acc->display_name }}</span>
                                        @if($acc->is_admin)
                                            <span class="px-1.5 py-0.2 rounded bg-purple-50 text-purple-700 text-[9px] font-bold border border-purple-200 uppercase">Admin</span>
                                        @endif
                                        @if($acc->two_factor_enabled)
                                            <span class="px-1.5 py-0.2 rounded bg-emerald-50 text-emerald-700 text-[9px] font-bold border border-emerald-200 uppercase flex items-center space-x-0.5">
                                                <i data-lucide="shield-check" class="w-2.5 h-2.5"></i>
                                                <span>2FA Aktif</span>
                                            </span>
                                        @endif
                                        @if(!$acc->is_active)
                                            <span class="px-1.5 py-0.2 rounded bg-rose-50 text-rose-700 text-[9px] font-bold border border-rose-200 uppercase">Suspended</span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-slate-400 font-mono">{{ $acc->email }}</div>
                                    <div class="text-[11px] flex items-center space-x-1 mt-0.5 font-medium {{ $acc->recovery_email ? 'text-emerald-600' : 'text-rose-500' }}">
                                        <i data-lucide="{{ $acc->recovery_email ? 'shield-check' : 'alert-circle' }}" class="w-3 h-3 flex-shrink-0"></i>
                                        <span>Pemulihan: {{ $acc->recovery_email ?: 'Belum diatur' }}</span>
                                    </div>
                                    <div class="text-[10px] text-slate-500 mt-0.5">
                                        Kapasitas: {{ $usedGb }} / {{ $quotaGb }} GB
                                    </div>
                                </div>
                            </div>

                            <!-- Tindakan Pengelolaan Akun -->
                            <div class="flex items-center space-x-1.5 self-end sm:self-center">
                                <!-- Kelola Akun & Reset Password Modal Button -->
                                <button type="button" onclick="openEditAccountModal({{ $acc->id }}, '{{ $acc->email }}', '{{ addslashes($acc->display_name) }}', '{{ $acc->recovery_email ?? '' }}', {{ $quotaGb }}, {{ $acc->two_factor_enabled ? 'true' : 'false' }})" 
                                        class="px-2.5 py-1 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-[11px] font-medium rounded-lg shadow-xs transition-colors flex items-center space-x-1" title="Kelola Akun & Kata Sandi">
                                    <i data-lucide="settings-2" class="w-3 h-3 text-slate-500"></i>
                                    <span>Kelola</span>
                                </button>

                                <!-- Toggle Suspend Status -->
                                <form action="{{ route('admin.accounts.toggle-status', $acc->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="px-2.5 py-1 text-[11px] font-medium rounded-lg border transition-colors {{ $acc->is_active ? 'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100' : 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' }}">
                                        {{ $acc->is_active ? 'Suspend' : 'Aktifkan' }}
                                    </button>
                                </form>

                                <!-- Hapus Akun -->
                                @if(auth()->id() !== $acc->id)
                                    <form id="deleteAccountForm_{{ $acc->id }}" action="{{ route('admin.accounts.destroy', $acc->id) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" onclick="showConfirmModal('Hapus Permanen Akun?', 'Apakah Anda yakin ingin menghapus permanen akun {{ $acc->email }} beserta semua emailnya?', () => document.getElementById('deleteAccountForm_{{ $acc->id }}').submit(), 'Ya, Hapus Akun', true)" class="p-1 text-slate-400 hover:text-rose-600 rounded-lg transition-colors" title="Hapus Akun">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="pt-2 border-t border-slate-100">
                    {{ $accounts->links() }}
                </div>
            </div>

        </div>

    </div>
</div>

<!-- Modal Kelola Akun & Sandi -->
<div id="editAccountModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl border border-slate-200 p-5 space-y-4" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between pb-2 border-b border-slate-100">
            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center space-x-1.5">
                <i data-lucide="user-cog" class="w-4 h-4 text-blue-600"></i>
                <span>Kelola Kotak Surat Pengguna</span>
            </h3>
            <button onclick="closeEditAccountModal()" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <p class="text-xs text-slate-500">
            Kelola data dan keamanan untuk akun: <br><strong id="editAccountEmail" class="font-mono text-slate-800 text-xs"></strong>
        </p>

        <form id="editAccountForm" action="" method="POST" class="space-y-3">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Tampilan:</label>
                <input type="text" id="editDisplayName" name="display_name" required
                       class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white font-medium">
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs font-semibold text-slate-700">Email Pemulihan (Wajib):</label>
                    <span class="text-[10px] text-blue-600 font-semibold flex items-center">
                        <i data-lucide="shield-check" class="w-3 h-3 mr-0.5"></i> Untuk Reset OTP
                    </span>
                </div>
                <input type="email" id="editRecoveryEmail" name="recovery_email" required placeholder="contoh: user@gmail.com"
                       class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white font-medium">
                <p class="text-[10px] text-slate-400 mt-1">Alamat email eksternal penerima kode OTP reset kata sandi.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Alokasi Kuota Penyimpanan (GB):</label>
                <input type="number" id="editQuotaGb" name="quota_gb" min="1" max="100" required
                       class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white font-medium">
            </div>

            <div class="pt-2 border-t border-slate-100">
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs font-semibold text-slate-700">Ganti Kata Sandi (Opsional):</label>
                    <span class="text-[10px] text-amber-600 font-semibold">Tidak boleh sama dengan sandi lama</span>
                </div>
                <input type="password" name="password" minlength="{{ $passwordPolicy['min_length'] ?? 8 }}" 
                       placeholder="Biarkan kosong jika tidak ingin mengubah sandi"
                       class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white font-mono">
                <div class="flex flex-wrap gap-1 mt-1.5 text-[10px]">
                    <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-medium">Min. {{ $passwordPolicy['min_length'] ?? 8 }} Karakter</span>
                    <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-medium">Huruf Besar (A-Z)</span>
                    <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-medium">Simbol Unik</span>
                    <span class="px-1.5 py-0.5 rounded bg-rose-50 text-rose-600 font-medium border border-rose-100">Berbeda dari Sandi Sebelumnya</span>
                </div>
            </div>

            <!-- Opsi Reset 2FA jika aktif -->
            <div id="reset2faContainer" class="p-3 bg-amber-50 border border-amber-200 rounded-xl space-y-1 hidden">
                <label class="inline-flex items-center space-x-2 cursor-pointer">
                    <input type="checkbox" name="reset_two_factor" value="1" class="rounded text-amber-600 focus:ring-amber-500 w-4 h-4">
                    <span class="text-xs font-bold text-amber-900">Reset / Nonaktifkan Google Authenticator (2FA)</span>
                </label>
                <p class="text-[10px] text-amber-700 leading-tight">Centang jika pengguna kehilangan perangkat ponsel mereka untuk menghapus verifikasi 2 langkah akun ini.</p>
            </div>

            <div class="pt-3 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeEditAccountModal()" class="px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-100 rounded-lg">
                    Batal
                </button>
                <button type="submit" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold shadow-xs">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function openEditAccountModal(id, email, displayName, recoveryEmail, quotaGb, twoFactorEnabled) {
        document.getElementById('editAccountEmail').textContent = email;
        document.getElementById('editDisplayName').value = displayName;
        document.getElementById('editRecoveryEmail').value = recoveryEmail;
        document.getElementById('editQuotaGb').value = quotaGb;
        document.getElementById('editAccountForm').action = `/admin/accounts/${id}`;
        
        const reset2fa = document.getElementById('reset2faContainer');
        if (twoFactorEnabled) {
            reset2fa.classList.remove('hidden');
        } else {
            reset2fa.classList.add('hidden');
        }

        document.getElementById('editAccountModal').classList.remove('hidden');
    }

    function closeEditAccountModal() {
        document.getElementById('editAccountModal').classList.add('hidden');
    }
</script>
@endpush
@endsection
