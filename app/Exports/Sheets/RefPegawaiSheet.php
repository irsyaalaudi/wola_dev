<?php

namespace App\Exports\Sheets;

use App\Models\Pegawai;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class RefPegawaiSheet implements FromCollection, WithHeadings, WithTitle
{
    protected $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function collection()
    {
        $pegawaiLogin = $this->user->pegawai;

        return Pegawai::with(['user', 'teams'])
            ->whereHas('teams.pegawais', function ($q) use ($pegawaiLogin) {
                $q->where('pegawai_team.pegawai_id', $pegawaiLogin->id)
                    ->where('pegawai_team.is_leader', 1);
            })
            ->get()
            ->map(function ($p) {
                $nama = $p->user->name ?? '-';

                return [
                    'id' => $p->id,
                    'nama' => $nama,
                    'jabatan' => $p->jabatan,
                    'display' => $p->id . ' - ' . $nama
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
