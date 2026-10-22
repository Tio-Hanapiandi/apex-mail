@extends('layouts.app')

@section('title', 'Migrasi Data Mailbox & Akun - ApexMail Admin')

@section('content')
<div class="flex-1 bg-[#f8fafc] overflow-y-auto p-3.5 sm:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto space-y-6">

        <!-- Top Header Admin Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-indigo-100 text-indigo-800 border border-indigo-200 uppercase">
                        Migration Suite
                    </span>
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Migrasi Data Mailbox & Akun Pengguna</h1>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Pindahkan seluruh akun pengguna, struktur folder, lampiran, dan pesan email dari server lama secara lossless tanpa mengubah tanggal asli.</p>
            </div>

            <!-- Sub Navigasi Menu Admin -->
            <div class="flex items-center space-x-1.5 overflow-x-auto no-scrollbar pb-1 max-w-full">
                <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Ringkasan Sistem
                </a>
                <a href="{{ route('admin.accounts') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Kelola Akun ({{ $totalAccounts }})
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
                    Email Masuk
                </a>
                <a href="{{ route('admin.queue') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                    Antrean & Log
                </a>
                <a href="{{ route('admin.migration') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-600 text-white shadow-sm shadow-blue-500/20">
                    Migrasi Data
                </a>
            </div>
        </div>

        <!-- Alert Banner Keunggulan Migrasi Lossless -->
        <div class="bg-gradient-to-r from-blue-900 via-indigo-900 to-slate-900 rounded-2xl p-5 text-white shadow-md">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center space-x-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-blue-500/30 text-blue-200 border border-blue-400/30">
                            100% Lossless & Preserved Metadata
                        </span>
                        <h2 class="text-base font-bold tracking-tight">Migrasi Mailbox Utuh Tanpa Mengubah Apapun</h2>
                    </div>
                    <p class="text-xs text-slate-300 leading-relaxed max-w-3xl">
                        ApexMail Migration Suite memproses data email lama dengan menjaga integritas data secara sempurna: 
                        <strong>tanggal pengiriman asli (RFC 822 Date)</strong>, nama pengirim, penerima (To/Cc/Bcc), status terbaca, bintang, 
                        seluruh <strong>struktur hierarki folder</strong> (Inbox, Sent, Trash, Spam, kustom subfolder), hingga <strong>berkas lampiran asli</strong> tetap terjaga utuh.
                    </p>
                </div>
                <div class="flex items-center space-x-2 shrink-0">
                    <a href="#panduan-zimbra" class="px-3.5 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-medium border border-white/20 transition-all flex items-center space-x-1.5">
                        <i data-lucide="book-open" class="w-4 h-4"></i>
                        <span>Panduan Ekspor</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Notifikasi Session -->
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center space-x-2.5 shadow-sm">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                <div class="font-medium">{!! session('success') !!}</div>
            </div>
        @endif

        @if(session('warning'))
            <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs flex items-center space-x-2.5 shadow-sm">
                <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 shrink-0"></i>
                <div class="font-medium">{!! session('warning') !!}</div>
            </div>
        @endif

        @if(session('error'))
            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center space-x-2.5 shadow-sm">
                <i data-lucide="alert-octagon" class="w-4 h-4 text-rose-600 shrink-0"></i>
                <div class="font-medium">{!! session('error') !!}</div>
            </div>
        @endif

        <!-- Grid 2 Kolom: Fitur Migrasi Utama -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- KOTAK 1: IMPOR ARSIP MAILBOX (.ZIP, .TGZ, .TAR.GZ, .EML) -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between">
                <div>
                    <div class="p-5 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                        <div class="flex items-center space-x-2.5">
                            <div class="w-9 h-9 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center font-bold">
                                <i data-lucide="archive" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">1. Impor Arsip Mailbox Pengguna</h3>
                                <p class="text-[11px] text-slate-500">Mendukung file backup/ekspor .tgz, .zip, .tar.gz, atau .eml</p>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                            Rekomendasi
                        </span>
                    </div>

                    <form action="{{ route('admin.migration.mailbox') }}" method="POST" enctype="multipart/form-data" class="p-5 space-y-4">
                        @csrf

                        <!-- Pilih Akun Tujuan -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Akun Tujuan di ApexMail <span class="text-rose-500">*</span>
                            </label>
                            <select name="account_id" required class="w-full text-xs rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500 bg-slate-50 p-2.5">
                                <option value="">-- Pilih Akun Email Pengguna --</option>
                                @foreach($accounts as $acc)
                                    <option value="{{ $acc->id }}">
                                        {{ $acc->email }} ({{ $acc->display_name }}) - Kuota: {{ round($acc->used_bytes / (1024 * 1024), 1) }} / {{ round($acc->quota_bytes / (1024 * 1024), 0) }} MB
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-[10px] text-slate-400 mt-1">Seluruh pesan yang diimpor akan masuk ke folder-folder akun ini.</p>
                        </div>

                        <!-- Opsi Unggah Berkas Arsip -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Unggah Berkas Arsip (.tgz / .zip / .eml)
                            </label>
                            <div class="mt-1 flex justify-center px-4 pt-4 pb-4 border-2 border-slate-200 border-dashed rounded-xl hover:border-blue-400 bg-slate-50 transition-colors">
                                <div class="space-y-1 text-center">
                                    <i data-lucide="upload-cloud" class="mx-auto h-7 w-7 text-slate-400"></i>
                                    <div class="flex text-xs text-slate-600 justify-center">
                                        <label for="archive_file" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none px-2 py-0.5 border border-slate-200 shadow-sm">
                                            <span>Pilih file arsip</span>
                                            <input id="archive_file" name="archive_file" type="file" accept=".zip,.tgz,.gz,.tar,.eml" class="sr-only">
                                        </label>
                                    </div>
                                    <p class="text-[10px] text-slate-400" id="file_chosen_label">Maksimal 512 MB per unggahan HTTP</p>
                                </div>
                            </div>
                        </div>

                        <!-- Atau Jalur Berkas di Server (CLI Path) -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Atau Path Berkas Lokal di Server (Untuk file besar > 1 GB)
                            </label>
                            <input type="text" name="server_path" placeholder="F:\Backup_Zimbra\user@domain.com.tgz atau /tmp/mailbox.zip"
                                   class="w-full text-xs rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500 bg-slate-50 p-2.5 font-mono">
                            <p class="text-[10px] text-slate-400 mt-1">Gunakan jalur ini jika file backup berukuran puluhan Gigabyte di harddisk server.</p>
                        </div>

                        <!-- Pilihan Deduplikasi & Folder Fallback -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Folder Default (Fallback)</label>
                                <select name="default_folder" class="w-full text-xs rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500 bg-slate-50 p-2">
                                    <option value="inbox">Kotak Masuk (Inbox)</option>
                                    <option value="sent">Terkirim (Sent)</option>
                                    <option value="archive">Arsip (Archive)</option>
                                    <option value="trash">Sampah (Trash)</option>
                                </select>
                            </div>
                            <div class="flex items-center space-x-2 pt-5">
                                <input type="checkbox" id="skip_duplicates" name="skip_duplicates" value="1" checked
                                       class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 h-4 w-4">
                                <label for="skip_duplicates" class="text-xs text-slate-700 font-medium">
                                    Lewati Pesan Duplikat
                                </label>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs shadow-sm shadow-blue-500/20 transition-all flex items-center justify-center space-x-2">
                                <i data-lucide="play" class="w-4 h-4"></i>
                                <span>Mulai Proses Impor Mailbox</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Footer Info CLI -->
                <div class="p-4 bg-slate-50 border-t border-slate-100 text-[11px] text-slate-600">
                    <span class="font-semibold text-slate-800">Tips CLI Artisan (Tanpa Timeout):</span>
                    <div class="mt-1 font-mono text-[10px] bg-slate-900 text-emerald-400 p-2 rounded-lg overflow-x-auto">
                        php artisan mail:migrate-archive user@domain.com /path/to/archive.tgz
                    </div>
                </div>
            </div>

            <!-- KOTAK 2: IMPOR AKUN MASSAL (CSV) -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between">
                <div>
                    <div class="p-5 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                        <div class="flex items-center space-x-2.5">
                            <div class="w-9 h-9 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold">
                                <i data-lucide="users" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">2. Impor Akun Pengguna Massal (CSV)</h3>
                                <p class="text-[11px] text-slate-500">Buat ratusan akun sekaligus lengkap dengan kuota & nama</p>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-indigo-100 text-indigo-800 border border-indigo-200">
                            Batch Provisioning
                        </span>
                    </div>

                    <form action="{{ route('admin.migration.accounts') }}" method="POST" enctype="multipart/form-data" class="p-5 space-y-4">
                        @csrf

                        <!-- Pilih Domain Default -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Domain Utama <span class="text-rose-500">*</span>
                            </label>
                            <select name="domain_id" required class="w-full text-xs rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500 bg-slate-50 p-2.5">
                                @foreach($domains as $dom)
                                    <option value="{{ $dom->id }}" {{ $dom->is_default ? 'selected' : '' }}>
                                        {{ $dom->name }} {{ $dom->is_default ? '(Default)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Unggah File CSV -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-semibold text-slate-700">
                                    Berkas CSV Akun
                                </label>
                                <a href="{{ route('admin.accounts.template') }}" class="text-[10px] text-emerald-600 hover:text-emerald-800 hover:underline flex items-center space-x-1">
                                    <i data-lucide="file-spreadsheet" class="w-3 h-3"></i>
                                    <span>Unduh Template CSV</span>
                                </a>
                            </div>
                            <input type="file" name="csv_file" accept=".csv,.txt"
                                   class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        </div>

                        <!-- Atau Tempel Langsung Teks CSV -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-xs font-semibold text-slate-700">
                                    Atau Tempel Langsung Data CSV
                                </label>
                                <button type="button" onclick="loadSampleCsv()" class="text-[10px] text-blue-600 hover:underline">
                                    Isi Contoh Format
                                </button>
                            </div>
                            <textarea id="csv_text" name="csv_text" rows="4" placeholder="email,display_name,password,quota_mb,is_admin&#10;budi@domain.com,Budi Santoso,Sandi123!,2048,0&#10;siti@domain.com,Siti Aminah,Sandi123!,1024,0"
                                      class="w-full text-xs rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500 bg-slate-50 p-2.5 font-mono"></textarea>
                        </div>

                        <!-- Opsi Sandi Default & Update Existing -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Sandi Default (Bila Kosong)</label>
                                <input type="text" name="default_password" value="Password123!"
                                       class="w-full text-xs rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500 bg-slate-50 p-2">
                            </div>
                            <div class="flex items-center space-x-2 pt-5">
                                <input type="checkbox" id="update_existing" name="update_existing" value="1"
                                       class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                                <label for="update_existing" class="text-xs text-slate-700 font-medium">
                                    Perbarui Akun Bila Ada
                                </label>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs shadow-sm shadow-indigo-500/20 transition-all flex items-center justify-center space-x-2">
                                <i data-lucide="user-plus" class="w-4 h-4"></i>
                                <span>Proses Impor Akun Pengguna</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Format Info -->
                <div class="p-4 bg-slate-50 border-t border-slate-100 text-[11px] text-slate-600">
                    <span class="font-semibold text-slate-800">Format Header CSV:</span>
                    <p class="font-mono text-[10px] text-slate-500 mt-0.5">email, display_name, password, quota_mb, is_admin</p>
                </div>
            </div>

        </div>

        <!-- KOTAK 3: UJI KONEKSI IMAP LANGSUNG (LIVE IMAP CONNECTIVITY) -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center space-x-2.5">
                    <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center font-bold">
                        <i data-lucide="radio" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">3. Uji Konektivitas IMAP Server Lama (Live Socket Sync)</h3>
                        <p class="text-[11px] text-slate-500">Periksa apakah server Zimbra lama Anda dapat diakses langsung melalui protokol IMAP SSL/TLS</p>
                    </div>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                    Direct Stream Socket
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Host Server IMAP Lama</label>
                    <input type="text" id="imap_host" placeholder="mail.domainlama.com atau 192.168.1.10"
                           class="w-full text-xs rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500 bg-slate-50 p-2.5">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Port</label>
                    <input type="number" id="imap_port" value="993"
                           class="w-full text-xs rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500 bg-slate-50 p-2.5">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Enkripsi</label>
                    <select id="imap_encryption" class="w-full text-xs rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500 bg-slate-50 p-2.5">
                        <option value="ssl" selected>SSL / TLS (Port 993)</option>
                        <option value="none">Tanpa Enkripsi (Port 143)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Username Akun</label>
                    <input type="text" id="imap_user" placeholder="user@domainlama.com"
                           class="w-full text-xs rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500 bg-slate-50 p-2.5">
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2">
                <div class="w-full sm:w-1/3">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Password Akun</label>
                    <input type="password" id="imap_pass" placeholder="••••••••"
                           class="w-full text-xs rounded-xl border-slate-200 focus:ring-blue-500 focus:border-blue-500 bg-slate-50 p-2.5">
                </div>
                <div class="w-full sm:w-auto flex items-end space-x-2">
                    <button type="button" id="btn_test_imap" onclick="executeImapTest()"
                            class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs shadow-sm transition-all flex items-center justify-center space-x-2">
                        <i data-lucide="zap" class="w-4 h-4 text-amber-400"></i>
                        <span>Uji Koneksi & Login IMAP</span>
                    </button>
                </div>
            </div>

            <!-- Hasil Uji IMAP Container -->
            <div id="imap_test_result" class="hidden p-3.5 rounded-xl text-xs"></div>
        </div>

        <!-- KOTAK 4: PANDUAN RESMI EKSPOR ZIMBRA (STEP-BY-STEP COMMANDS) -->
        <div id="panduan-zimbra" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-5">
            <div class="flex items-center space-x-3 pb-3 border-b border-slate-100">
                <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center font-bold">
                    <i data-lucide="terminal" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900">Panduan Langkah Ekspor Data Mailbox di Server Zimbra</h3>
                    <p class="text-xs text-slate-500">Jalankan perintah ini di server Linux Zimbra Anda untuk menghasilkan file arsip yang siap diimpor ke ApexMail.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Metode 1: Ekspor 1 Akun Lengkap -->
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-800 flex items-center space-x-1.5">
                            <span class="w-5 h-5 rounded-full bg-blue-600 text-white text-[10px] inline-flex items-center justify-center font-bold">1</span>
                            <span>Ekspor Akun Lengkap ke Format .TGZ</span>
                        </span>
                        <span class="text-[10px] font-mono text-slate-400">su - zimbra</span>
                    </div>
                    <p class="text-[11px] text-slate-600">
                        Ekspor seluruh folder (Kotak Masuk, Terkirim, Draf, Sampah, Folder Kustom, Lampiran) satu akun pengguna menjadi satu file .tgz tunggal:
                    </p>
                    <div class="font-mono text-[11px] bg-slate-900 text-emerald-400 p-2.5 rounded-lg select-all overflow-x-auto">
                        zmmailbox -z -m user@domain.com getRestURL "//?fmt=tgz" &gt; /tmp/user@domain.com.tgz
                    </div>
                </div>

                <!-- Metode 2: Ekspor Format .ZIP -->
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-800 flex items-center space-x-1.5">
                            <span class="w-5 h-5 rounded-full bg-blue-600 text-white text-[10px] inline-flex items-center justify-center font-bold">2</span>
                            <span>Ekspor Akun Lengkap ke Format .ZIP</span>
                        </span>
                        <span class="text-[10px] font-mono text-slate-400">su - zimbra</span>
                    </div>
                    <p class="text-[11px] text-slate-600">
                        Jika Anda lebih menyukai format zip, ganti parameter format menjadi <code>fmt=zip</code>:
                    </p>
                    <div class="font-mono text-[11px] bg-slate-900 text-emerald-400 p-2.5 rounded-lg select-all overflow-x-auto">
                        zmmailbox -z -m user@domain.com getRestURL "//?fmt=zip" &gt; /tmp/user@domain.com.zip
                    </div>
                </div>

                <!-- Metode 3: Ekspor Daftar Akun ke CSV -->
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-800 flex items-center space-x-1.5">
                            <span class="w-5 h-5 rounded-full bg-indigo-600 text-white text-[10px] inline-flex items-center justify-center font-bold">3</span>
                            <span>Ekspor Seluruh Daftar Akun Email Zimbra</span>
                        </span>
                        <span class="text-[10px] font-mono text-slate-400">CLI Zimbra</span>
                    </div>
                    <p class="text-[11px] text-slate-600">
                        Dapatkan seluruh alamat email aktif di server Zimbra untuk kemudian diimpor ke form CSV di atas:
                    </p>
                    <div class="font-mono text-[11px] bg-slate-900 text-emerald-400 p-2.5 rounded-lg select-all overflow-x-auto">
                        zmprov -l gaa domain.com &gt; /tmp/daftar_akun.txt
                    </div>
                </div>

                <!-- Metode 4: Script Batch Ekspor Seluruh Mailbox -->
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-800 flex items-center space-x-1.5">
                            <span class="w-5 h-5 rounded-full bg-indigo-600 text-white text-[10px] inline-flex items-center justify-center font-bold">4</span>
                            <span>Bash Script Ekspor Semua Mailbox Otomatis</span>
                        </span>
                        <span class="text-[10px] font-mono text-slate-400">Loop Script</span>
                    </div>
                    <p class="text-[11px] text-slate-600">
                        Script one-liner untuk mengekspor seluruh akun pada suatu domain ke direktori <code>/tmp/backup/</code>:
                    </p>
                    <div class="font-mono text-[10px] bg-slate-900 text-emerald-400 p-2.5 rounded-lg select-all overflow-x-auto">
                        mkdir -p /tmp/backup && for acc in $(zmprov -l gaa domain.com); do zmmailbox -z -m "$acc" getRestURL "//?fmt=tgz" > "/tmp/backup/$acc.tgz"; echo "Backup $acc selesai"; done
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    // Update label when file is selected
    const archiveFileInput = document.getElementById('archive_file');
    if (archiveFileInput) {
        archiveFileInput.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            const label = document.getElementById('file_chosen_label');
            if (fileName && label) {
                label.innerHTML = '<span class="text-blue-700 font-semibold">' + fileName + '</span> (' + (e.target.files[0].size / (1024 * 1024)).toFixed(2) + ' MB)';
            }
        });
    }

    function loadSampleCsv() {
        const textarea = document.getElementById('csv_text');
        if (textarea) {
            textarea.value = "email,display_name,password,quota_mb,is_admin\n" +
                             "direksi@azkario.biz.id,Direktur Utama,Password123!,5120,1\n" +
                             "kucing@azkario.biz.id,Kucing Anggora,Password123!,2048,0\n" +
                             "staff1@azkario.biz.id,Staff Operasional,Password123!,1024,0";
        }
    }

    async function executeImapTest() {
        const host = document.getElementById('imap_host').value.trim();
        const port = document.getElementById('imap_port').value.trim();
        const encryption = document.getElementById('imap_encryption').value;
        const user = document.getElementById('imap_user').value.trim();
        const pass = document.getElementById('imap_pass').value;
        const resultContainer = document.getElementById('imap_test_result');
        const btn = document.getElementById('btn_test_imap');

        if (!host || !user || !pass) {
            alert('Mohon lengkapi host, username, dan password server IMAP terlebih dahulu.');
            return;
        }

        resultContainer.className = 'p-3.5 rounded-xl text-xs bg-slate-100 text-slate-700 flex items-center space-x-2';
        resultContainer.innerHTML = '<i data-lucide="loader" class="w-4 h-4 animate-spin text-blue-600"></i><span>Menghubungi socket IMAP ' + host + ':' + port + '...</span>';
        resultContainer.classList.remove('hidden');
        if (window.lucide) lucide.createIcons();

        btn.disabled = true;

        try {
            const response = await fetch("{{ route('admin.migration.imap-test') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    host: host,
                    port: parseInt(port),
                    encryption: encryption,
                    username: user,
                    password: pass
                })
            });

            const data = await response.json();

            if (data.success) {
                resultContainer.className = 'p-3.5 rounded-xl text-xs bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center space-x-2';
                resultContainer.innerHTML = '<i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i><div><span class="font-bold">Berhasil:</span> ' + data.message + '</div>';
            } else {
                resultContainer.className = 'p-3.5 rounded-xl text-xs bg-rose-50 border border-rose-200 text-rose-800 flex items-center space-x-2';
                resultContainer.innerHTML = '<i data-lucide="alert-octagon" class="w-4 h-4 text-rose-600 shrink-0"></i><div><span class="font-bold">Gagal Terhubung:</span> ' + data.message + '</div>';
            }
        } catch (err) {
            resultContainer.className = 'p-3.5 rounded-xl text-xs bg-rose-50 border border-rose-200 text-rose-800 flex items-center space-x-2';
            resultContainer.innerHTML = '<i data-lucide="alert-octagon" class="w-4 h-4 text-rose-600 shrink-0"></i><div><span class="font-bold">Kesalahan Sistem:</span> ' + err.message + '</div>';
        } finally {
            btn.disabled = false;
            if (window.lucide) lucide.createIcons();
        }
    }
</script>
@endsection
