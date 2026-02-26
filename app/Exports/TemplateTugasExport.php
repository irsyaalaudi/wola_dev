<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\Sheets\InputTugasSheet;
use App\Exports\Sheets\RefPegawaiSheet;
use App\Exports\Sheets\RefJenisSheet;

class TemplateTugasExport implements WithMultipleSheets
{
    protected $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function sheets(): array
    {
        return [
            new InputTugasSheet(),
            new RefPegawaiSheet(),
            new RefJenisSheet($this->user),
        ];
    }
}
