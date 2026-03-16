@extends('layouts.app')
@section('page-title', 'Export Laporan')

@section('content')

    <div class="bg-white rounded-lg shadow p-6">

        <h3 class="text-lg font-semibold mb-6 text-gray-800">
            Laporan Tugas
        </h3>

        {{-- FORM FILTER --}}
        <form method="GET" action="{{ route('user.export_laporan.index') }}" class="space-y-4 mb-6">

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium text-gray-700">
                        Jenis Pekerjaan
                    </label>

                    <input type="text" name="jenis_pekerjaan" value="{{ request('jenis_pekerjaan') }}"
                        placeholder="Cari Jenis Pekerjaan..." class="w-full px-3 py-2 border border-gray-300 rounded-lg" />

                </div>


                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium text-gray-700">
                        Tim
                    </label>

                    <select name="tim" class="w-full px-3 py-2 border border-gray-300 rounded-lg">

                        <option value="">Semua Tim</option>

                        @foreach ($timList as $tim)
                            <option value="{{ $tim->id }}" {{ request('tim') == $tim->id ? 'selected' : '' }}>

                                {{ $tim->nama_tim }}

                            </option>
                        @endforeach

                    </select>

                </div>


                <div class="flex flex-col gap-1">

                    <label class="text-sm font-medium text-gray-700">
                        Bulan
                    </label>

                    <select name="bulan" class="w-full px-3 py-2 border border-gray-300 rounded-lg">

                        <option value="">Semua Bulan</option>

                        @foreach ([1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'] as $num => $name)
                            <option value="{{ $num }}" {{ request('bulan') == $num ? 'selected' : '' }}>

                                {{ $name }}

                            </option>
                        @endforeach

                    </select>

                </div>


                <div class="flex flex-col gap-1">

                    <label class="text-sm font-medium text-gray-700">
                        Tahun
                    </label>

                    <select name="tahun" class="w-full px-3 py-2 border border-gray-300 rounded-lg">

                        <option value="">Semua Tahun</option>

                        @php
                            $tahunMulai = 2026;
                            $tahunSekarang = date('Y');
                            $daftarTahun = range($tahunMulai, max($tahunMulai, $tahunSekarang));
                        @endphp

                        @foreach ($daftarTahun as $y)
                            <option value="{{ $y }}" {{ request('tahun') == $y ? 'selected' : '' }}>

                                {{ $y }}

                            </option>
                        @endforeach

                    </select>

                </div>

            </div>


            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">

                <div class="flex flex-col gap-1">

                    <label class="text-sm font-medium text-gray-700">
                        Tanggal Mulai
                    </label>

                    <input type="date" name="start_date" value="{{ request('start_date') }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg" />

                </div>


                <div class="flex flex-col gap-1">

                    <label class="text-sm font-medium text-gray-700">
                        Tanggal Akhir
                    </label>

                    <input type="date" name="end_date" value="{{ request('end_date') }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg" />

                </div>


                <div class="lg:col-span-2 flex gap-2">

                    <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg px-6 py-2">

                        Filter

                    </button>


                    <a href="{{ route('user.export_laporan.index') }}" class="bg-gray-200 px-6 py-2 rounded">

                        Reset

                    </a>


                    <a href="{{ route('user.pekerjaan.export', request()->query()) }}"
                        class="bg-green-600 text-white px-6 py-2 rounded">

                        Export Excel

                    </a>

                </div>

            </div>

        </form>


        {{-- TABLE LAPORAN --}}

        <div class="overflow-x-auto">

            <table class="min-w-full text-sm border">

                <thead class="bg-gradient-to-r from-blue-100 to-blue-200 text-center text-sm text-gray-700">

                    <tr>

                        <th class="px-2 py-2 border">No</th>

                        <th class="px-2 py-2 border">Nama Tim</th>

                        <th class="px-2 py-2 border">Nama Pekerjaan</th>

                        <th class="px-2 py-2 border">Target</th>

                        <th class="px-2 py-2 border">Total Realisasi</th>

                        <th class="px-2 py-2 border">Histori Perubahan</th>

                        <th class="px-2 py-2 border">Satuan</th>

                        <th class="px-2 py-2 border">Tanggal Mulai</th>

                        <th class="px-2 py-2 border">Deadline</th>

                        <th class="px-2 py-2 border">Progress (%)</th>

                        <th class="px-2 py-2 border">Bobot</th>

                        <th class="px-2 py-2 border">Nilai Saat Ini</th>

                        <th class="px-2 py-2 border">Status</th>

                    </tr>

                </thead>


                <tbody>

                    @forelse($tugas as $t)
                        @php

                            $totalRealisasi = $t->semuaRealisasi->sum('realisasi');

                            $progress = $t->target > 0 ? round(($totalRealisasi / $t->target) * 100, 2) : 0;

                            $bobot = $t->jenisPekerjaan->bobot ?? 0;

                        @endphp

                        @php
                            $realisasiFiltered = $t->semuaRealisasi;

                            // Filter histori yang ditampilkan berdasarkan input bulan/tahun
                            if (request('bulan')) {
                                $realisasiFiltered = $realisasiFiltered->filter(function ($r) {
                                    return \Carbon\Carbon::parse($r->tanggal_realisasi)->month == request('bulan');
                                });
                            }

                            if (request('tahun')) {
                                $realisasiFiltered = $realisasiFiltered->filter(function ($r) {
                                    return \Carbon\Carbon::parse($r->tanggal_realisasi)->year == request('tahun');
                                });
                            }

                            // Hitung ulang total realisasi hanya dari data yang sudah difilter (opsional)
                            // $totalRealisasi = $realisasiFiltered->sum('realisasi');

                        @endphp

                        <tr>

                            <td class="border px-2 py-1 text-center">
                                {{ $loop->iteration }}
                            </td>
                            <td class="border px-2 py-1">

                                {{ $t->jenisPekerjaan->teams->first()->nama_tim ?? '-' }}

                            </td>


                            <td class="border px-2 py-1">

                                {{ $t->jenisPekerjaan->nama_pekerjaan ?? '-' }}

                            </td>


                            <td class="border px-2 py-1">

                                {{ $t->target }}

                            </td>


                            <td class="border px-2 py-1">

                                {{ $totalRealisasi }}

                            </td>

                            <td class="border px-2 py-1">

                                @if ($realisasiFiltered->count())
                                    @foreach ($realisasiFiltered as $r)
                                        <div>

                                            {{ \Carbon\Carbon::parse($r->tanggal_realisasi)->format('d M Y') }}
                                            : {{ $r->realisasi }}

                                            @if (!$r->is_approved)
                                                <span class="text-yellow-500">(Menunggu Approve)</span>
                                            @endif

                                        </div>
                                    @endforeach
                                @else
                                    -
                                @endif

                            </td>

                            <td class="border px-2 py-1">

                                {{ $t->jenisPekerjaan->satuan ?? '-' }}

                            </td>


                            @php
                                $start = \Carbon\Carbon::parse($t->start_date ?? $t->created_at);
                                $end = \Carbon\Carbon::parse($t->deadline);

                                if (request('bulan') && request('tahun')) {
                                    $awalBulan = \Carbon\Carbon::create(
                                        request('tahun'),
                                        request('bulan'),
                                        1,
                                    )->startOfMonth();
                                    $akhirBulan = \Carbon\Carbon::create(
                                        request('tahun'),
                                        request('bulan'),
                                        1,
                                    )->endOfMonth();

                                    if ($start->lt($awalBulan)) {
                                        $start = $awalBulan;
                                    }

                                    if ($end->gt($akhirBulan)) {
                                        $end = $akhirBulan;
                                    }
                                }
                            @endphp

                            <td class="border px-2 py-1">
                                {{ $start->format('Y-m-d') }}
                            </td>

                            <td class="border px-2 py-1">
                                {{ $end->format('Y-m-d') }}
                            </td>


                            <td class="border px-2 py-1">

                                {{ $progress }}

                            </td>


                            <td class="border px-2 py-1">

                                {{ $bobot }}

                            </td>


                            <td class="border px-2 py-1">

                                {{ round($bobot * ($progress / 100), 2) }}

                            </td>


                            <td class="border px-2 py-1">

                                {{ $t->status }}

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="13" class="text-center py-4">

                                Tidak ada data

                            </td>

                        </tr>
                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

    <footer class="text-center text-sm text-gray-500 py-4 border-t mt-8">
        © {{ date('Y') }} <strong>WOLA</strong>. All rights reserved.
    </footer>

@endsection
