<?php

namespace App\Imports;

use App\Models\JenisPekerjaan;
use App\Models\Team;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class JenisPekerjaanSheetImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        foreach ($rows->skip(1) as $row) {

            if ($row->filter()->isEmpty()) {
                continue;
            }

            $nama   = $row[0] ?? null;
            $satuan = $row[1] ?? null;
            $bobot  = $row[2] ?? 0;
            $timString = $row[3] ?? null;

            if (!$nama) {
                continue;
            }

            $pekerjaan = JenisPekerjaan::create([
                'nama_pekerjaan' => $nama,
                'satuan' => $satuan,
                'bobot' => $bobot
            ]);

            if ($timString) {

                $timArray = explode(',', $timString);

                foreach ($timArray as $tim) {

                    $team = Team::where('nama_tim', trim($tim))->first();

                    if ($team) {
                        $pekerjaan->teams()->attach($team->id);
                    }
                }
            }
        }
    }
}