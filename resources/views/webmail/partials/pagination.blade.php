@if(isset($emails) && $emails->hasPages())
    <div class="p-2.5 bg-white border-t border-slate-200 flex items-center justify-between text-xs text-slate-500">
        <span class="text-[11px]">Hal {{ $emails->currentPage() }} dari {{ $emails->lastPage() }}</span>
        <div class="flex items-center space-x-1">
            @if(!$emails->onFirstPage())
                <a href="{{ $emails->previousPageUrl() }}" class="p-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                </a>
            @endif
            @if($emails->hasMorePages())
                <a href="{{ $emails->nextPageUrl() }}" class="p-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </a>
            @endif
        </div>
    </div>
@endif
