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
            ->where('pegawai_id', auth()->user()->pegawai_id)
            ->where('is_leader', 1)
            ->pluck('team_id')
            ->toArray();

        $search = $request->input('search');

        $tugas = Tugas::with(['pegawai.teams', 'jenisPekerjaan.team', 'semuaRealisasi'])
            ->whereHas('jenisPekerjaan', function ($q) use ($teamIds) {
                $q->whereIn('tim_id', $teamIds);
            })
            ->whereHas('pegawai', function ($q) use ($teamIds, $search) {
                $q->whereIn('id', function ($qq) use ($teamIds) {
                    $qq->select('pegawai_id')
                        ->from('pegawai_team')
                        ->whereIn('team_id', $teamIds);
                });

                if ($search) {
                    $q->where('nama', 'like', "%{$search}%");
                }
            })
            ->get()
            ->map(function ($t) {
                $totalRealisasi = $t->semuaRealisasi->sum('realisasi');
                $progress = $t->target > 0 ? min($totalRealisasi / $t->target, 1) : 0;

                $bobot = $t->jenisPekerjaan->bobot ?? 0;

                $lastDate = $t->semuaRealisasi->max('tanggal_realisasi');
                $hariTelat = 0;
                if ($lastDate && Carbon::parse($lastDate)->gt(Carbon::parse($t->deadline))) {
                    $hariTelat = Carbon::parse($lastDate)->diffInDays(Carbon::parse($t->deadline));
                }

                $penalti = $bobot * 0.1 * $hariTelat;
                $nilaiAkhir = max(0, ($bobot * $progress) - $penalti);

                $realisasiTerakhir = $t->semuaRealisasi->last();

                if (!$realisasiTerakhir) {
                    $status = 'Belum Dikerjakan';
                } elseif (!$realisasiTerakhir->is_approved) {
                    $status = 'Menunggu Persetujuan';
                } elseif ($totalRealisasi < $t->target) {
                    $status = 'Ongoing';
                } else {
                    $status = 'Selesai Dikerjakan';
                }

                $namaTim = $t->jenisPekerjaan->team->nama_tim ?? '-';

                return [
                    'id'               => $t->id,
                    'pegawai'          => $t->pegawai->nama ?? '-',
                    'tim'              => $namaTim,
                    'nama_tugas'       => $t->jenisPekerjaan->nama_pekerjaan ?? '-',
                    'target'           => $t->target,
                    'satuan'           => $t->satuan,
                    'totalRealisasi'   => $totalRealisasi,
                    'realisasiTerakhir' => $realisasiTerakhir->realisasi ?? null,
                    'histori'          => $t->semuaRealisasi,
                    'bobot'            => $bobot,
                    'hariTelat'        => $hariTelat,
                    'nilaiAkhir'       => round($nilaiAkhir, 2),
                    'status'           => $status,
                    'isApproved'       => $realisasiTerakhir?->is_approved ?? false,
                    'asal'             => $t->asal,
                    'file_bukti'       => $realisasiTerakhir?->file_bukti ?? null,
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

        $realisasiTerakhir->update([
            'is_approved' => true,
        ]);

        return redirect()->back()->with('success', 'Realisasi berhasil disetujui.');
    }

    /**
     * Export data progress ke Excel
     */
    public function export(Request $request)
    {
        $teamIds = DB::table('pegawai_team')
            ->where('pegawai_id', auth()->user()->pegawai_id)
            ->pluck('team_id')
            ->toArray();

        $search = $request->input('search');

        $export = new class($teamIds, $search) implements
            \Maatwebsite\Excel\Concerns\FromCollection,
            \Maatwebsite\Excel\Concerns\WithHeadings,
            \Maatwebsite\Excel\Concerns\ShouldAutoSize,
            \Maatwebsite\Excel\Concerns\WithStyles
        {
            protected $teamIds, $search;

            public function __construct($teamIds, $search)
            {
                $this->teamIds = $teamIds;
                $this->search  = $search;
            }

            public function collection()
            {
                $tugas = Tugas::with(['pegawai.teams', 'jenisPekerjaan.team', 'semuaRealisasi'])
                    ->whereHas('pegawai', function ($q) {
                        $q->whereIn('id', function ($qq) {
                            $qq->select('pegawai_id')
                                ->from('pegawai_team')
                                ->whereIn('team_id', $this->teamIds);
                        });

                        if ($this->search) {
                            $q->where('nama', 'like', "%{$this->search}%");
                        }
                    })
                    ->get()
                    ->map(function ($t, $index) {
                        $totalRealisasi = $t->semuaRealisasi->sum('realisasi');
                        $progress = $t->target > 0 ? min($totalRealisasi / $t->target, 1) : 0;

                        $bobot = $t->jenisPekerjaan->bobot ?? 0;

                        // keterlambatan
                        $realisasiSortir = $t->semuaRealisasi->sortBy('tanggal_realisasi');
                        $akumulasiCek = 0;
                        $tanggalSelesai = null;
                        foreach ($realisasiSortir as $r) {
                            $akumulasiCek += $r->realisasi;
                            if ($akumulasiCek >= $t->target) {
                                $tanggalSelesai = $r->tanggal_realisasi;
                                break;
                            }
                        }

                        // Sudah selesai tepat waktu = selesai sebelum atau tepat deadline
                        $selesaiTepat = $tanggalSelesai && !Carbon::parse($tanggalSelesai)->gt(Carbon::parse($t->deadline));

                        $hariTelat = 0;
                        $penalti = 0;

                        if (!$selesaiTepat) {
                            $realisasiTelat = $realisasiSortir->first(function ($r) use ($t) {
                                return Carbon::parse($r->tanggal_realisasi)->gt(Carbon::parse($t->deadline));
                            });

                            if ($realisasiTelat) {
                                $hariTelat = Carbon::parse($t->deadline)
                                    ->diffInDays(Carbon::parse($realisasiTelat->tanggal_realisasi));
                            } elseif ($totalRealisasi < $t->target) {
                                if (Carbon::now()->gt(Carbon::parse($t->deadline))) {
                                    $hariTelat = Carbon::parse($t->deadline)->diffInDays(Carbon::now());
                                }
                            }

                            // Penalti 5% per hari keterlambatan dikalikan bobot
                            $penalti = $bobot * 0.05 * $hariTelat;
                        }
                        $nilaiAkhir = max(0, ($bobot * $progress) - $penalti);

                        $namaTim = $t->jenisPekerjaan->team->nama_tim ?? '-';
                        $fileBukti = $t->semuaRealisasi->last()?->file_bukti ?? null;
                        $fileBuktiUrl = $fileBukti ? asset('storage/' . $fileBukti) : '-';

                        return [
                            'No'            => $index + 1,
                            'Nama Pegawai'  => $t->pegawai->nama ?? '-',
                            'Nama Tim'      => $namaTim,
                            'Nama Tugas'    => $t->jenisPekerjaan->nama_pekerjaan ?? '-',
                            'Target'        => $t->target,
                            'Realisasi'     => $totalRealisasi,
                            'Bobot'         => $bobot,  
                            'Hari Telat'    => $hariTelat,
                            'Nilai Akhir'   => round($nilaiAkhir, 2),
                            'Bukti'         => $fileBuktiUrl,
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
                $highestRow    = $sheet->getHighestRow();
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
