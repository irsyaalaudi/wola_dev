<?php

namespace App\Exports\Sheets;

use App\Models\JenisPekerjaan;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class RefJenisSheet implements FromCollection, WithHeadings, WithTitle
{
    protected $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function collection()
    {
        $pegawai = $this->user->pegawai;

        return JenisPekerjaan::with('teams')
            ->whereHas('teams.pegawais', function ($q) use ($pegawai) {
                $q->where('pegawai_team.is_leader', 1)
                ->where('pegawai_team.pegawai_id', $pegawai->id);
            })
            ->get()
            ->map(function ($j) {

                return [
                    'id' => $j->id,
                    'nama_pekerjaan' => $j->nama_pekerjaan,
                    'tim' => $j->teams->pluck('nama_tim')->implode(', ') ?: '-',
                    'satuan' => $j->satuan,
                    'display' => $j->id . ' - ' . $j->nama_pekerjaan
                ];

            });
    }

    public function headings(): array
    {
        return ['id', 'nama_pekerjaan', 'tim', 'satuan', 'display'];
    }

    public function title(): string
    {
        return 'REF_JENIS';
    }
}