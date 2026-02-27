@extends('layouts.app')
@section('page-title', 'Dashboard')

@section('content')
<div x-data="{ showTable: true, showChart: false }" class="bg-white rounded-2xl p-6 mb-12 border border-gray-200">

    {{-- Judul & Filter --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4 flex-wrap">
        <h2 class="text-2xl font-semibold text-blue-600">Dashboard Superadmin</h2>
        <div class="flex flex-col sm:flex-row items-center gap-3">
            <form method="GET" action="{{ route('superadmin.dashboard') }}" class="flex gap-3 w-full sm:w-auto">
                {{-- Dropdown Bulan --}}
                <select name="bulan" class="px-4 py-2 border rounded-lg">
                    <option value="">Semua Bulan</option>
                    @foreach(['Januari','Februari','Maret','April','Mei','Juni',
                    'Juli','Agustus','September','Oktober','November','Desember'] as $index => $bulan)
                    <option value="{{ $index+1 }}" {{ request('bulan') == $index+1 ? 'selected' : '' }}>
                        {{ $bulan }}
                    </option>
                    @endforeach
                </select>

                {{-- Input Tahun --}}
                <input type="number" name="tahun" class="px-4 py-2 border rounded-lg w-24"
                    placeholder="Tahun" value="{{ request('tahun') }}">

                {{-- Search --}}
                <input type="text" name="search" class="px-4 py-2 border rounded-lg"
                    placeholder="Cari nama pegawai..." value="{{ request('search') }}">

                {{-- Tombol Filter --}}
                <button type="submit"
                    class="px-4 py-2 rounded-lg border border-blue-400 text-blue-600 font-medium
                           hover:bg-blue-600 hover:text-white transition">
                    Filter
                </button>
            </form>
            <a href="{{ route('superadmin.dashboard.export', request()->all()) }}"
                class="px-4 py-2 rounded-lg border border-green-400 text-green-600 font-medium
              hover:bg-green-600 hover:text-white transition">
                Export Tabel
            </a>
        </div>
    </div>

    {{-- Statistik Global --}}
    <div class="grid grid-cols-1 sm:grid-cols-5 gap-4 mb-6">
        <div class="p-4 bg-blue-100 rounded-lg text-center">
            <div class="text-xl font-bold">{{ $totalPegawai }}</div>
            <div class="text-sm text-gray-600">Total Pegawai</div>
        </div>
        <div class="p-4 bg-green-100 rounded-lg text-center">
            <div class="text-xl font-bold">{{ $totalTugas }}</div>
            <div class="text-sm text-gray-600">Total Tugas</div>
        </div>
        <div class="p-4 bg-yellow-100 rounded-lg text-center">
            <div class="text-xl font-bold">{{ $ongoing }}</div>
            <div class="text-sm text-gray-600">Ongoing</div>
        </div>
        <div class="p-4 bg-red-100 rounded-lg text-center">
            <div class="text-xl font-bold">{{ $selesai }}</div>
            <div class="text-sm text-gray-600">Selesai</div>
        </div>
        <div class="p-4 bg-purple-100 rounded-lg text-center">
            <div class="text-xl font-bold">{{ $nilaiKeseluruhan }}%</div>
            <div class="text-sm text-gray-600">Nilai Keseluruhan</div>
        </div>
    </div>

    {{-- Tombol Toggle Tabel / Grafik --}}
    <div class="flex gap-2 mb-4">
        <button @click="showTable = !showTable; showChart = !showChart"
            class="px-3 py-2 rounded bg-blue-100 text-blue-700 hover:bg-blue-200 text-sm">
            <i :class="showTable ? 'fas fa-chart-bar' : 'fas fa-table'" class="mr-1"></i>
            <span x-text="showTable ? 'Tampilkan Grafik' : 'Tampilkan Tabel'"></span>
        </button>
    </div>

    {{-- Tabel Pegawai --}}
    <div x-show="showTable" x-transition class="mb-6">
        <div class="table-wrapper w-full md:w-[1600px]">
    <div class="md-card-content overflow-x-auto">
        <table class="table-auto text-sm text-gray-700 border-collapse min-w-max">
            <thead class="bg-gradient-to-r from-blue-100 to-blue-200 text-gray-700 text-center">
                <tr>
                    <th rowspan="2" class="px-3 py-2 border sticky left-0 bg-blue-100 z-10">No.</th>
                    <th rowspan="2" class="px-3 py-2 border text-left sticky left-12 bg-blue-100 z-10">Nama Pegawai</th>
                    <th rowspan="2" class="px-3 py-2 border">Jabatan</th>
                    <th rowspan="2" class="px-3 py-2 border">Score (%)</th>
                    <th rowspan="2" class="px-3 py-2 border">Grade</th>
                    @foreach ($teams as $team)
                        <th colspan="2" class="px-3 py-2 border bg-blue-400 text-white">
                            {{ strtoupper($team->nama_tim) }}
                        </th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($teams as $team)
                        <th class="px-2 py-1 border bg-blue-300 text-white">T</th>
                        <th class="px-2 py-1 border bg-blue-300 text-white">R</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($data as $index => $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 border text-center sticky left-0 bg-white z-10">{{ $index + 1 }}</td>
                        <td class="px-3 py-2 border text-left sticky left-12 bg-white z-10">{{ $item['pegawai']->nama }}</td>
                        <td class="px-3 py-2 border text-center">{{ $item['pegawai']->jabatan }}</td>
                        <td class="px-3 py-2 border text-center text-blue-700 font-semibold">{{ $item['score'] }}%</td>
                        <td class="px-3 py-2 border text-center font-semibold
                            @if($item['grade'] == 'SANGAT BAIK') text-blue-700
                            @elseif($item['grade'] == 'KURANG') text-red-600
                            @else text-gray-700
                            @endif">{{ $item['grade'] }}</td>
                        @foreach ($teams as $team)
                            @php
                                $row = collect($item['teams'])->firstWhere('team_id', $team->id);
                            @endphp
                            <td class="px-3 py-2 border text-center text-blue-700 font-semibold">
                                {{ $row ? number_format($row['total_target'], 2) : '0.00' }}
                            </td>
                            <td class="px-3 py-2 border text-center text-green-600 font-semibold">
                                {{ $row ? number_format($row['total_realisasi'], 2) : '0.00' }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 5 + ($teams->count() * 2) }}" class="px-3 py-4 border text-center text-gray-500">
                            Tidak ada data.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

        <div class="mt-2 text-sm text-gray-600">
            <span class="font-semibold">Keterangan:</span>
            <span class="px-2 py-1 bg-blue-300 text-white rounded">T = Target</span>
            <span class="px-2 py-1 bg-blue-300 text-white rounded">R = Realisasi</span>
        </div>
    </div>

    {{-- Grafik Target vs Realisasi --}}
    <div x-show="showChart" x-transition class="bg-white rounded-xl shadow-md border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-700 mb-4">Total Target vs Realisasi per Pegawai</h3>
        <canvas id="targetRealisasiChart" height="120"></canvas>
    </div>

</div>
<footer class="text-center text-sm text-gray-500 py-4 border-t mt-8">
    © {{ date('Y') }} <strong>WOLA</strong>. All rights reserved.
</footer>

{{-- Chart.js --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('targetRealisasiChart').getContext('2d');

    const namaPegawai = @json($chartLabels) || [];

    // Buat label A, B, C, D...
    const alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    const labels = namaPegawai.map((_, index) => {
        return alphabet[index] ?? `X${index+1}`;
    });

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Total Target',
                    data: @json($chartTarget),
                    backgroundColor: 'rgba(54, 162, 235, 0.6)'
                },
                {
                    label: 'Total Realisasi',
                    data: @json($chartRealisasi),
                    backgroundColor: 'rgba(75, 192, 192, 0.6)'
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

const container = document.createElement('div');

const total = namaPegawai.length;
const columns = 3;
const rows = Math.ceil(total / columns);

container.className = `mt-4 grid grid-flow-col gap-3 text-sm text-gray-700`;
container.style.gridTemplateRows = `repeat(${rows}, auto)`;

namaPegawai.forEach((nama, index) => {
    const row = document.createElement('div');
    row.innerHTML = `
        <div class="flex items-start gap-2">
            <span class="w-6 h-6 flex items-center justify-center rounded-full bg-blue-100 text-blue-700 font-semibold text-xs">
                ${labels[index]}
            </span>
            <span>${nama}</span>
        </div>
    `;
    container.appendChild(row);
});

document.getElementById('targetRealisasiChart').parentNode.appendChild(container);
</script>
@endsection