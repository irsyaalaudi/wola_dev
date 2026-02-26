<?php

namespace App\Exports\Sheets;

use App\Models\Pegawai;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class RefPegawaiSheet implements FromCollection, WithHeadings, WithTitle
{
    public function collection()
    {
        return Pegawai::select('id','nama','jabatan')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'nama' => $p->nama,
                    'jabatan' => $p->jabatan,
                    'display' => $p->id . ' - ' . $p->nama
                ];
            });
    }

    public function headings(): array
    {
        return ['id', 'nama', 'jabatan', 'display'];
    }

    public function title(): string
    {
        return 'REF_PEGAWAI';
    }
}
