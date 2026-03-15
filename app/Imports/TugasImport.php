<?php

namespace App\Imports;

use App\Models\Tugas;
use App\Models\JenisPekerjaan;
use Carbon\Carbon;
use Exception;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class TugasImport implements ToModel, WithHeadingRow
{
    protected $teamIds;
    protected $rowNumber = 1;

    public function __construct($teamIds)
    {
        $this->teamIds = $teamIds;
    }

    public function model(array $row)
    {
        $this->rowNumber++;

        /*
        |--------------------------------------------------------------------------
        | SKIP BARIS KOSONG
        |--------------------------------------------------------------------------
        */
        if (empty($row['pegawai_id']) || empty($row['jenis_pekerjaan_id'])) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | AMBIL ID PEGAWAI DAN JENIS PEKERJAAN
        |--------------------------------------------------------------------------
        */
        $pegawaiId = explode(' - ', $row['pegawai_id'])[0] ?? null;
        $jenisId   = explode(' - ', $row['jenis_pekerjaan_id'])[0] ?? null;

        if (!$pegawaiId || !$jenisId) {
            throw new Exception("Baris {$this->rowNumber}: Format Pegawai atau Jenis Pekerjaan salah.");
        }

        /*
        |--------------------------------------------------------------------------
        | CEK JENIS PEKERJAAN ADA ATAU TIDAK
        |--------------------------------------------------------------------------
        */
        $jenis = JenisPekerjaan::find($jenisId);

        if (!$jenis) {
            throw new Exception("Baris {$this->rowNumber}: Jenis pekerjaan tidak ditemukan.");
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDASI START DATE
        |--------------------------------------------------------------------------
        */
        try {

            $startDateValue = $row['start_date_format_yyyy_mm_dd'] ?? null;

            if (!$startDateValue) {
                throw new Exception("Start Date kosong.");
            }

            if (is_numeric($startDateValue)) {
                $startDate = Carbon::instance(
                    Date::excelToDateTimeObject($startDateValue)
                );
            } else {
                $startDate = Carbon::parse($startDateValue);
            }

        } catch (\Exception $e) {
            throw new Exception("Baris {$this->rowNumber}: Format start_date salah. Gunakan YYYY-MM-DD.");
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDASI DEADLINE
        |--------------------------------------------------------------------------
        */
        try {

            $deadlineValue = $row['deadline_format_yyyy_mm_dd'] ?? null;

            if (!$deadlineValue) {
                throw new Exception("Deadline kosong.");
            }

            if (is_numeric($deadlineValue)) {
                $deadline = Carbon::instance(
                    Date::excelToDateTimeObject($deadlineValue)
                );
            } else {
                $deadline = Carbon::parse($deadlineValue);
            }

        } catch (\Exception $e) {
            throw new Exception("Baris {$this->rowNumber}: Format deadline salah. Gunakan YYYY-MM-DD.");
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDASI DEADLINE >= START DATE
        |--------------------------------------------------------------------------
        */
        if ($deadline->lt($startDate)) {
            throw new Exception("Baris {$this->rowNumber}: Deadline tidak boleh sebelum Start Date.");
        }

        /*
        |--------------------------------------------------------------------------
        | SIMPAN DATA
        |--------------------------------------------------------------------------
        */
        return Tugas::create([
            'pegawai_id'         => $pegawaiId,
            'jenis_pekerjaan_id' => $jenisId,
            'target'             => $row['target'] ?? 0,
            'asal'               => auth()->user()->name,
            'start_date'         => $startDate,
            'deadline'           => $deadline,
            'status'             => 'pending',
        ]);
    }
}