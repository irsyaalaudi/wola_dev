<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Carbon\Carbon;

class TugasUserExport implements FromCollection, WithHeadings
{
    protected $tugas;

    public function __construct($tugas)
    {
        $this->tugas = $tugas;
    }

    public function collection()
    {
        return $this->tugas->map(function ($t) {

            $totalRealisasi = $t->semuaRealisasi->sum('realisasi');

            $progress = $t->target > 0
                ? round(($totalRealisasi / $t->target) * 100, 2)
                : 0;

            $bobot = $t->jenisPekerjaan->bobot ?? 0;

            // hitung keterlambatan
            $realisasiSortir = $t->semuaRealisasi->sortBy('tanggal_realisasi');

            $akumulasi = 0;
            $tanggalCapai100 = null;

            foreach ($realisasiSortir as $r) {
                $akumulasi += $r->realisasi;

                if ($akumulasi >= $t->target) {
                    $tanggalCapai100 = $r->tanggal_realisasi;
                    break;
                }
            }

            $hariTelat = 0;

            if ($tanggalCapai100) {

                if (Carbon::parse($tanggalCapai100)->gt(Carbon::parse($t->deadline))) {
                    $hariTelat = Carbon::parse($t->deadline)
                        ->diffInDays(Carbon::parse($tanggalCapai100));
                }

            } else {

                if (Carbon::now()->gt(Carbon::parse($t->deadline))) {
                    $hariTelat = Carbon::parse($t->deadline)
                        ->diffInDays(Carbon::now());
                }
            }

            $penalti = $bobot * 0.05 * $hariTelat;

            $nilaiSaatIni = max(0, ($bobot * ($progress / 100)) - $penalti);

            return [

                'Nama Tim' => $t->jenisPekerjaan->teams->first()->nama_tim ?? '-',

                'Nama Pekerjaan' => $t->jenisPekerjaan->nama_pekerjaan ?? '-',

                'Target' => $t->target,

                'Total Realisasi' => $totalRealisasi,

                'Satuan' => $t->jenisPekerjaan->satuan ?? '-',

                'Tanggal Mulai' =>
                    Carbon::parse($t->start_date ?? $t->created_at)->format('Y-m-d'),

                'Deadline' =>
                    Carbon::parse($t->deadline)->format('Y-m-d'),

                'Progress (%)' => $progress,

                'Bobot' => $bobot,

                'Nilai Saat Ini' => round($nilaiSaatIni, 2),

                'Status' => $t->status
            ];
        });
    }

    public function headings(): array
    {
        return [

            'Nama Tim',
            'Nama Pekerjaan',
            'Target',
            'Total Realisasi',
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