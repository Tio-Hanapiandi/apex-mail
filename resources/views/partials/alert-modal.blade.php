<!-- Universal Alert & Confirmation Modal Component (ApexMail Unified UI/UX) -->
<div id="apexAlertModal" class="hidden fixed inset-0 z-[9999] items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity duration-200" style="margin: 0 !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important;">
    <div class="relative w-full max-w-sm bg-white rounded-3xl shadow-2xl border border-slate-200 p-6 space-y-4 text-center transform transition-all duration-200 scale-95 opacity-0 my-auto" id="apexAlertModalCard" style="margin-left: auto !important; margin-right: auto !important;">
        <!-- Icon Container -->
        <div id="apexAlertModalIconWrapper" class="w-14 h-14 rounded-2xl mx-auto flex items-center justify-center shadow-sm">
            <i id="apexAlertModalIcon" data-lucide="info" class="w-7 h-7"></i>
        </div>

        <!-- Title & Message -->
        <div class="space-y-1.5">
            <h3 id="apexAlertModalTitle" class="text-base font-bold text-slate-900 tracking-tight"></h3>
            <p id="apexAlertModalMessage" class="text-xs text-slate-600 leading-relaxed break-words"></p>
        </div>

        <!-- Actions -->
        <div class="pt-2">
            <button type="button" id="apexAlertModalBtn" onclick="closeApexAlertModal()" 
                    class="w-full py-2.5 px-4 rounded-xl text-xs font-semibold shadow-sm transition-all flex items-center justify-center space-x-1">
                <span>Mengerti</span>
            </button>
        </div>
    </div>
</div>

<!-- Universal Confirmation Modal Component -->
<div id="apexConfirmModal" class="hidden fixed inset-0 z-[9999] items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity duration-200" style="margin: 0 !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important;">
    <div class="relative w-full max-w-sm bg-white rounded-3xl shadow-2xl border border-slate-200 p-6 space-y-4 text-center transform transition-all duration-200 scale-95 opacity-0 my-auto" id="apexConfirmModalCard" style="margin-left: auto !important; margin-right: auto !important;">
        <!-- Icon Container -->
        <div id="apexConfirmModalIconWrapper" class="w-14 h-14 rounded-2xl mx-auto flex items-center justify-center bg-rose-100 text-rose-600 shadow-sm">
            <i id="apexConfirmModalIcon" data-lucide="alert-triangle" class="w-7 h-7"></i>
        </div>

        <!-- Title & Message -->
        <div class="space-y-1.5">
            <h3 id="apexConfirmModalTitle" class="text-base font-bold text-slate-900 tracking-tight">Konfirmasi Tindakan</h3>
            <p id="apexConfirmModalMessage" class="text-xs text-slate-600 leading-relaxed break-words">Apakah Anda yakin ingin melanjutkan tindakan ini?</p>
        </div>

        <!-- Action Buttons -->
        <div class="pt-2 grid grid-cols-2 gap-2">
            <button type="button" onclick="closeApexConfirmModal()" 
                    class="py-2.5 px-4 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors">
                Batal
            </button>
            <button type="button" id="apexConfirmModalSubmitBtn" 
                    class="py-2.5 px-4 rounded-xl text-xs font-semibold bg-rose-600 hover:bg-rose-700 text-white shadow-sm transition-all flex items-center justify-center space-x-1">
                <span id="apexConfirmModalSubmitText">Ya, Lanjutkan</span>
            </button>
        </div>
    </div>
</div>

