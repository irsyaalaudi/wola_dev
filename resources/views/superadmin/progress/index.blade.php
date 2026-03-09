@extends('layouts.app')

@section('page-title', 'Progress')

@section('content')

<!-- CARD: Tabel Kinerja Pegawai -->
<div class="bg-white rounded-2xl p-6 mb-12 border border-gray-200">
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-2xl font-semibold text-blue-600">Tabel Kinerja Pegawai</h2>

    <div class="flex items-center gap-3 w-full sm:w-auto">
      <form method="GET" action="{{ route('superadmin.progress.index') }}" class="flex gap-3 w-full sm:w-auto">
        <input type="text" name="search_tugas" value="{{ request('search_tugas') }}"
          class="px-4 py-2 w-full sm:w-64 border border-gray-300 rounded-lg 
             focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400
             bg-white/50 backdrop-blur-sm placeholder-gray-500"
          placeholder="Cari tugas / pegawai...">
        <button type="submit"
          class="px-4 py-2 rounded-lg border border-gray-400 text-gray-600 font-medium 
             bg-white/40 backdrop-blur-sm hover:bg-gray-100 hover:text-gray-700
             transition duration-200 ease-in-out transform hover:scale-105">
          <i class="fas fa-search mr-1"></i> Cari
        </button>
      </form>

      <!-- Tombol Export -->
      <a href="{{ route('superadmin.progress.export.kinerja') }}"
        class="inline-flex items-center px-4 py-2 border border-green-500 text-green-600 font-medium 
          rounded-lg backdrop-blur-sm bg-white/30 hover:bg-green-50 hover:text-green-700 
          transition duration-200 ease-in-out transform hover:scale-105 shadow-sm">
        <i class="fas fa-file-excel text-lg mr-2"></i>
        Export Tabel
      </a>
    </div>
  </div>
  <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
    <table class="w-full table-auto text-sm text-gray-700">
      <thead class="bg-gradient-to-r from-blue-100 to-blue-200 text-center text-sm text-gray-700">
        <tr>
          <th class="p-3 border">No.</th>
          <th class="p-3 border text-left">Nama Pegawai</th>
          <th class="p-3 border text-left">Nama Pekerjaan</th>
          <th class="p-3 border text-left">Nama Tim</th>
          <th class="p-3 border text-left">Asal</th>
          <th class="p-3 border">Target</th>
          <th class="p-3 border">Realisasi</th>
          <th class="p-3 border">Satuan</th>
          <th class="p-3 border text-red-500">Deadline</th>
          <th class="p-3 border">Tgl Realisasi</th>
          <th class="p-3 border">Bobot</th>
          <th class="p-3 border">Nilai Akhir</th>
          <th class="p-3 border text-left">Catatan</th>
          <th class="p-3 border">Bukti</th>
        </tr>
      </thead>
      <tbody>
        @forelse($tugas as $t)
        <tr class="text-center odd:bg-white even:bg-gray-50 hover:bg-blue-50 border-b border-gray-200 transition-colors">
          <td class="px-3 py-2">{{ $loop->iteration + ($tugas->currentPage()-1)*$tugas->perPage() }}</td>
          <td class="text-left px-3 py-2 font-medium">{{ optional($t->pegawai)->nama ?? '-' }}</td>
          {{-- Nama pekerjaan diambil dari jenisPekerjaan --}}
          <td class="text-left px-3 py-2">{{ optional($t->jenisPekerjaan)->nama_pekerjaan ?? '-' }}</td>
          <td class="text-left px-3 py-2"> {{ optional(optional($t->jenisPekerjaan)->team)->nama_tim ?? '-' }}</td>
          <td class="text-left px-3 py-2">{{ $t->asal ?? '-' }}</td>
          <td class="px-3 py-2">{{ $t->target ?? '-' }}</td>
          <td class="px-3 py-2">{{ optional($t->realisasi)->realisasi ?? '-' }}</td>
          <td class="px-3 py-2">{{ $t->jenisPekerjaan->satuan ?? '-' }}</td>
          <td class="px-3 py-2 text-red-600">
            {{ $t->deadline ? \Carbon\Carbon::parse($t->deadline)->format('d M Y') : '-' }}
          </td>
          <td class="px-3 py-2">
            {{ optional(optional($t->realisasi)->tanggal_realisasi) 
               ? \Carbon\Carbon::parse($t->realisasi->tanggal_realisasi)->format('d M Y') : '-' }}
          </td>
          <td>{{ $t->jenisPekerjaan->bobot ?? '-' }}</td>
          <td>
            {{-- ambil dari tabel progress atau langsung hitung --}}
            {{ \App\Models\Progress::where('pegawai_id',$t->pegawai_id)->value('nilai_akhir') ?? 0 }}
          </td>
          <td class="text-left px-3 py-2 text-gray-500 italic">{{ optional($t->realisasi)->catatan ?? '-' }}</td>
          <td class="px-3 py-2">
            @if(optional($t->realisasi)->file_bukti)
            <a href="{{ asset('storage/' . $t->realisasi->file_bukti) }}" target="_blank" class="text-blue-600 hover:underline">Lihat</a>
            @else
            <span class="text-gray-400">-</span>
            @endif
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="14" class="text-center py-6 text-gray-500">Tidak ada data kinerja pegawai.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <!-- Paginasi -->
  @if ($tugas->hasPages())
  <div class="flex items-center justify-between border-t border-white/10 px-4 py-3 sm:px-6">
    <!-- Mobile Previous/Next -->
    <div class="flex flex-1 justify-between sm:hidden">
      @if ($tugas->onFirstPage())
      <span class="relative inline-flex items-center rounded-md border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-gray-400 cursor-not-allowed">Previous</span>
      @else
      <a href="{{ $tugas->appends(['progress_page' => $progress->currentPage()])->previousPageUrl() }}" class="relative inline-flex items-center rounded-md border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-gray-200 hover:bg-white/10">Previous</a>
      @endif

      @if ($tugas->hasMorePages())
      <a href="{{ $tugas->appends(['progress_page' => $progress->currentPage()])->nextPageUrl() }}" class="relative ml-3 inline-flex items-center rounded-md border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-gray-200 hover:bg-white/10">Next</a>
      @else
      <span class="relative ml-3 inline-flex items-center rounded-md border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-gray-400 cursor-not-allowed">Next</span>
      @endif
    </div>

    <!-- Desktop -->
    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
      <div>
        <p class="text-sm text-black">
          Menampilkan
          <span class="font-medium">{{ $tugas->firstItem() }}</span>
          sampai
          <span class="font-medium">{{ $tugas->lastItem() }}</span>
          data dari
          <span class="font-medium">{{ $tugas->total() }}</span>
          data keseluruhan
        </p>
      </div>
      <div>
        <nav aria-label="Pagination" class="isolate inline-flex -space-x-px rounded-md">
          {{-- Tombol Previous --}}
          @if ($tugas->onFirstPage())
          <span class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 cursor-not-allowed">
            <span class="sr-only">Previous</span>
            <svg viewBox="0 0 20 20" fill="currentColor" class="size-5">
              <path d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" />
            </svg>
          </span>
          @else
          <a href="{{ $tugas->appends(['progress_page' => $progress->currentPage()])->previousPageUrl() }}" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 hover:bg-white/5">
            <span class="sr-only">Previous</span>
            <svg viewBox="0 0 20 20" fill="currentColor" class="size-5">
              <path d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" />
            </svg>
          </a>
          @endif

          {{-- Nomor Halaman --}}
          @foreach ($tugas->getUrlRange(1, $tugas->lastPage()) as $page => $url)
          @if ($page == $tugas->currentPage())
          <span aria-current="page" class="relative z-10 inline-flex items-center bg-blue-700 px-4 py-2 text-sm font-semibold text-white">{{ $page }}</span>
          @else
          <a href="{{ $tugas->appends(['progress_page' => $progress->currentPage()])->url($page) }}" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-400 hover:bg-white/5">{{ $page }}</a>
          @endif
          @endforeach

          {{-- Tombol Next --}}
          @if ($tugas->hasMorePages())
          <a href="{{ $tugas->appends(['progress_page' => $progress->currentPage()])->nextPageUrl() }}" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 hover:bg-white/5">
            <span class="sr-only">Next</span>
            <svg viewBox="0 0 20 20" fill="currentColor" class="size-5">
              <path d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" />
            </svg>
          </a>
          @else
          <span class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 cursor-not-allowed">
            <span class="sr-only">Next</span>
            <svg viewBox="0 0 20 20" fill="CurrentColor" class="size-5">
              <path d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" />
            </svg>
          </span>
          @endif
        </nav>
      </div>
    </div>
  </div>
  @endif
