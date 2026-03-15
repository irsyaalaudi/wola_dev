<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Comment;

class InputTugasSheet implements FromArray, WithHeadings, WithTitle, WithEvents
{
    public function headings(): array
    {
        return [
            'pegawai_id',
            'jenis_pekerjaan_id',
            'target',
            'start_date (format: yyyy-mm-dd)',
            'deadline (format: yyyy-mm-dd)',
        ];
    }

    public function array(): array
    {
        return array_fill(0, 100, ['', '', '', '', '']);
    }

    public function title(): string
    {
        return 'INPUT_TUGAS';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                $sheet = $event->sheet->getDelegate();

                // =========================
                // Dropdown Pegawai (Kolom A)
                // =========================
                for ($row = 2; $row <= 200; $row++) {

                    $validation = $sheet->getCell("A$row")->getDataValidation();
                    $validation->setType(DataValidation::TYPE_LIST);
                    $validation->setErrorStyle(DataValidation::STYLE_STOP);
                    $validation->setAllowBlank(true);
                    $validation->setShowDropDown(true);
                    $validation->setFormula1("='REF_PEGAWAI'!\$D\$2:\$D\$500");
                }

                // =========================
                // Dropdown Jenis (Kolom B)
                // =========================
                for ($row = 2; $row <= 200; $row++) {

                    $validation = $sheet->getCell("B$row")->getDataValidation();
                    $validation->setType(DataValidation::TYPE_LIST);
                    $validation->setErrorStyle(DataValidation::STYLE_STOP);
                    $validation->setAllowBlank(true);
                    $validation->setShowDropDown(true);
                    $validation->setFormula1("='REF_JENIS'!\$E\$2:\$E\$1000");
                }

                // =========================
                // Format Deadline
                // =========================
                // Format start_date
                $sheet->getStyle('D2:D200')
                    ->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_DATE_YYYYMMDD2);

                // Format deadline
                // Format durasi (angka)
                $sheet->getStyle('E2:E200')
                    ->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_DATE_YYYYMMDD2);


                // Validasi tanggal
                for ($row = 2; $row <= 200; $row++) {
                    $validationStart = $sheet->getCell("D$row")->getDataValidation();
                    $validationStart->setType(DataValidation::TYPE_DATE);
                    $validationStart->setOperator(DataValidation::OPERATOR_BETWEEN);
                    $validationStart->setAllowBlank(true);
                    $validationStart->setFormula1('DATE(2000,1,1)');
                    $validationStart->setFormula2('DATE(2100,12,31)');

                    // DEADLINE
                    $validationDurasi = $sheet->getCell("E$row")->getDataValidation();
                    $validationDurasi->setType(DataValidation::TYPE_DATE);
                    $validationDurasi->setOperator(DataValidation::OPERATOR_BETWEEN);
                    $validationDurasi->setFormula1('DATE(2000,1,1)');
                    $validationDurasi->setFormula2('DATE(2100,12,31)');
                    $validationDurasi->setAllowBlank(true);
                }

                // Freeze Header
                $sheet->freezePane('A2');

                // Auto width
                foreach (range('A','E') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}
