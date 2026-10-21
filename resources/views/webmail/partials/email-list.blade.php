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

@forelse($emails as $index => $mail)
    @php
        $isSelected = ($selectedEmail && $selectedEmail->id === $mail->id);
        $senderDisplay = $mail->from_name ?: $mail->from_email;
        $avatarStyle = getAvatarColor($mail->from_email ?: $senderDisplay);
        $initials = strtoupper(substr($senderDisplay, 0, 2));
        
        // Human friendly relative time
        $timeFormatted = '';
        if ($mail->sent_at) {
            if ($mail->sent_at->isToday()) {
                $timeFormatted = $mail->sent_at->format('H:i');
            } elseif ($mail->sent_at->isYesterday()) {
                $timeFormatted = 'Kemarin';
            } elseif ($mail->sent_at->isCurrentYear()) {
                $timeFormatted = $mail->sent_at->translatedFormat('d M');
            } else {
                $timeFormatted = $mail->sent_at->format('d/m/y');
            }
        }
    @endphp
    
    <div data-email-id="{{ $mail->id }}" 
         data-index="{{ $index }}"
         class="email-row group relative block p-3.5 transition-all cursor-pointer select-none border-b border-slate-100 border-l-[4px] {{ $isSelected ? 'bg-blue-50/90 border-l-blue-600 shadow-xs ring-1 ring-blue-500/20' : (!$mail->is_read ? 'bg-white border-l-blue-600 shadow-2xs hover:bg-blue-50/30' : 'bg-slate-50/50 border-l-transparent hover:bg-slate-100/70') }}"
         onclick="handleRowClick(event, '{{ route('webmail.index', array_filter(['folder' => $currentFolder->slug ?? 'inbox', 'email' => $mail->id, 'page' => $emails->currentPage() > 1 ? $emails->currentPage() : null, 'q' => $query, 'filter' => $activeFilter])) }}')">

        <div class="flex items-start space-x-3">
            
            <!-- Avatar Sender & Checkbox Switch -->
            <div class="relative flex-shrink-0 mt-0.5">
                <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-xs border {{ $avatarStyle['bg'] }} shadow-2xs group-hover:hidden">
                    {{ $initials }}
                </div>
                <div class="hidden group-hover:flex w-9 h-9 items-center justify-center">
                    <input type="checkbox" value="{{ $mail->id }}" 
                           class="email-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4 cursor-pointer"
                           onclick="event.stopPropagation(); updateBatchBar();">
                </div>
                @if(!$mail->is_read)
                    <span class="w-3 h-3 rounded-full bg-blue-600 ring-2 ring-white absolute -top-1 -right-1 shadow-xs" title="Pesan Baru / Belum Dibaca"></span>
                @endif
            </div>

            <!-- Info Pesan & Pratinjau -->
            <div class="flex-1 min-w-0">
                
                <!-- Baris 1: Nama Pengirim & Waktu -->
                <div class="flex items-center justify-between mb-0.5">
                    <span class="text-xs truncate {{ !$mail->is_read ? 'font-black text-slate-950 text-[13px]' : 'font-medium text-slate-700' }}">
                        {{ $senderDisplay }}
                    </span>

                    <div class="flex items-center space-x-1.5 flex-shrink-0 ml-2">
                        <span class="text-[11px] {{ !$mail->is_read ? 'font-bold text-blue-600' : 'font-medium text-slate-400' }} whitespace-nowrap">
                            {{ $timeFormatted }}
                        </span>
                        
                        <!-- Contextual Star Action -->
                        <button type="button" onclick="event.stopPropagation(); toggleStarAjax({{ $mail->id }}, this)" 
                                class="text-slate-300 hover:text-amber-500 p-0.5 transition-colors" title="Beri Bintang">
                            <i data-lucide="star" class="w-3.5 h-3.5 {{ $mail->is_starred ? 'fill-amber-400 text-amber-500' : '' }}"></i>
                        </button>
                    </div>
                </div>

                <!-- Baris 2: Subjek Surat -->
                <div class="text-xs truncate mb-1 {{ !$mail->is_read ? 'font-bold text-slate-900' : 'text-slate-600' }}">
                    {{ $mail->subject ?: '(Tanpa Subjek)' }}
                </div>

                <!-- Baris 3: Cuplikan Isi Pesan (Snippet) yang Mudah Dibaca -->
                <p class="text-xs line-clamp-2 leading-relaxed {{ !$mail->is_read ? 'text-slate-700 font-medium' : 'text-slate-400 font-normal' }}">
                    {{ $mail->snippet ?: strip_tags($mail->body_text) }}
                </p>

                <!-- Baris 4: Badges (Lampiran / Inbound Tag) -->
                <div class="flex items-center space-x-2 mt-2">
                    @if(!$mail->is_read)
                        <span class="inline-flex items-center space-x-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-600 text-white shadow-xs">
                            <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                            <span>Baru</span>
                        </span>
                    @endif

                    @if($mail->has_attachments)
                        <span class="inline-flex items-center space-x-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                            <i data-lucide="paperclip" class="w-3 h-3 text-slate-500"></i>
                            <span>Lampiran</span>
                        </span>
                    @endif
                </div>

            </div>
        </div>
    </div>
@empty
    <div class="p-12 text-center text-slate-400 space-y-3">
        <div class="w-14 h-14 rounded-2xl bg-blue-50 text-blue-500 flex items-center justify-center mx-auto shadow-xs">
            <i data-lucide="inbox" class="w-7 h-7"></i>
        </div>
        <div class="space-y-1">
            <p class="text-sm font-bold text-slate-700">Tidak ada email ditemukan</p>
            <p class="text-xs text-slate-400 max-w-xs mx-auto leading-relaxed">
                @if(!empty($query))
                    Tidak ada pesan yang cocok dengan pencarian "<strong>{{ $query }}</strong>".
                @else
                    Kotak surat ini saat ini kosong dan siap menerima pesan baru.
                @endif
            </p>
        </div>
        @if(!empty($query))
            <button type="button" onclick="clearLiveSearch()" 
               class="inline-block mt-2 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold transition-colors cursor-pointer">
                Hapus Filter Pencarian
            </button>
        @endif
    </div>
@endforelse
