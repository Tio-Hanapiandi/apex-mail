@extends('layouts.app')

@section('title', 'Kalender & Jadwal Rapat - ApexMail')

@section('content')
<div class="flex-1 bg-[#f8fafc] overflow-y-auto p-3.5 sm:p-6 lg:p-8">
    <div class="max-w-5xl mx-auto space-y-6">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200">
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Kalender & Penjadwalan Rapat</h1>
                <p class="text-xs text-slate-500 mt-0.5">Sinkronisasi jadwal, rapat koordinasi, dan tautan pertemuan virtual.</p>
            </div>

            <button class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold shadow-sm shadow-blue-500/20 flex items-center space-x-1.5 self-start sm:self-auto">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>Buat Jadwal Baru</span>
            </button>
        </div>

        <!-- Agenda Acara -->
        <div class="space-y-4">
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Agenda Kegiatan Mendatang</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @forelse($events as $ev)
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4 hover:border-blue-200 transition-all">
                        <div class="flex items-start justify-between">
                            <div class="space-y-1">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-50 text-blue-700 border border-blue-200">
                                    {{ $ev->start_time->isoFormat('dddd, D MMMM Y') }}
                                </span>
                                <h3 class="font-bold text-sm text-slate-900 mt-1">{{ $ev->title }}</h3>
                            </div>
                            <div class="w-3 h-3 rounded-full" style="background-color: {{ $ev->color }}"></div>
                        </div>

                        <p class="text-xs text-slate-500 leading-relaxed">
                            {{ $ev->description }}
                        </p>

                        <div class="space-y-2 pt-2 border-t border-slate-100 text-xs">
                            <div class="flex items-center text-slate-600">
                                <i data-lucide="clock" class="w-3.5 h-3.5 mr-2 text-slate-400"></i>
                                <span>{{ $ev->start_time->format('H:i') }} - {{ $ev->end_time->format('H:i') }} WIB</span>
                            </div>

                            @if($ev->location)
                                <div class="flex items-center text-slate-600">
                                    <i data-lucide="map-pin" class="w-3.5 h-3.5 mr-2 text-slate-400"></i>
                                    <span>{{ $ev->location }}</span>
                                </div>
                            @endif

                            @if($ev->meeting_link)
                                <div class="flex items-center text-blue-600">
                                    <i data-lucide="video" class="w-3.5 h-3.5 mr-2 text-blue-500"></i>
                                    <a href="{{ $ev->meeting_link }}" target="_blank" class="hover:underline font-medium text-[11px] truncate">
                                        Masuk Ruang Rapat Online
                                    </a>
                                </div>
                            @endif
                        </div>

                        @if($ev->attendees && count($ev->attendees) > 0)
                            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400">
                                <span>Peserta ({{ count($ev->attendees) }}):</span>
                                <div class="flex -space-x-1">
                                    @foreach($ev->attendees as $att)
                                        <div class="w-6 h-6 rounded-full bg-slate-100 text-slate-700 font-bold border border-white text-[9px] flex items-center justify-center" title="{{ $att }}">
                                            {{ strtoupper(substr($att, 0, 1)) }}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="col-span-2 text-center py-12 text-slate-400 bg-white rounded-2xl border border-slate-200">
                        <i data-lucide="calendar" class="w-10 h-10 mx-auto mb-2 text-slate-300"></i>
                        <p class="text-xs font-medium">Belum ada agenda rapat yang dijadwalkan.</p>
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</div>
@endsection
