<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class TemplateInputSheet implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'template_input';
    }

    public function headings(): array
    {
        return [
            'nama_pekerjaan',
            'satuan',
            'bobot',
            'namatim (pisahkan dengan koma jika lebih dari satu)'
        ];
    }

    public function array(): array
    {
        return [
            //['Contoh Pengolahan Data', 'Dataset', '20', 'PST,HUMAS']
        ];
    }
}