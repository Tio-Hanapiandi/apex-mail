@extends('layouts.app')

@section('title', 'Buku Kontak & Direktori GAL - ApexMail')

@section('content')
<div class="flex-1 bg-[#f8fafc] overflow-y-auto p-3.5 sm:p-6 lg:p-8">
    <div class="max-w-5xl mx-auto space-y-6">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200">
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Direktori Kontak & Global Address List (GAL)</h1>
                <p class="text-xs text-slate-500 mt-0.5">Daftar kontak perusahaan dan buku alamat yang tersinkronisasi otomatis.</p>
            </div>

            <!-- Search Bar -->
            <form action="{{ route('groupware.contacts') }}" method="GET" class="relative w-full sm:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-2.5 text-slate-400"></i>
                <input type="text" name="q" value="{{ $query }}" placeholder="Cari nama, email, jabatan..."
                       class="w-full pl-8 pr-3 py-1.5 bg-white text-xs rounded-lg text-slate-800 border border-slate-200 focus:outline-none focus:ring-1 focus:ring-blue-500">
            </form>
        </div>

        <!-- Grid Kontak -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($contacts as $c)
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 hover:border-blue-200 transition-all space-y-3">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-700 flex items-center justify-center font-bold text-xs">
                                {{ strtoupper(substr($c->name, 0, 2)) }}
                            </div>
                            <div>
                                <h3 class="font-bold text-xs text-slate-900">{{ $c->name }}</h3>
                                <p class="text-[11px] text-slate-500">{{ $c->job_title ?: 'Karyawan' }}</p>
                            </div>
                        </div>

                        @if($c->is_gal)
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                GAL
                            </span>
                        @endif
                    </div>

                    <div class="space-y-1.5 pt-2 border-t border-slate-100 text-xs">
                        <div class="flex items-center text-slate-600 truncate">
                            <i data-lucide="mail" class="w-3.5 h-3.5 mr-2 text-slate-400 flex-shrink-0"></i>
                            <a href="{{ route('webmail.index') }}?q={{ urlencode($c->email) }}" class="truncate hover:text-blue-600 font-mono text-[11px]">
                                {{ $c->email }}
                            </a>
                        </div>
                        @if($c->phone)
                            <div class="flex items-center text-slate-600">
                                <i data-lucide="phone" class="w-3.5 h-3.5 mr-2 text-slate-400 flex-shrink-0"></i>
                                <span class="text-[11px]">{{ $c->phone }}</span>
                            </div>
                        @endif
                        @if($c->company)
                            <div class="flex items-center text-slate-600">
                                <i data-lucide="briefcase" class="w-3.5 h-3.5 mr-2 text-slate-400 flex-shrink-0"></i>
                                <span class="text-[11px] text-slate-500">{{ $c->company }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-span-3 text-center py-12 text-slate-400">
                    <i data-lucide="users" class="w-10 h-10 mx-auto mb-2 text-slate-300"></i>
                    <p class="text-xs font-medium">Tidak ada kontak yang ditemukan.</p>
                </div>
            @endforelse
        </div>

    </div>
</div>
@endsection
