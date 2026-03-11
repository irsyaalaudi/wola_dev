<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class JenisPekerjaanImport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'template_input' => new JenisPekerjaanSheetImport(),
        ];
    }
}