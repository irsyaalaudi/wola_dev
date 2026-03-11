<?php

namespace App\Exports\Sheets;

use App\Models\Team;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class RefTimSheet implements FromCollection, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'tim';
    }

    public function collection()
    {
        return Team::select('id','nama_tim')->get();
    }

    public function headings(): array
    {
        return [
            'id',
            'nama_tim'
        ];
    }
}