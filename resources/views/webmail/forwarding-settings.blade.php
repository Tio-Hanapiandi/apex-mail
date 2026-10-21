@extends('layouts.app')

@section('title', 'Penerusan Email (Email Forwarding) - ApexMail')

@section('content')
<div class="flex-1 bg-[#f8fafc] overflow-y-auto p-3.5 sm:p-6 lg:p-8">
    <div class="max-w-4xl mx-auto space-y-6">

        <!-- Header Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200">
            <div>
                <div class="flex items-center space-x-2 text-xs text-slate-500 mb-1">
                    <a href="{{ route('webmail.index') }}" class="hover:text-blue-600 transition-colors">Kotak Surat</a>
                    <span>/</span>
                    <span class="text-slate-700 font-medium">Pengaturan Akun</span>
                </div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight flex items-center space-x-2">
                    <i data-lucide="forward" class="w-6 h-6 text-indigo-600"></i>
                    <span>Penerusan Email (Email Forwarding)</span>
                </h1>
                <p class="text-xs text-slate-500 mt-0.5">Teruskan seluruh email masuk yang diterima akun <strong class="text-slate-700">{{ $user->email }}</strong> secara otomatis ke alamat email lain.</p>
            </div>

            <!-- Status Badge -->
            <div>
                @if($user->forward_active)
                    <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 shadow-xs">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse mr-2"></span>
                        Penerusan Otomatis Aktif
                    </span>
                @else
                    <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                        <span class="w-2 h-2 rounded-full bg-slate-400 mr-2"></span>
                        Penerusan Nonaktif
                    </span>
                @endif
            </div>
        </div>

        <!-- Form Pengaturan Forwarding -->
        <form action="{{ route('webmail.forwarding.update') }}" method="POST" class="space-y-6">
            @csrf

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-6">
                <!-- Toggle Aktifkan Forwarding -->
                <div class="flex items-start justify-between pb-5 border-b border-slate-100">
                    <div class="pr-4">
                        <label for="forward_active" class="text-sm font-bold text-slate-900 cursor-pointer">
                            Aktifkan Penerusan Email Otomatis
                        </label>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                            Bila diaktifkan, setiap email baru yang masuk ke kotak surat Anda akan langsung diteruskan ke alamat tujuan yang Anda tentukan di bawah.
                        </p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                        <input type="checkbox" id="forward_active" name="forward_active" value="1" 
                               {{ old('forward_active', $user->forward_active) ? 'checked' : '' }} 
                               class="sr-only peer" onchange="toggleForwardFields(this.checked)">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                    </label>
                </div>

                <!-- Section Input Alamat Tujuan (Hidden saat nonaktif jika diinginkan, atau disabled visual) -->
                <div id="forwardFieldsSection" class="space-y-5 {{ old('forward_active', $user->forward_active) ? '' : 'opacity-60' }}">
                    <div>
                        <label for="forward_destination" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Alamat Email Tujuan Penerusan <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i data-lucide="mail" class="w-4 h-4"></i>
                            </div>
                            <input type="text" id="forward_destination" name="forward_destination" 
                                   value="{{ old('forward_destination', $user->forward_destination) }}"
                                   placeholder="misal: nama.pribadi@gmail.com, rekan@perusahaan.com"
                                   class="w-full pl-10 pr-4 py-2.5 text-xs sm:text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:bg-white font-medium text-slate-800 transition-all">
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1.5">
                            Anda dapat memasukkan lebih dari satu alamat email penerima sekaligus (pisahkan dengan koma <code>,</code> atau titik koma <code>;</code>).
                        </p>
                    </div>

                    <!-- Pilihan Simpan Salinan Lokal -->
                    <div class="p-4 bg-slate-50/70 border border-slate-200/80 rounded-xl space-y-2">
                        <label class="flex items-start space-x-3 cursor-pointer">
                            <input type="checkbox" name="forward_keep_copy" value="1" 
                                   {{ old('forward_keep_copy', $user->forward_keep_copy ?? true) ? 'checked' : '' }}
                                   class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                            <div>
                                <span class="text-xs font-bold text-slate-800">Simpan salinan email di Kotak Masuk (Inbox) lokal</span>
                                <p class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">
                                    <strong>Disarankan:</strong> Email yang masuk akan tetap tersimpan di folder Kotak Masuk webmail Anda selain diteruskan ke alamat tujuan. Jika tidak dicentang, email lokal akan dihapus setelah diteruskan untuk menghemat kuota kotak surat.
                                </p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Info Proteksi SPF & Loop -->
                <div class="p-4 bg-blue-50/70 border border-blue-200 rounded-xl flex items-start space-x-3 text-xs text-blue-900 leading-relaxed">
                    <i data-lucide="info" class="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5"></i>
                    <div class="space-y-1">
                        <div class="font-bold text-blue-950">Standar Pengiriman & Perlindungan Loop:</div>
                        <ul class="list-disc list-inside space-y-0.5 text-[11px] text-blue-800">
                            <li>Email diteruskan dengan tanda tangan kriptografi DKIM domain resmi Anda, memastikan pengiriman lolos filter spam Gmail, Yahoo, dan Outlook.</li>
                            <li>Header <em>Reply-To</em> secara otomatis dialamatkan ke pengirim asli, sehingga jika Anda membalas dari Gmail, balasan akan langsung tertuju ke pengirim awal.</li>
                            <li>Sistem memiliki proteksi anti-looping untuk mencegah email berputar berulang kali jika alamat tujuan sama dengan alamat pengirim.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('webmail.index') }}" class="inline-flex items-center space-x-2 px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900 bg-white hover:bg-slate-50 border border-slate-200 rounded-xl transition-colors">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                    <span>Kembali ke Kotak Masuk</span>
                </a>

                <button type="submit" class="inline-flex items-center space-x-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-sm shadow-indigo-500/25 transition-all">
                    <i data-lucide="save" class="w-3.5 h-3.5"></i>
                    <span>Simpan Pengaturan Penerusan</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleForwardFields(isChecked) {
        const section = document.getElementById('forwardFieldsSection');
        if (section) {
            if (isChecked) {
                section.classList.remove('opacity-60');
            } else {
                section.classList.add('opacity-60');
            }
        }
    }
</script>
@endsection
