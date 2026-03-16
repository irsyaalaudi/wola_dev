<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;

class TugasUserExport implements FromCollection, WithHeadings
{
    protected $tugas;

    public function __construct($tugas)
    {
        $this->tugas = $tugas;
    }

    /**
     * Mengambil koleksi data
     */
    public function collection()
    {
        return $this->tugas->values()->map(function ($t, $index) {
            
            // 1. Kalkulasi Progress dan Bobot
            $totalRealisasi = $t->semuaRealisasi->sum('realisasi');
            $progress = $t->target > 0 ? round(($totalRealisasi / $t->target) * 100, 2) : 0;
            $bobot = $t->jenisPekerjaan->bobot ?? 0;
            $nilaiSaatIni = round($bobot * ($progress / 100), 2);

            // 2. Filter Histori Perubahan (Berdasarkan Request)
            $realisasiFiltered = $t->semuaRealisasi;
            if (request('bulan')) {
                $realisasiFiltered = $realisasiFiltered->filter(fn($r) => Carbon::parse($r->tanggal_realisasi)->month == request('bulan'));
            }
            if (request('tahun')) {
                $realisasiFiltered = $realisasiFiltered->filter(fn($r) => Carbon::parse($r->tanggal_realisasi)->year == request('tahun'));
            }

            $histori = $realisasiFiltered->sortBy('tanggal_realisasi')->map(function ($r) {
                $text = Carbon::parse($r->tanggal_realisasi)->format('d M Y') . ' : ' . $r->realisasi;
                return $r->is_approved ? $text : $text . ' (Menunggu Approve)';
            })->implode("\n");

            // 3. Logika Penyesuaian Tanggal Mulai & Deadline (Overlap)
            $startDate = Carbon::parse($t->start_date ?? $t->created_at);
            $deadline = Carbon::parse($t->deadline);

            if (request('bulan') && request('tahun')) {
                $awalBulanFilter = Carbon::create(request('tahun'), request('bulan'), 1)->startOfMonth();
                $akhirBulanFilter = Carbon::create(request('tahun'), request('bulan'), 1)->endOfMonth();

                // Jika tugas mulai sebelum bulan filter, tampilkan awal bulan filter
                if ($startDate->lt($awalBulanFilter)) {
                    $startDate = $awalBulanFilter;
                }
                // Jika deadline setelah bulan filter, tampilkan akhir bulan filter
                if ($deadline->gt($akhirBulanFilter)) {
                    $deadline = $akhirBulanFilter;
                }
            }

            // 4. Return Array Data
            return [
                'No'                => $index + 1,
                'Nama Tim'          => $t->jenisPekerjaan->teams->first()->nama_tim ?? '-',
                'Nama Pekerjaan'    => $t->jenisPekerjaan->nama_pekerjaan ?? '-',
                'Target'            => $t->target,
                'Total Realisasi'   => $totalRealisasi,
                'Histori Perubahan' => $histori,
                'Satuan'            => $t->jenisPekerjaan->satuan ?? '-',
                'Tanggal Mulai'     => $startDate->format('Y-m-d'),
                'Deadline'          => $deadline->format('Y-m-d'),
                'Progress (%)'      => $progress,
                'Bobot'             => $bobot,
                'Nilai Saat Ini'    => $nilaiSaatIni,
                'Status'            => $t->status
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No',
            'Nama Tim',
            'Nama Pekerjaan',
            'Target',
            'Total Realisasi',
            'Histori Perubahan',
            'Satuan',
            'Tanggal Mulai',
            'Deadline',
            'Progress (%)',
            'Bobot',
            'Nilai Saat Ini',
            'Status'
        ];
    }
}