</div>

<!-- CARD: Tabel Nilai Akhir Pegawai -->
<div class="bg-white rounded-2xl p-6 mb-12 border border-gray-200">
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-2xl font-semibold text-blue-600">Tabel Nilai Akhir Pegawai</h2>

    <div class="flex items-center gap-3 w-full sm:w-auto">
      <form method="GET" action="{{ route('superadmin.progress.index') }}" class="flex gap-3 w-full sm:w-auto">
        <input type="text" name="search_progress" value="{{ request('search_progress') }}"
          class="px-4 py-2 w-full sm:w-64 border border-gray-300 rounded-lg 
             focus:outline-none focus:ring-2 focus:ring-green-400 focus:border-green-400
             bg-white/50 backdrop-blur-sm placeholder-gray-500"
          placeholder="Cari pegawai / NIP...">
        <button type="submit"
          class="px-4 py-2 rounded-lg border border-gray-400 text-gray-600 font-medium 
             bg-white/40 backdrop-blur-sm hover:bg-gray-100 hover:text-gray-700
             transition duration-200 ease-in-out transform hover:scale-105">
          <i class="fas fa-search mr-1"></i> Cari
        </button>
      </form>

      <!-- Tombol Export -->
      <a href="{{ route('superadmin.progress.export.nilaiAkhir') }}"
        class="inline-flex items-center px-4 py-2 border border-green-500 text-green-600 font-medium 
          rounded-lg backdrop-blur-sm bg-white/30 hover:bg-green-50 hover:text-green-700 
          transition duration-200 ease-in-out transform hover:scale-105 shadow-sm">
        <i class="fas fa-file-excel text-lg mr-2"></i>
        Export Tabel
      </a>
    </div>
  </div>

  <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
    <table class="w-full table-auto text-sm text-gray-700">
      <thead class="bg-gradient-to-r from-blue-100 to-blue-200 text-center text-sm text-gray-700">
        <tr>
          <th class="p-3 border">No.</th>
          <th class="p-3 border text-left">Nama Pegawai</th>
          <th class="p-3 border">NIP</th>
          <th class="p-3 border">Nilai Akhir</th>
        </tr>
      </thead>
      <tbody>
        @forelse($progress as $p)
        <tr class="text-center odd:bg-white even:bg-gray-50 hover:bg-blue-50 border-b border-gray-200 transition-colors">
          <td class="px-4 py-2">{{ $loop->iteration + ($progress->currentPage()-1)*$progress->perPage() }}</td>
          <td class="text-left px-4 py-2 font-medium">{{ optional($p->pegawai)->nama ?? '-' }}</td>
          <td class="px-4 py-2">{{ optional($p->pegawai)->nip ?? '-' }}</td>
          <td class="px-4 py-2 text-blue-700 font-semibold">{{ $p->nilai_akhir }}</td>
        </tr>
        @empty
        <tr>
          <td colspan="4" class="text-center py-6 text-gray-500">Tidak ada data nilai akhir pegawai.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <!-- Paginasi -->
  @if ($progress->hasPages())
  <div class="flex items-center justify-between border-t border-white/10 px-4 py-3 sm:px-6">
    <!-- Mobile Previous/Next -->
    <div class="flex flex-1 justify-between sm:hidden">
      @if ($progress->onFirstPage())
      <span class="relative inline-flex items-center rounded-md border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-gray-400 cursor-not-allowed">Previous</span>
      @else
      <a href="{{ $progress->appends(['tugas_page' => $tugas->currentPage()])->previousPageUrl() }}" class="relative inline-flex items-center rounded-md border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-gray-200 hover:bg-white/10">Previous</a>
      @endif

      @if ($progress->hasMorePages())
      <a href="{{ $progress->appends(['tugas_page' => $tugas->currentPage()])->nextPageUrl() }}" class="relative ml-3 inline-flex items-center rounded-md border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-gray-200 hover:bg-white/10">Next</a>
      @else
      <span class="relative ml-3 inline-flex items-center rounded-md border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-gray-400 cursor-not-allowed">Next</span>
      @endif
    </div>

    <!-- Desktop -->
    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
      <div>
        <p class="text-sm text-black">
          Menampilkan
          <span class="font-medium">{{ $progress->firstItem() }}</span>
          sampai
          <span class="font-medium">{{ $progress->lastItem() }}</span>
          data dari
          <span class="font-medium">{{ $progress->total() }}</span>
          data keseluruhan
        </p>
      </div>
      <div>
        <nav aria-label="Pagination" class="isolate inline-flex -space-x-px rounded-md">
          {{-- Tombol Previous --}}
          @if ($progress->onFirstPage())
          <span class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 cursor-not-allowed">
            <span class="sr-only">Previous</span>
            <svg viewBox="0 0 20 20" fill="currentColor" class="size-5">
              <path d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" />
            </svg>
          </span>
          @else
          <a href="{{ $progress->appends(['tugas_page' => $tugas->currentPage()])->previousPageUrl() }}" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 hover:bg-white/5">
            <span class="sr-only">Previous</span>
            <svg viewBox="0 0 20 20" fill="currentColor" class="size-5">
              <path d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" />
            </svg>
          </a>
          @endif

          {{-- Nomor Halaman --}}
          @foreach ($progress->getUrlRange(1, $progress->lastPage()) as $page => $url)
          @if ($page == $progress->currentPage())
          <span aria-current="page" class="relative z-10 inline-flex items-center bg-blue-700 px-4 py-2 text-sm font-semibold text-white">{{ $page }}</span>
          @else
          <a href="{{ $progress->appends(['tugas_page' => $tugas->currentPage()])->url($page) }}" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-400 hover:bg-white/5">{{ $page }}</a>
          @endif
          @endforeach

          {{-- Tombol Next --}}
          @if ($progress->hasMorePages())
          <a href="{{ $progress->appends(['tugas_page' => $tugas->currentPage()])->nextPageUrl() }}" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 hover:bg-white/5">
            <span class="sr-only">Next</span>
            <svg viewBox="0 0 20 20" fill="currentColor" class="size-5">
              <path d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" />
            </svg>
          </a>
          @else
          <span class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 cursor-not-allowed">
            <span class="sr-only">Next</span>
            <svg viewBox="0 0 20 20" fill="currentColor" class="size-5">
              <path d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" />
            </svg>
          </span>
          @endif
        </nav>
      </div>
    </div>
  </div>
  
  @endif
</div>
  <!-- Footer -->
  <footer class="text-center text-sm text-gray-500 py-4 border-t mt-8">
    © {{ date('Y') }} <strong>WOLA</strong>. All rights reserved.
  </footer>

  @endsection