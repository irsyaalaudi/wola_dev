<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pegawai;
use App\Models\Team;
use App\Models\Tugas;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $bulan = $request->input('bulan');
        $tahun = $request->input('tahun');

        // ambil semua tim untuk header tabel
        $teams = Team::orderBy('nama_tim')->get();

        // ambil semua pegawai + relasi yang diperlukan
$search = trim((string) $request->input('search', ''));

$pegawais = Pegawai::with([
    'teams',
    'tugas' => function ($q) use ($bulan, $tahun) {
        if ($bulan) $q->whereMonth('created_at', $bulan);
        if ($tahun) $q->whereYear('created_at', $tahun);
    },
    'tugas.jenisPekerjaan',
    'tugas.semuaRealisasi'
])
->when($search, function ($q) use ($search) {
    $names = array_filter(array_map('trim', explode(',', $search)));
    $q->where(function ($sub) use ($names) {
        foreach ($names as $name) {
            $sub->orWhere('nama', 'like', "%$name%");
        }
    });
})
->when($bulan || $tahun, function ($q) use ($bulan, $tahun) {
    $q->whereHas('tugas', function ($q2) use ($bulan, $tahun) {
        if ($bulan) $q2->whereMonth('created_at', $bulan);
        if ($tahun) $q2->whereYear('created_at', $tahun);
    });
})
->get();

// kartu ringkasan
        $totalPegawai = Pegawai::count();

        $tugasQuery = Tugas::query();
        if ($bulan) $tugasQuery->whereMonth('created_at', $bulan);
        if ($tahun) $tugasQuery->whereYear('created_at', $tahun);

        $totalTugas = (clone $tugasQuery)->count();

        // ongoing = target belum tercapai
        $ongoing = (clone $tugasQuery)
            ->get()
            ->filter(fn($t) => $t->semuaRealisasi->sum('realisasi') < $t->target)
            ->count();

        // selesai = total realisasi >= target
        $selesai = (clone $tugasQuery)
            ->get()
            ->filter(fn($t) => $t->semuaRealisasi->sum('realisasi') >= $t->target)
            ->count();

        // nilai keseluruhan (persentase rata-rata)
        $allTugas = (clone $tugasQuery)->with('semuaRealisasi')->get();
        $nilaiKeseluruhan = 0;
        if ($allTugas->count() > 0) {
            $persenList = $allTugas->map(function ($t) {
                $totalRealisasi = $t->semuaRealisasi->sum('realisasi');
                return $t->target > 0 ? min($totalRealisasi / $t->target, 1) * 100 : 0;
            });
            $nilaiKeseluruhan = round($persenList->avg(), 2);
        }

        // mapping pegawai -> tim -> target & realisasi + score & grade
        $data = $pegawais->map(function ($pegawai) use ($teams, $bulan, $tahun) {
            $teamsData = $teams->map(function ($team) use ($pegawai, $bulan, $tahun) {
                $tugasQuery = $pegawai->tugas()->whereHas('jenisPekerjaan', function ($q) use ($team) {
                    $q->where('tim_id', $team->id);
                });

                if ($bulan) $tugasQuery->whereMonth('created_at', $bulan);
                if ($tahun) $tugasQuery->whereYear('created_at', $tahun);

                $tugasTim = $tugasQuery->with('semuaRealisasi')->get();
                $totalTarget = $tugasTim->sum('target');
                $totalRealisasi = $tugasTim
                    ->flatMap->semuaRealisasi
                    ->where('is_approved', true)
                    ->sum('realisasi');

                return [
                    'team_id'         => $team->id,
                    'nama_tim'        => $team->nama_tim ?? $team->nama ?? '—',
                    'total_target'    => $totalTarget,
                    'total_realisasi' => $totalRealisasi,
                ];
            });

            $grandTarget = $teamsData->sum('total_target');
            $grandRealisasi = $teamsData->sum('total_realisasi');

            // hitung score
            $score = $grandTarget > 0 ? round(($grandRealisasi / $grandTarget) * 100, 2) : 0;

            // tentukan grade
            if ($score >= 90) {
                $grade = 'SANGAT BAIK';
            } elseif ($score >= 80) {
                $grade = 'BAIK';
            } elseif ($score >= 70) {
                $grade = 'CUKUP';
            } elseif ($score >= 60) {
                $grade = 'SEDANG';
            } else {
                $grade = 'KURANG';
            }

            return [
                'pegawai'  => $pegawai,
                'teams'    => $teamsData,
                'score'    => $score,
                'grade'    => $grade,
                'grand_target' => $grandTarget,
                'grand_realisasi' => $grandRealisasi,
            ];
        });

        $tasks = Tugas::with(['pegawai', 'jenisPekerjaan', 'semuaRealisasi']);

