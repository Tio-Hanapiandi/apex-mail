<!-- Universal Session Timeout Warning & Countdown Modal Component (ApexMail Security Suite) -->
@auth
<div id="sessionTimeoutModal" class="hidden fixed inset-0 z-[99999] items-center justify-center p-4 bg-slate-900/70 backdrop-blur-xs transition-opacity duration-200" style="margin: 0 !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important;">
    <div class="relative w-full max-w-sm sm:max-w-md bg-white rounded-3xl shadow-2xl border border-slate-200 p-6 sm:p-7 space-y-5 text-center transform transition-all duration-200 scale-95 opacity-0 my-auto" id="sessionTimeoutModalCard" style="margin-left: auto !important; margin-right: auto !important;">
        <!-- Pulsing Warning Icon -->
        <div id="sessionTimeoutIconWrapper" class="w-16 h-16 rounded-2xl mx-auto flex items-center justify-center bg-amber-100 text-amber-600 shadow-sm shadow-amber-500/20 animate-pulse">
            <i data-lucide="clock" class="w-8 h-8"></i>
        </div>

        <!-- Title & Subtitle -->
        <div class="space-y-2">
            <h3 id="sessionTimeoutTitle" class="text-lg font-bold text-slate-900 tracking-tight">Sesi Anda Akan Segera Berakhir</h3>
            <p id="sessionTimeoutMessage" class="text-xs text-slate-600 leading-relaxed">
                Tidak ada aktivitas terdeteksi selama beberapa waktu. Demi keamanan data akun email Anda, sistem akan menutup sesi otomatis dalam:
            </p>
        </div>

        <!-- Big Countdown Badge -->
        <div class="py-3 px-5 bg-amber-50/90 border border-amber-200 rounded-2xl flex items-center justify-center space-x-2 shadow-xs">
            <i data-lucide="timer" class="w-5 h-5 text-amber-600"></i>
            <span id="sessionCountdownTimer" class="font-mono text-3xl font-extrabold text-amber-700 tracking-wider">60</span>
            <span class="text-xs font-bold text-amber-600 uppercase tracking-wider">detik</span>
        </div>

        <!-- Email Writing / Long Draft Reminder -->
        <div id="sessionTimeoutDraftTip" class="p-3 bg-blue-50/80 border border-blue-100 rounded-xl text-[11px] text-blue-900 flex items-start space-x-2 text-left">
            <i data-lucide="info" class="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5"></i>
            <span>Jika Anda sedang mengetik atau membaca email panjang, klik tombol <strong>Perpanjang Sesi</strong> di bawah agar draf Anda tidak hilang.</span>
        </div>

        <!-- Action Buttons -->
        <div class="pt-1 grid grid-cols-2 gap-3">
            <button type="button" onclick="submitSessionTimeoutLogout()" 
                    class="py-2.5 px-4 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors flex items-center justify-center space-x-1.5 cursor-pointer">
                <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                <span>Keluar Sekarang</span>
            </button>
            <button type="button" id="sessionExtendBtn" onclick="extendCurrentSession()" 
                    class="py-2.5 px-4 rounded-xl text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/25 transition-all flex items-center justify-center space-x-1.5 cursor-pointer">
                <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                <span id="sessionExtendBtnText">Perpanjang Sesi</span>
            </button>
        </div>
    </div>
</div>

<!-- Hidden Logout Form -->
<form id="sessionTimeoutLogoutForm" action="{{ route('logout') }}" method="POST" class="hidden">
    @csrf
</form>

