<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tugas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ProgressController extends Controller
{
    /**
     * Tampilkan halaman progress tugas tim
     */
    public function index(Request $request)
    {
        $teamIds = DB::table('pegawai_team')
            ->where('pegawai_id', auth()->user()->pegawai->id)
            ->where('is_leader', 1)
            ->pluck('team_id')
            ->toArray();

        $search = $request->input('search');

        $tugas = Tugas::with(['pegawai.user', 'jenisPekerjaan.teams', 'semuaRealisasi'])
            ->whereHas('jenisPekerjaan.teams', function ($q) use ($teamIds) {
                $q->whereIn('teams.id', $teamIds);
            })
            ->whereHas('pegawai', function ($q) use ($teamIds, $search) {
                $q->whereIn('id', function ($qq) use ($teamIds) {
                    $qq->select('pegawai_id')
                        ->from('pegawai_team')
                        ->whereIn('team_id', $teamIds);
                });

                if ($search) {
                    $q->whereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"));
                }
            })
            ->get()
            ->map(function ($t) use ($teamIds) {
                $totalRealisasi = $t->semuaRealisasi->sum('realisasi');
                $progress = $t->target > 0 ? min($totalRealisasi / $t->target, 1) : 0;

                $bobot = $t->jenisPekerjaan->bobot ?? 0;

                $realisasiSortir = $t->semuaRealisasi->sortBy('tanggal_realisasi');
                $akumulasi = 0;
                $tanggalCapai100 = null;
                foreach ($realisasiSortir as $r) {
                    $akumulasi += $r->realisasi;
                    if ($akumulasi >= $t->target) {
                        $tanggalCapai100 = $r->tanggal_realisasi;
                        break;
                    }
                }

                $hariTelat = 0;
                if ($tanggalCapai100) {
                    if (Carbon::parse($tanggalCapai100)->gt(Carbon::parse($t->deadline))) {
                        $hariTelat = Carbon::parse($t->deadline)
                            ->diffInDays(Carbon::parse($tanggalCapai100));
                    }
                } else {
                    if (Carbon::now()->gt(Carbon::parse($t->deadline))) {
                        $hariTelat = Carbon::parse($t->deadline)->diffInDays(Carbon::now());
                    }
                }
                $penalti = $bobot * 0.1 * $hariTelat;
                $nilaiAkhir = max(0, ($bobot * $progress) - $penalti);

                $namaTim = $t->jenisPekerjaan->teams
                    ->whereIn('id', $teamIds)
                    ->pluck('nama_tim')
                    ->implode(', ') ?: '-';
                $realisasiTerakhir = $t->semuaRealisasi->last();

                return [
                    'id' => $t->id,
                    'pegawai' => $t->pegawai->user->name ?? '-',
                    'tim' => $namaTim,
                    'nama_tugas' => $t->jenisPekerjaan->nama_pekerjaan ?? '-',
                    'target' => $t->target,
                    'satuan' => $t->jenisPekerjaan->satuan ?? '-',
                    'totalRealisasi' => $totalRealisasi,
                    'realisasiTerakhir' => $realisasiTerakhir->realisasi ?? null,
                    'histori' => $t->semuaRealisasi,
                    'bobot' => $bobot,
                    'hariTelat' => $hariTelat,
                    'nilaiAkhir' => round($nilaiAkhir, 2),
                    'status' => $t->status,
                    'isApproved' => $realisasiTerakhir?->is_approved ?? false,
                    'asal' => $t->asal,
                    'file_bukti' => $realisasiTerakhir?->file_bukti ?? null,
                ];
            });

        return view('admin.progress.index', compact('tugas'));
    }

    /**
     * Approve realisasi terakhir dari tugas
     */
    public function approve($id)
    {
        $tugas = Tugas::with('semuaRealisasi')->findOrFail($id);
        $realisasiTerakhir = $tugas->semuaRealisasi->last();

        if (!$realisasiTerakhir) {
            return redirect()->back()->with('error', 'Belum ada realisasi untuk disetujui.');
        }

        $realisasiTerakhir->update(['is_approved' => true]);

        $tugas->update(['status' => 'done']);

        return redirect()->back()->with('success', 'Realisasi berhasil disetujui.');
    }

    /**
     * Export data progress ke Excel
     */
    public function export(Request $request)
    {
        $teamIds = DB::table('pegawai_team')
            ->where('pegawai_id', auth()->user()->pegawai->id)
            ->pluck('team_id')
            ->toArray();

        $search = $request->input('search');

        $export = new class ($teamIds, $search) implements
        \Maatwebsite\Excel\Concerns\FromCollection,
        \Maatwebsite\Excel\Concerns\WithHeadings,
        \Maatwebsite\Excel\Concerns\ShouldAutoSize,
        \Maatwebsite\Excel\Concerns\WithStyles {
            protected $teamIds, $search;

            public function __construct($teamIds, $search)
            {
                $this->teamIds = $teamIds;
                $this->search = $search;
            }

            public function collection()
            {
                $tugas = Tugas::with(['pegawai.user', 'jenisPekerjaan.teams', 'semuaRealisasi'])
                    ->whereHas('pegawai', function ($q) {
                        $q->whereIn('id', function ($qq) {
                            $qq->select('pegawai_id')
                                ->from('pegawai_team')
                                ->whereIn('team_id', $this->teamIds);
                        });

                        if ($this->search) {
                            $q->whereHas('user', fn($u) => $u->where('name', 'like', "%{$this->search}%"));
                        }
                    })
                    ->whereHas('jenisPekerjaan.teams', function ($q) {
                        $q->whereIn('teams.id', $this->teamIds);
                    })
                    ->get()
                    ->map(function ($t, $index) {
                        $totalRealisasi = $t->semuaRealisasi->sum('realisasi');
                        $progress = $t->target > 0 ? min($totalRealisasi / $t->target, 1) : 0;

                        $bobot = $t->jenisPekerjaan->bobot ?? 0;

                        // keterlambatan
                        $realisasiSortir = $t->semuaRealisasi->sortBy('tanggal_realisasi');
                        $akumulasi = 0;
                        $tanggalCapai100 = null;
                        foreach ($realisasiSortir as $r) {
                            $akumulasi += $r->realisasi;
                            if ($akumulasi >= $t->target) {
                                $tanggalCapai100 = $r->tanggal_realisasi;
                                break;
                            }
                        }

                        $hariTelat = 0;
                        if ($tanggalCapai100) {
                            if (Carbon::parse($tanggalCapai100)->gt(Carbon::parse($t->deadline))) {
                                $hariTelat = Carbon::parse($t->deadline)
                                    ->diffInDays(Carbon::parse($tanggalCapai100));
                            }
                        } else {
                            if (Carbon::now()->gt(Carbon::parse($t->deadline))) {
                                $hariTelat = Carbon::parse($t->deadline)->diffInDays(Carbon::now());
                            }
                        }
                        $penalti = $bobot * 0.1 * $hariTelat;
                        $nilaiAkhir = max(0, ($bobot * $progress) - $penalti);

                        $namaTim = $t->jenisPekerjaan->teams
                            ->whereIn('id', values: $this->teamIds)
                            ->pluck('nama_tim')
                            ->implode(', ') ?: '-';

                        $fileBukti = $t->semuaRealisasi->last()?->file_bukti ?? null;
                        $fileBuktiUrl = $fileBukti ? asset('storage/' . $fileBukti) : '-';

                        return [
                            'No' => $index + 1,
                            'Nama Pegawai' => $t->pegawai->user->name ?? '-',
                            'Nama Tim' => $namaTim,
                            'Nama Tugas' => $t->jenisPekerjaan->nama_pekerjaan ?? '-',
                            'Target' => $t->target,
                            'Realisasi' => $totalRealisasi,
                            'Bobot' => $bobot,
                            'Hari Telat' => $hariTelat,
                            'Nilai Akhir' => round($nilaiAkhir, 2),
                            'Bukti' => $fileBuktiUrl,
                        ];
                    });

                return $tugas;
            }

            public function headings(): array
            {
                return [
                'No',
                'Nama Pegawai',
                'Nama Tim',
                'Nama Tugas',
                'Target',
                'Realisasi',
                'Bobot',
                'Hari Telat',
                'Nilai Akhir',
                'Bukti',
                ];
            }

            public function styles(Worksheet $sheet)
            {
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                $sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => 'center'],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                $sheet->getStyle('A2:' . $highestColumn . $highestRow)->applyFromArray([
                    'alignment' => ['horizontal' => 'left'],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                $sheet->getStyle('A2:A' . $highestRow)->applyFromArray([
                    'alignment' => ['horizontal' => 'center'],
                ]);

                return [];
            }
        };

        return Excel::download($export, 'progress.xlsx');
    }
}