if ($bulan) $tasks->whereMonth('created_at', $bulan);
if ($tahun) $tasks->whereYear('created_at', $tahun);

$tasks = $tasks->get();

        $pegawaiSummary = $pegawais->map(function ($pegawai) {

    $tugas = $pegawai->tugas;

    return [
        'nama' => $pegawai->nama,

        'tugas' => $tugas->map(function ($task) {
            return $task->jenisPekerjaan->nama_pekerjaan ?? '-';
        })->values()->toArray(),

        'realisasi_detail' => $tugas->map(function ($task) {

            $realisasi = $task->semuaRealisasi
                ->where('is_approved', true)
                ->sum('realisasi');

            return [
                'nama' => $task->jenisPekerjaan->nama_pekerjaan ?? '-',
                'realisasi' => $realisasi
            ];

        })->values()->toArray()
    ];
});

        // siapkan data untuk chart
        $chartLabels = $data->pluck('pegawai.nama')->toArray();
        $chartTarget = $data->pluck('grand_target')->toArray();
        $chartRealisasi = $data->pluck('grand_realisasi')->toArray();
        $chartTugas = $pegawaiSummary->pluck('tugas')->values()->toArray();
        $chartRealisasiDetail = $pegawaiSummary->pluck('realisasi_detail')->values()->toArray();

        return view('superadmin.dashboard', compact(
            'data',
            'teams',
            'totalPegawai',
            'totalTugas',
            'ongoing',
            'selesai',
            'nilaiKeseluruhan',
            'chartLabels',
            'chartTarget',
            'chartTugas',
            'chartRealisasiDetail',
            'chartRealisasi'
        ));
    }
    public function exportExcel(Request $request)
    {
        $bulan = $request->input('bulan');
        $tahun = $request->input('tahun');
        $search = trim((string) $request->input('search', ''));

        $teams = Team::orderBy('nama_tim')->get();

        $pegawais = Pegawai::with(['tugas.jenisPekerjaan', 'tugas.semuaRealisasi'])
            ->when($search, fn($q) => $q->where('nama', 'like', "%$search%"))
            ->get();

        // ---- Export dengan multi heading ----
        return \Maatwebsite\Excel\Facades\Excel::download(
            new class($pegawais, $teams, $bulan, $tahun) implements
                \Maatwebsite\Excel\Concerns\FromCollection,
                \Maatwebsite\Excel\Concerns\WithHeadings,
                \Maatwebsite\Excel\Concerns\WithMapping,
                \Maatwebsite\Excel\Concerns\WithStyles,
                \Maatwebsite\Excel\Concerns\WithEvents
            {
                private $pegawais, $teams, $bulan, $tahun;

                public function __construct($pegawais, $teams, $bulan, $tahun)
                {
                    $this->pegawais = $pegawais;
                    $this->teams = $teams;
                    $this->bulan = $bulan;
                    $this->tahun = $tahun;
                }

                public function collection()
                {
                    return $this->pegawais;
                }

                public function headings(): array
                {
                    // baris header pertama
                    $head1 = ['No', 'Nama Pegawai', 'Jabatan', 'Score (%)', 'Grade'];
                    foreach ($this->teams as $team) {
                        $head1[] = $team->nama_tim;
                        $head1[] = '';
                    }

                    // baris header kedua
                    $head2 = ['', '', '', '', ''];
                    foreach ($this->teams as $team) {
                        $head2[] = 'T';
                        $head2[] = 'R';
                    }

                    return [$head1, $head2];
                }

                public function map($pegawai): array
                {
                    $teamsData = $this->teams->map(function ($team) use ($pegawai) {
                        $tugasQuery = $pegawai->tugas()->whereHas('jenisPekerjaan', function ($q) use ($team) {
                            $q->where('tim_id', $team->id);
                        });

                        if ($this->bulan) $tugasQuery->whereMonth('created_at', $this->bulan);
                        if ($this->tahun) $tugasQuery->whereYear('created_at', $this->tahun);

                        $tugasTim = $tugasQuery->with('semuaRealisasi')->get();
                        $totalTarget = $tugasTim->sum('target');
                        $totalRealisasi = $tugasTim
                            ->flatMap->semuaRealisasi
                            ->where('is_approved', true)
                            ->sum('realisasi');

                        return ['target' => $totalTarget, 'realisasi' => $totalRealisasi];
                    });

                    $grandTarget = $teamsData->sum('target');
                    $grandRealisasi = $teamsData->sum('realisasi');
                    $score = $grandTarget > 0 ? round(($grandRealisasi / $grandTarget) * 100, 2) : 0;

                    if ($score >= 90) $grade = 'SANGAT BAIK';
                    elseif ($score >= 80) $grade = 'BAIK';
                    elseif ($score >= 70) $grade = 'CUKUP';
                    elseif ($score >= 60) $grade = 'SEDANG';
                    else $grade = 'KURANG';

                    $row = [
                        $pegawai->id,
                        $pegawai->nama,
                        $pegawai->jabatan,
                        $score . '%',
                        $grade,
                    ];

                    foreach ($teamsData as $teamData) {
                        $row[] = number_format($teamData['target'], 2);
                        $row[] = number_format($teamData['realisasi'], 2);
                    }

                    return $row;
                }

                // ✅ Tambah style
                public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
                {
                    $highestRow    = $sheet->getHighestRow();
                    $highestColumn = $sheet->getHighestColumn();

                    // Style header baris 1 & 2
                    $sheet->getStyle('A1:' . $highestColumn . '2')->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'color' => ['rgb' => '4F81BD'] // biru tua
                        ],
                    ]);

                    // Style isi tabel
                    $sheet->getStyle('A3:' . $highestColumn . $highestRow)->applyFromArray([
                        'alignment' => ['vertical' => 'center'],
                        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
                    ]);

                    // Kolom No rata tengah
                    $sheet->getStyle('A3:A' . $highestRow)->getAlignment()->setHorizontal('center');

                    // Kolom Score (%) & Grade rata tengah
                    $sheet->getStyle('D3:E' . $highestRow)->getAlignment()->setHorizontal('center');

                    // Tinggi header
                    $sheet->getRowDimension(1)->setRowHeight(25);
                    $sheet->getRowDimension(2)->setRowHeight(20);

                    return [];
                }

                public function registerEvents(): array
                {
                    return [
                        \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $event) {
                            $sheet = $event->sheet->getDelegate();

                            // Merge header nama tim
                            $colIndex = 6; // mulai kolom tim
                            foreach ($this->teams as $team) {
                                $colLetterStart = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                                $colLetterEnd   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);

                                $sheet->mergeCells("{$colLetterStart}1:{$colLetterEnd}1");
                                $colIndex += 2;
                            }

                            // Autosize semua kolom
                            $highestColumn = $sheet->getHighestColumn();
                            foreach (range('A', $highestColumn) as $col) {
                                $sheet->getColumnDimension($col)->setAutoSize(true);
                            }
                        },
                    ];
                }
            },
            'laporan_dashboard_superadmin.xlsx'
        );
    }
}