<script>
(function() {
    const IDLE_TIMEOUT_SEC = {{ (int) config('session.idle_timeout', 900) }};
    const WARNING_THRESHOLD_SEC = {{ (int) config('session.warning_threshold', 60) }};
    const MAX_ABSOLUTE_SEC = {{ (int) config('session.max_absolute_lifetime', 28800) }};
    const LOGIN_TIMESTAMP_SEC = {{ (int) session('session_login_time', now()->timestamp) }};
    const KEEP_ALIVE_URL = "{{ route('session.keep-alive') }}";
    const LOGIN_URL = "{{ route('login') }}";
    const CSRF_TOKEN = "{{ csrf_token() }}";

    let localLastActivity = Date.now();
    let isModalVisible = false;
    let isExtending = false;
    let hasUserInteractedSinceLastPing = false;
    let timerInterval = null;
    let periodicKeepAliveInterval = null;

    function ensureModalInBody() {
        const modal = document.getElementById('sessionTimeoutModal');
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    }

    function recordActivity() {
        localLastActivity = Date.now();
        if (!isModalVisible) {
            hasUserInteractedSinceLastPing = true;
        }
    }

    // Attach listeners for user activity across the document
    ['mousedown', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
        window.addEventListener(evt, recordActivity, { passive: true });
    });

    function showSessionModal(remainingSec, isAbsolute = false) {
        ensureModalInBody();
        if (isModalVisible) return;
        isModalVisible = true;

        const modal = document.getElementById('sessionTimeoutModal');
        const card = document.getElementById('sessionTimeoutModalCard');
        const titleEl = document.getElementById('sessionTimeoutTitle');
        const msgEl = document.getElementById('sessionTimeoutMessage');
        const tipEl = document.getElementById('sessionTimeoutDraftTip');
        const extendBtn = document.getElementById('sessionExtendBtn');

        if (!modal || !card) return;

        if (isAbsolute) {
            if (titleEl) titleEl.textContent = 'Batas Maksimal Sesi (8 Jam) Habis';
            if (msgEl) msgEl.textContent = 'Batas maksimal durasi sesi kerja 8 jam telah tercapai. Demi kepatuhan keamanan data, Anda wajib melakukan login ulang dalam:';
            if (tipEl) tipEl.classList.add('hidden');
            if (extendBtn) extendBtn.classList.add('hidden');
        } else {
            if (titleEl) titleEl.textContent = 'Sesi Anda Akan Segera Berakhir';
            if (msgEl) msgEl.textContent = 'Tidak ada aktivitas terdeteksi selama beberapa waktu. Demi keamanan data akun email Anda, sistem akan menutup sesi otomatis dalam:';
            if (tipEl) tipEl.classList.remove('hidden');
            if (extendBtn) extendBtn.classList.remove('hidden');
        }

        updateCountdownDisplay(remainingSec);

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => {
            card.classList.remove('scale-95', 'opacity-0');
            card.classList.add('scale-100', 'opacity-100');
        }, 10);

        if (window.lucide) {
            lucide.createIcons();
        }
    }

    function hideSessionModal() {
        if (!isModalVisible) return;
        const modal = document.getElementById('sessionTimeoutModal');
        const card = document.getElementById('sessionTimeoutModalCard');
        if (!modal || !card) return;

        card.classList.remove('scale-100', 'opacity-100');
        card.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
            isModalVisible = false;
        }, 180);
    }

    function updateCountdownDisplay(sec) {
        const timerEl = document.getElementById('sessionCountdownTimer');
        if (timerEl) {
            timerEl.textContent = Math.max(0, sec);
        }
    }

    function checkSessionTimer() {
        const nowSec = Math.floor(Date.now() / 1000);
        const idleElapsedSec = Math.floor((Date.now() - localLastActivity) / 1000);
        const idleRemainingSec = Math.max(0, IDLE_TIMEOUT_SEC - idleElapsedSec);

        const absoluteElapsedSec = nowSec - LOGIN_TIMESTAMP_SEC;
        const absoluteRemainingSec = Math.max(0, MAX_ABSOLUTE_SEC - absoluteElapsedSec);

        const isAbsoluteLimiting = absoluteRemainingSec <= idleRemainingSec;
        const effectiveRemainingSec = Math.min(idleRemainingSec, absoluteRemainingSec);

        if (effectiveRemainingSec <= 0) {
            clearInterval(timerInterval);
            clearInterval(periodicKeepAliveInterval);
            window.location.href = LOGIN_URL;
            return;
        }

        if (effectiveRemainingSec <= WARNING_THRESHOLD_SEC) {
            showSessionModal(effectiveRemainingSec, isAbsoluteLimiting);
            updateCountdownDisplay(effectiveRemainingSec);
        } else if (isModalVisible) {
            hideSessionModal();
        }
    }

    window.extendCurrentSession = function() {
        if (isExtending) return;
        isExtending = true;

        const btnText = document.getElementById('sessionExtendBtnText');
        if (btnText) btnText.textContent = 'Memperpanjang...';

        fetch(KEEP_ALIVE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            isExtending = false;
            if (btnText) btnText.textContent = 'Perpanjang Sesi';

            if (data.session_expired) {
                window.location.href = data.redirect || LOGIN_URL;
                return;
            }

            localLastActivity = Date.now();
            hasUserInteractedSinceLastPing = false;
            hideSessionModal();
        })
        .catch(() => {
            isExtending = false;
            if (btnText) btnText.textContent = 'Perpanjang Sesi';
        });
    };

    window.submitSessionTimeoutLogout = function() {
        const form = document.getElementById('sessionTimeoutLogoutForm');
        if (form) {
            form.submit();
        } else {
            window.location.href = "{{ route('logout') }}";
        }
    };

    // Periodic background keep-alive ping if user has been active (every 3 minutes = 180s)
    periodicKeepAliveInterval = setInterval(() => {
        if (hasUserInteractedSinceLastPing && !isModalVisible) {
            hasUserInteractedSinceLastPing = false;
            fetch(KEEP_ALIVE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).catch(() => {});
        }
    }, 180000);

    // Global fetch 401 interceptor for automatic redirect on expired session
    const originalFetch = window.fetch;
    window.fetch = async function(...args) {
        const response = await originalFetch.apply(this, args);
        if (response.status === 401) {
            try {
                const clone = response.clone();
                const data = await clone.json();
                if (data && data.session_expired) {
                    window.location.href = data.redirect || LOGIN_URL;
                }
            } catch(e) {}
        }
        return response;
    };

    // Start timer check every second
    timerInterval = setInterval(checkSessionTimer, 1000);
})();
</script>
@endauth