<script>
    let apexConfirmCallback = null;

    function ensureApexModalsInBody() {
        const alertModal = document.getElementById('apexAlertModal');
        const confirmModal = document.getElementById('apexConfirmModal');
        if (alertModal && alertModal.parentElement !== document.body) {
            document.body.appendChild(alertModal);
        }
        if (confirmModal && confirmModal.parentElement !== document.body) {
            document.body.appendChild(confirmModal);
        }
    }

    /**
     * Show Universal Alert Modal.
     * @param {'success'|'error'|'warning'|'info'} type
     * @param {string} title
     * @param {string} message
     */
    function showAppModal(type, title, message) {
        ensureApexModalsInBody();

        const modal = document.getElementById('apexAlertModal');
        const card = document.getElementById('apexAlertModalCard');
        const iconWrapper = document.getElementById('apexAlertModalIconWrapper');
        const icon = document.getElementById('apexAlertModalIcon');
        const titleEl = document.getElementById('apexAlertModalTitle');
        const msgEl = document.getElementById('apexAlertModalMessage');
        const btn = document.getElementById('apexAlertModalBtn');

        if (!modal) return;

        // Normalisasi tampilan modal general untuk 'Pesan Terkirim'
        const cleanMsg = (message || '').toString().trim();
        const cleanTitle = (title || '').toString().trim();
        if (type === 'success' && (cleanMsg.toLowerCase() === 'pesan terkirim' || cleanTitle.toLowerCase() === 'pesan terkirim')) {
            title = 'Pesan Terkirim';
            message = '';
        }

        titleEl.textContent = title;
        if (!message || message.toString().trim() === '') {
            msgEl.textContent = '';
            msgEl.classList.add('hidden');
        } else {
            msgEl.innerHTML = message;
            msgEl.classList.remove('hidden');
        }

        // Reset styling
        iconWrapper.className = 'w-14 h-14 rounded-2xl mx-auto flex items-center justify-center shadow-sm';
        btn.className = 'w-full py-2.5 px-4 rounded-xl text-xs font-semibold shadow-sm transition-all flex items-center justify-center space-x-1';

        if (type === 'success') {
            iconWrapper.classList.add('bg-emerald-100', 'text-emerald-600');
            icon.setAttribute('data-lucide', 'check-circle-2');
            btn.classList.add('bg-emerald-600', 'hover:bg-emerald-700', 'text-white', 'shadow-emerald-500/20');
        } else if (type === 'error') {
            iconWrapper.classList.add('bg-rose-100', 'text-rose-600');
            icon.setAttribute('data-lucide', 'alert-circle');
            btn.classList.add('bg-rose-600', 'hover:bg-rose-700', 'text-white', 'shadow-rose-500/20');
        } else if (type === 'warning') {
            iconWrapper.classList.add('bg-amber-100', 'text-amber-600');
            icon.setAttribute('data-lucide', 'alert-triangle');
            btn.classList.add('bg-amber-600', 'hover:bg-amber-700', 'text-white', 'shadow-amber-500/20');
        } else {
            iconWrapper.classList.add('bg-blue-100', 'text-blue-600');
            icon.setAttribute('data-lucide', 'info');
            btn.classList.add('bg-blue-600', 'hover:bg-blue-700', 'text-white', 'shadow-blue-500/20');
        }

        if (window.lucide) {
            lucide.createIcons();
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');

        requestAnimationFrame(() => {
            card.classList.remove('scale-95', 'opacity-0');
            card.classList.add('scale-100', 'opacity-100');
        });
    }

    function closeApexAlertModal() {
        const modal = document.getElementById('apexAlertModal');
        const card = document.getElementById('apexAlertModalCard');
        if (!modal) return;

        card.classList.remove('scale-100', 'opacity-100');
        card.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }, 180);
    }

    /**
     * Show Universal Confirmation Modal.
     * @param {string} title
     * @param {string} message
     * @param {Function} onConfirm
     * @param {string} confirmText
     * @param {boolean} isDanger
     */
    function showConfirmModal(title, message, onConfirm, confirmText = 'Ya, Lanjutkan', isDanger = true) {
        ensureApexModalsInBody();

        const modal = document.getElementById('apexConfirmModal');
        const card = document.getElementById('apexConfirmModalCard');
        const iconWrapper = document.getElementById('apexConfirmModalIconWrapper');
        const icon = document.getElementById('apexConfirmModalIcon');
        const titleEl = document.getElementById('apexConfirmModalTitle');
        const msgEl = document.getElementById('apexConfirmModalMessage');
        const btn = document.getElementById('apexConfirmModalSubmitBtn');
        const btnText = document.getElementById('apexConfirmModalSubmitText');

        if (!modal) return;

        titleEl.textContent = title;
        msgEl.innerHTML = message;
        btnText.textContent = confirmText;
        apexConfirmCallback = onConfirm;

        iconWrapper.className = 'w-14 h-14 rounded-2xl mx-auto flex items-center justify-center shadow-sm';
        btn.className = 'py-2.5 px-4 rounded-xl text-xs font-semibold shadow-sm transition-all flex items-center justify-center space-x-1';

        if (isDanger) {
            iconWrapper.classList.add('bg-rose-100', 'text-rose-600');
            icon.setAttribute('data-lucide', 'trash-2');
            btn.classList.add('bg-rose-600', 'hover:bg-rose-700', 'text-white', 'shadow-rose-500/20');
        } else {
            iconWrapper.classList.add('bg-blue-100', 'text-blue-600');
            icon.setAttribute('data-lucide', 'help-circle');
            btn.classList.add('bg-blue-600', 'hover:bg-blue-700', 'text-white', 'shadow-blue-500/20');
        }

        if (window.lucide) {
            lucide.createIcons();
        }

        btn.onclick = function() {
            closeApexConfirmModal();
            if (typeof apexConfirmCallback === 'function') {
                apexConfirmCallback();
            }
        };

        modal.classList.remove('hidden');
        modal.classList.add('flex');

        requestAnimationFrame(() => {
            card.classList.remove('scale-95', 'opacity-0');
            card.classList.add('scale-100', 'opacity-100');
        });
    }

    function closeApexConfirmModal() {
        const modal = document.getElementById('apexConfirmModal');
        const card = document.getElementById('apexConfirmModalCard');
        if (!modal) return;

        card.classList.remove('scale-100', 'opacity-100');
        card.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
            apexConfirmCallback = null;
        }, 180);
    }

    // Auto trigger on session flash messages
    document.addEventListener('DOMContentLoaded', function () {
        ensureApexModalsInBody();

        @if(session('success'))
            @php
                $sMsg = session('success');
                $isSentMsg = in_array(strtolower(trim($sMsg)), [
                    'pesan terkirim',
                    'email berhasil dikirimkan!',
                    'email berhasil dikirimkan',
                    'pesan berhasil dikirim',
                    'pesan berhasil dikirimkan'
                ]);
                $mTitle = $isSentMsg ? 'Pesan Terkirim' : 'Berhasil!';
                $mText = $isSentMsg ? '' : $sMsg;
            @endphp
            showAppModal('success', {!! json_encode($mTitle) !!}, {!! json_encode($mText) !!});
        @elseif(session('error'))
            showAppModal('error', 'Terjadi Kesalahan', {!! json_encode(session('error')) !!});
        @elseif(session('warning'))
            showAppModal('warning', 'Peringatan', {!! json_encode(session('warning')) !!});
        @elseif(isset($errors) && $errors->any())
            showAppModal('error', 'Validasi Gagal', {!! json_encode(implode('<br>', $errors->all())) !!});
        @endif

        // Close on escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeApexAlertModal();
                closeApexConfirmModal();
            }
        });
    });
</script>
