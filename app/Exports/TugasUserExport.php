<?php

namespace App\Exports;

use App\Models\Tugas;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Carbon;

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

            if ($totalRealisasi == 0) {
                $status = 'Belum Dikerjakan';
            } elseif ($totalRealisasi < $t->target) {
                $status = 'Ongoing';
            } else {
                $status = 'Selesai';
            }

            return [
                'Nama Tim' => $t->jenisPekerjaan->team->nama_tim ?? '-',
                'Nama Pekerjaan' => $t->jenisPekerjaan->nama_pekerjaan ?? '-',
                'Target' => $t->target,
                'Total Realisasi' => $totalRealisasi,
                'Satuan' => $t->satuan,
                'Tanggal Mulai' => Carbon::parse($t->created_at)->format('Y-m-d'),
                'Deadline' => Carbon::parse($t->deadline)->format('Y-m-d'),
                'Status' => $status,
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
            'Status',
        ];
    }
}