@extends('layouts.app')
@section('page-title', 'Tugas Saya')

@section('content')
<div class="bg-white rounded-lg shadow p-6">
  <h3 class="text-lg font-semibold mb-4">Daftar Tugas Anda</h3>

  {{-- Form Search --}}
  <form method="GET" action="{{ route('user.pekerjaan.index') }}"
    class="flex flex-wrap gap-4 mb-6 items-center max-w-full">
    <input type="text" name="jenis_pekerjaan" value="{{ request('jenis_pekerjaan') }}"
      placeholder="Cari Jenis Pekerjaan..."
      class="flex-grow min-w-[150px] max-w-xs px-3 py-2 border rounded" />

    {{-- Filter Bulan --}}
    <div class="flex items-center gap-2">
        <select name="bulan" id="filter_bulan" class="px-3 py-2 border rounded bg-white filter-group-1">
            <option value="">-- Semua Bulan --</option>
            @foreach([1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'] as $num => $name)
                <option value="{{ $num }}" {{ request('bulan') == $num ? 'selected' : '' }}>{{ $name }}</option>
            @endforeach
        </select>
    </div>

    {{-- Filter Tahun --}}
    <select name="tahun" id="filter_tahun" class="px-3 py-2 border rounded bg-white filter-group-1">
        <option value="">-- Semua Tahun --</option>
        @php
            $tahunMulai = 2026;
            $tahunSekarang = date('Y');
            $daftarTahun = range($tahunMulai, max($tahunMulai, $tahunSekarang));
        @endphp
        @foreach($daftarTahun as $y)
            <option value="{{ $y }}" {{ request('tahun') == $y ? 'selected' : '' }}>{{ $y }}</option>
        @endforeach
    </select>

    {{-- Rentang Tanggal --}}
    <div class="flex items-center gap-2 min-w-[180px]">
        <label for="start_date" class="font-semibold text-gray-700 whitespace-nowrap">Tanggal Mulai:</label>
        <input type="date" id="start_date" name="start_date" value="{{ request('start_date') }}" class="flex-grow px-3 py-2 border rounded filter-group-2" />
    </div>

    <div class="flex items-center gap-2 min-w-[180px]">
        <label for="end_date" class="font-semibold text-gray-700 whitespace-nowrap">Tanggal Akhir:</label>
        <input type="date" id="end_date" name="end_date" value="{{ request('end_date') }}" class="flex-grow px-3 py-2 border rounded filter-group-2" />
    </div>

    <button type="submit"
      class="bg-blue-600 hover:bg-blue-700 text-white rounded px-4 py-2 whitespace-nowrap">
      Filter
    </button>

    <a href="{{ route('user.pekerjaan.index') }}" 
       class="bg-gray-500 hover:bg-gray-600 text-white rounded px-4 py-2 whitespace-nowrap">
      Reset Filter
    </a>
  </form>

  @forelse($tugas as $t)
  <a id="tugas-{{ $t->id }}"></a>
  <div class="border rounded-lg mb-4 overflow-hidden">
    <div class="bg-blue-500 text-white px-4 py-3 flex justify-between items-center">
      <span class="font-semibold">{{ $t->jenisPekerjaan->nama_pekerjaan ?? '-' }}</span>
      <div class="flex items-center gap-2">
        @php
        $totalRealisasi = $t->total_realisasi ?? $t->semuaRealisasi->sum('realisasi');
        @endphp
        @if($totalRealisasi == 0)
        <span class="bg-gray-300 text-gray-800 text-xs px-2 py-1 rounded">Belum Dikerjakan</span>
        <button class="bg-white text-gray-800 px-3 py-1 rounded text-sm"
          onclick="document.getElementById('modalRealisasi{{ $t->id }}').classList.remove('hidden')">
          Isi Realisasi
        </button>
        @elseif($totalRealisasi < $t->target)
          <span class="bg-yellow-300 text-yellow-900 text-xs px-2 py-1 rounded">Ongoing</span>
          <button class="bg-yellow-500 text-white px-3 py-1 rounded text-sm"
            onclick="document.getElementById('modalRealisasi{{ $t->id }}').classList.remove('hidden')">
            Tambah Realisasi
          </button>
          @else
          <span class="bg-green-500 text-white text-xs px-2 py-1 rounded">Selesai</span>
          <button class="bg-green-600 text-white px-3 py-1 rounded text-sm"
            onclick="document.getElementById('modalRealisasi{{ $t->id }}').classList.remove('hidden')">
            Tambah Realisasi
          </button>
          @endif
      </div>
    </div>

    <div class="p-4">
      <p><strong>Tanggal Mulai: </strong> {{ \Carbon\Carbon::parse($t->created_at)->format('d M Y') }}</p>
      <p><strong>Target:</strong> {{ $t->target }} {{ $t->satuan }}</p>
      <p><strong>Total Realisasi:</strong> {{ $totalRealisasi }}</p>
      <p><strong>Bobot:</strong> {{ $t->bobot_asli ?? ($t->jenisPekerjaan->bobot ?? 0) }}</p>
      <p><strong>Penalti Keterlambatan:</strong> {{ $t->penalti ?? 0 }}</p>
      <p><strong>Nilai Akhir:</strong> {{ $t->nilai_akhir ?? 0 }}</p>
      <p>
        <strong>Tanggal Akhir:</strong> {{ \Carbon\Carbon::parse($t->deadline)->format('d M Y') }}
        @if($t->is_late)
        <span class="ml-2 bg-red-500 text-white px-2 py-1 rounded text-xs">Terlambat</span>
        @else
        <span class="ml-2 bg-green-500 text-white px-2 py-1 rounded text-xs">Tepat Waktu</span>
        @endif
      </p>

      {{-- Histori Realisasi --}}
      @if(!empty($t->rincian) && count($t->rincian))
      <div class="mt-4">
        <h5 class="font-semibold mb-2">Histori Realisasi</h5>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm border">
            <thead class="bg-gray-100">
              <tr>
                <th class="px-2 py-1 border">#</th>
                <th class="px-2 py-1 border">Tanggal Input</th>
                <th class="px-2 py-1 border">Tanggal Realisasi</th>
                <th class="px-2 py-1 border">Jumlah</th>
                <th class="px-2 py-1 border">Akumulasi</th>
                <th class="px-2 py-1 border">Capaian</th>
                <th class="px-2 py-1 border">Catatan</th>
                <th class="px-2 py-1 border">Bukti</th>
              </tr>
            </thead>
            <tbody>
              @foreach($t->rincian as $i => $r)
              <tr>
                <td class="px-2 py-1 border text-center">{{ $i+1 }}</td>
                <td class="px-2 py-1 border">{{ $r['tanggal_input'] }}</td>
                <td class="px-2 py-1 border">{{ $r['tanggal_realisasi'] }}</td>
                <td class="px-2 py-1 border text-center">{{ $r['jumlah'] }}</td>
                <td class="px-2 py-1 border text-center">{{ $r['akumulasi'] }}</td>
                <td class="px-2 py-1 border text-center">{{ $r['persen'] }}%</td>
                <td class="px-2 py-1 border">{{ $r['catatan'] ?? '-' }}</td>
                <td class="px-2 py-1 border text-center">
                  @if(!empty($r['file_bukti']))
                  <a href="{{ asset('storage/'.$r['file_bukti']) }}"
                    target="_blank" class="text-blue-600 underline">Lihat</a>
                  @else
                  -
                  @endif
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      @endif
    </div>

    {{-- Modal Input Realisasi --}}
    <div id="modalRealisasi{{ $t->id }}"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
      <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6">
        <h5 class="text-lg font-semibold mb-4">
          Realisasi: {{ $t->jenisPekerjaan->nama_pekerjaan ?? '-' }}
        </h5>

        @if($errors->any())
        <div class="bg-red-100 text-red-700 p-2 rounded mb-3 text-sm">
          <ul class="list-disc pl-4">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
        @endif

        <form action="{{ route('user.pekerjaan.realisasi', $t->id) }}"
          method="POST" enctype="multipart/form-data" class="space-y-4">
          @csrf
          <input type="number" name="realisasi" placeholder="Jumlah Realisasi"
            class="w-full border rounded px-3 py-2"
            min="1"
            required>
          <input type="date" name="tanggal_realisasi"
            class="w-full border rounded px-3 py-2"
            min="{{ $t->created_at->toDateString() }}"
            required>
          <textarea name="catatan" placeholder="Catatan"
            class="w-full border rounded px-3 py-2"></textarea>
          <input type="file" name="file_bukti" accept=".pdf,image/*"
            class="w-full border rounded px-3 py-2">
          <div class="flex justify-end gap-2">
            <button type="button"
              class="bg-gray-300 px-4 py-2 rounded"
              onclick="document.getElementById('modalRealisasi{{ $t->id }}').classList.add('hidden')">
              Batal
            </button>
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded">Kirim</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  @empty
  <div class="bg-blue-100 text-blue-700 p-4 rounded">Tidak ada tugas yang tersedia saat ini.</div>
  @endforelse
</div>

<footer class="text-center text-sm text-gray-500 py-4 border-t mt-8">
  © {{ date('Y') }} <strong>WOLA</strong>. All rights reserved.
</footer>

<script>
  document.addEventListener('DOMContentLoaded', function() {
      const group1 = document.querySelectorAll('.filter-group-1'); 
      const group2 = document.querySelectorAll('.filter-group-2'); 

      function updateDisableState() {
          const isGroup1Filled = Array.from(group1).some(el => el.value !== "");
          const isGroup2Filled = Array.from(group2).some(el => el.value !== "");

          group2.forEach(el => {
              el.disabled = isGroup1Filled;
              if(isGroup1Filled) el.classList.add('bg-gray-100', 'cursor-not-allowed');
              else el.classList.remove('bg-gray-100', 'cursor-not-allowed');
          });

          group1.forEach(el => {
              el.disabled = isGroup2Filled;
              if(isGroup2Filled) el.classList.add('bg-gray-100', 'cursor-not-allowed');
              else el.classList.remove('bg-gray-100', 'cursor-not-allowed');
          });
      }

      [...group1, ...group2].forEach(input => {
          input.addEventListener('change', updateDisableState);
      });

      updateDisableState();
  });
  </script>

@if(session('scroll_to'))
  <script>
    document.addEventListener("DOMContentLoaded", function() {
        const el = document.getElementById("tugas-{{ session('scroll_to') }}");
        if (el) {
            el.scrollIntoView({ behavior: "smooth", block: "start" });
        }
    });
  </script>
@endif
@endsection