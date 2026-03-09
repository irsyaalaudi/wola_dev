<?php

namespace App\Imports;

use App\Models\Tugas;
use App\Models\JenisPekerjaan;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class TugasImport implements ToModel, WithHeadingRow
{
    protected $teamIds;

    public function __construct($teamIds)
    {
        $this->teamIds = $teamIds;
    }

    public function model(array $row)
{
    if (empty($row['pegawai_id']) || empty($row['jenis_pekerjaan_id'])) {
        return null;
    }

    $pegawaiId = explode(' - ', $row['pegawai_id'])[0] ?? null;
    $jenisId   = explode(' - ', $row['jenis_pekerjaan_id'])[0] ?? null;

    if (!$pegawaiId || !$jenisId) {
        return null;
    }

    $jenis = JenisPekerjaan::where('id', $jenisId)
        ->whereIn('tim_id', $this->teamIds)
        ->first();

    if (!$jenis) {
        return null;
    }

    $deadline = null;

    if (!empty($row['deadline'])) {

        if (is_numeric($row['deadline'])) {
            $deadline = \PhpOffice\PhpSpreadsheet\Shared\Date
                ::excelToDateTimeObject($row['deadline'])
                ->format('Y-m-d');
        } else {
            $deadline = \Carbon\Carbon
                ::parse($row['deadline'])
                ->format('Y-m-d');
        }
    }

    return new Tugas([
        'pegawai_id' => $pegawaiId,
        'jenis_pekerjaan_id' => $jenisId,
        'target' => $row['target'] ?? 0,
        'satuan' => $jenis->satuan,
        'asal' => auth()->user()->pegawai->nama ?? auth()->user()->name,
        'deadline' => $deadline,
    ]);
}
}