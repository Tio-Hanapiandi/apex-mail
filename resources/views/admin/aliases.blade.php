@extends('layouts.app')

@section('title', 'Manajemen Alias & Grup Distribusi - ApexMail Admin')

@section('content')
<div class="flex-1 bg-[#f8fafc] overflow-y-auto p-3.5 sm:p-6 lg:p-8">
    <div class="max-w-6xl mx-auto space-y-6">

        <!-- Top Header Admin Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200">
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Alias Email & Grup Distribusi</h1>
                <p class="text-xs text-slate-500 mt-0.5">Kelola alamat forwarder (misal: <code>info@domain.com</code> diteruskan ke beberapa staf).</p>
            </div>

            <div class="flex items-center space-x-1.5 overflow-x-auto no-scrollbar pb-1 max-w-full">
                <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Ringkasan Sistem
                </a>
                <a href="{{ route('admin.accounts') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Kelola Akun
                </a>
                <a href="{{ route('admin.aliases') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-600 text-white shadow-sm shadow-blue-500/20">
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

            <!-- Form Buat Alias Baru -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center">
                    <i data-lucide="plus-circle" class="w-3.5 h-3.5 mr-1.5 text-blue-600"></i>
                    Buat Alias / Grup Baru
                </h3>

                <form action="{{ route('admin.aliases.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Domain:</label>
                        <select name="domain_id" required class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white">
                            @foreach($domains as $dom)
                                <option value="{{ $dom->id }}">{{ $dom->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Alamat Sumber (Source):</label>
                        <div class="relative">
                            <input type="text" name="source_username" required placeholder="contoh: info atau support"
                                   class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white font-mono">
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1">Otomatis digabungkan dengan nama domain yang dipilih.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Tujuan Penerusan (Pisahkan koma):</label>
                        <textarea name="destination_emails" rows="3" required placeholder="budi@apexmail.local, sarah@apexmail.local"
                                  class="w-full p-2.5 text-xs font-mono bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white"></textarea>
                    </div>

                    <button type="submit" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold shadow-sm transition-all">
                        Simpan Alias / Grup
                    </button>
                </form>
            </div>

            <!-- Daftar Alias Terdaftar -->
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Daftar Alias Terdaftar ({{ $aliases->total() }})</h3>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($aliases as $al)
                        <div class="py-3.5 flex items-start justify-between">
                            <div class="space-y-1">
                                <div class="flex items-center space-x-2">
                                    <span class="font-bold text-xs text-blue-700 font-mono">{{ $al->source_email }}</span>
                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-blue-50 text-blue-700 border border-blue-200 uppercase">Forwarder</span>
                                </div>
                                <div class="text-[11px] text-slate-500">
                                    Diteruskan ke: <code class="font-mono text-slate-700 bg-slate-50 px-1.5 py-0.5 rounded">{{ $al->destination_emails }}</code>
                                </div>
                            </div>

                            <form id="deleteAliasForm_{{ $al->id }}" action="{{ route('admin.aliases.destroy', $al->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="button" onclick="showConfirmModal('Hapus Alias Email?', 'Apakah Anda yakin ingin menghapus alias {{ $al->source_email }}?', () => document.getElementById('deleteAliasForm_{{ $al->id }}').submit(), 'Ya, Hapus Alias', true)" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg transition-colors" title="Hapus Alias">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 py-8 text-center">Belum ada alias email yang didaftarkan.</p>
                    @endforelse
                </div>
            </div>

        </div>

    </div>
</div>
@endsection
