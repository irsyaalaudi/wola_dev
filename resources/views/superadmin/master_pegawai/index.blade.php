@extends('layouts.app')

@section('page-title', 'Master | Pegawai')

@section('content')
<div class="bg-white rounded-2xl p-6 mb-12 border border-gray-200">
  <!-- Judul dan Form Pencarian -->
  <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4 flex-wrap">
    <h2 class="text-2xl font-semibold text-blue-600">Tabel Pegawai</h2>

    <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
      <!-- Form Pencarian -->
      <form method="GET" action="{{ route('superadmin.master_pegawai.index') }}" class="flex gap-3 w-full sm:w-auto">
        <input type="text" name="search" value="{{ request('search') }}"
          class="px-4 py-2 w-full sm:w-64 border border-gray-300 rounded-lg 
             focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400
             bg-white/50 backdrop-blur-sm placeholder-gray-500"
          placeholder="Cari nama pegawai, NIP,">
        <button type="submit"
          class="px-4 py-2 rounded-lg border border-gray-400 text-gray-600 font-medium 
             bg-white/40 backdrop-blur-sm hover:bg-gray-100 hover:text-gray-700
             transition duration-200 ease-in-out transform hover:scale-105">
          <i class="fas fa-search mr-1"></i> Cari
        </button>
      </form>

      <!-- Tombol Export -->
      <a href="{{ route('superadmin.master_pegawai.export') }}"
        class="inline-flex items-center px-4 py-2 rounded-lg border border-green-400 text-green-600 font-medium
           bg-green-200/20 backdrop-blur-sm shadow-sm 
           hover:bg-green-300/30 hover:border-green-500 hover:text-green-700
           transition duration-200 ease-in-out transform hover:scale-105">
        <i class="fas fa-file-excel mr-2"></i> Export Tabel
      </a>
    </div>
  </div>

  <!-- Alert Sukses -->
  @if(session('success'))
  <div class="mb-4 p-4 bg-green-100 border border-green-300 text-green-800 rounded-md">
    {{ session('success') }}
  </div>
  @endif

  <!-- Tabel Pegawai -->
  <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
    <table class="w-full table-auto text-sm text-gray-700">
      <thead class="bg-gradient-to-r from-blue-100 to-blue-200 text-center text-sm text-gray-700">
        <tr>
          <th class="p-3 border">No.</th>
          <th class="p-3 border">Nama Pegawai</th>
          <th class="p-3 border">NIP</th>
          <th class="p-3 border">Jabatan</th>
          <th class="p-3 border">Tim</th>
        </tr>
      </thead>
      <tbody>
        @forelse($data as $pegawai)
        <tr class="text-center odd:bg-white even:bg-gray-50 hover:bg-gray-100 transition duration-200">
          <td class="px-4 py-2 border">{{ $loop->iteration }}</td>
          <td class="text-left px-4 py-2 border font-medium">{{ $pegawai->nama }}</td>
          <td class="px-4 py-2 border">{{ $pegawai->nip }}</td>
          <td class="text-left px-4 py-2 border">{{ $pegawai->jabatan }}</td>
          <td class="text-left px-4 py-2 border">
            @if($pegawai->teams->count())
            @foreach($pegawai->teams as $team)
            <span class="inline-block bg-blue-100 text-blue-600 px-2 py-1 rounded text-xs m-0.5">
              {{ $team->nama_tim }}
              @if($team->pivot->is_leader)
              <strong>(Ketua)</strong>
              @endif
            </span>
            @endforeach
            @else
            -
            @endif
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="5" class="text-center px-4 py-6 text-gray-500 italic">Tidak ada data pegawai yang tersedia.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<!-- Footer -->
<footer class="text-center text-sm text-gray-500 py-4 border-t mt-8">
  © {{ date('Y') }} <strong>WOLA</strong>. All rights reserved.
</footer>
@endsection