<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pegawai;
use App\Models\Tugas;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DashboardController extends Controller
{
    public function index(Request $request)
{
    $userPegawai = auth()->user()->pegawai;
    if (!$userPegawai) {
        abort(403, 'Anda tidak memiliki data pegawai.');
    }

    $teamIds = $userPegawai->teams->pluck('id')->toArray();
    $bulan   = $request->input('bulan');
    $tahun   = $request->input('tahun');
    $search  = trim((string) $request->input('search', ''));

    $namaBulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
        4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September',
        10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    $labelBulanTahun = match (true) {
        $bulan && $tahun   => strtoupper($namaBulan[(int)$bulan]) . " $tahun",
        $bulan && !$tahun  => strtoupper($namaBulan[(int)$bulan]) . " - Semua Tahun",
        !$bulan && $tahun  => "Semua Bulan - $tahun",
        default            => 'Semua Bulan & Tahun'
    };

    // =====================
    // AMBIL MEMBER TIM
    // =====================
    $members = Pegawai::whereHas('teams', fn($q) =>
        $q->whereIn('teams.id', $teamIds)
    )->get();

    $memberIds = $members->pluck('id')->toArray();

    // =====================
    // QUERY TUGAS
    // =====================
    $tasksQuery = Tugas::with(['pegawai.teams', 'jenisPekerjaan.team', 'semuaRealisasi'])
        ->whereIn('pegawai_id', $memberIds)
        ->where('asal', $userPegawai->nama)
        ->when($bulan, fn($q) => $q->whereMonth('created_at', $bulan))
        ->when($tahun, fn($q) => $q->whereYear('created_at', $tahun));

    if ($search !== '') {
        $keywords = preg_split('/[\s,]+/', $search, -1, PREG_SPLIT_NO_EMPTY);
        $tasksQuery->where(function ($q) use ($keywords) {
            foreach ($keywords as $word) {
                $q->orWhereHas('pegawai', fn($qq) =>
                    $qq->where('nama', 'like', "%$word%"))
                  ->orWhereHas('jenisPekerjaan', fn($qq) =>
                    $qq->where('nama_pekerjaan', 'like', "%$word%"));
            }
        });
    }

    $tasks = $tasksQuery->get();

    // =====================
    // HITUNG NILAI & STATUS
    // =====================
    $tasks->transform(fn($t) => $this->calculateTask($t));

    // =====================
    // STATISTIK
    // =====================
    $totalTugas     = $tasks->count();
    $tugasSelesai   = $tasks->where('status', 'Selesai Dikerjakan')->count();
    $tugasOngoing   = $tasks->where('status', 'Ongoing')->count();
    $tugasBelum     = $tasks->where('status', 'Belum Dikerjakan')->count();
    $rataNilaiAkhir = $totalTugas > 0 ? round($tasks->avg('nilaiAkhir'), 2) : 0;

    // =====================
    // GRAFIK PER TUGAS
    // =====================
    $grafikLabels    = $tasks->pluck('jenisPekerjaan.nama_pekerjaan')->toArray();
    $grafikTarget    = $tasks->pluck('totalTarget')->toArray();
    $grafikRealisasi = $tasks->pluck('totalRealisasi')->toArray();

    $pegawaiSummary = $tasks->groupBy('pegawai_id')->map(function ($items) {

    return [
        'nama' => $items->first()->pegawai->nama ?? '-',

        'target' => $items->sum('totalTarget'),

        'realisasi' => $items->sum('totalRealisasi'),

        // daftar tugas
        'tugas' => $items->map(function ($task) {
            return $task->jenisPekerjaan->nama_pekerjaan ?? '-';
        })->values()->toArray(),

        // tugas + realisasi
        'realisasi_detail' => $items->map(function ($task) {
            return [
                'nama' => $task->jenisPekerjaan->nama_pekerjaan ?? '-',
                'realisasi' => $task->totalRealisasi ?? 0
            ];
        })->values()->toArray()
    ];
});

    $grafikPegawaiLabels    = $pegawaiSummary->pluck('nama')->values()->toArray();
    $grafikPegawaiTarget    = $pegawaiSummary->pluck('target')->values()->toArray();
    $grafikPegawaiRealisasi = $pegawaiSummary->pluck('realisasi')->values()->toArray();
    $grafikPegawaiTugas = $pegawaiSummary->pluck('tugas')->values()->toArray();
    $grafikPegawaiRealisasiDetail = $pegawaiSummary->pluck('realisasi_detail')->values()->toArray();

    return view('admin.dashboard', compact(
        'members',
        'tasks',
        'totalTugas',
        'tugasSelesai',
        'tugasOngoing',
        'tugasBelum',
        'rataNilaiAkhir',
        'grafikLabels',
        'grafikTarget',
        'grafikRealisasi',
        'grafikPegawaiLabels',
        'grafikPegawaiTarget',
        'grafikPegawaiRealisasi',
        'grafikPegawaiTugas',
        'grafikPegawaiRealisasiDetail',
        'labelBulanTahun'
    ));
}

    public function exportExcel(Request $request)
    {
        $bulan  = $request->input('bulan');
        $tahun  = $request->input('tahun');
        $search = trim((string) $request->input('search', ''));

        $userPegawai = auth()->user()->pegawai;
        if (!$userPegawai) {
            abort(403, 'Anda tidak memiliki data pegawai.');
        }

        $teamIds   = $userPegawai->teams->pluck('id')->toArray();
        $memberIds = Pegawai::whereHas('teams', fn($q) => $q->whereIn('teams.id', $teamIds))
            ->pluck('id')->toArray();

        $tasks = Tugas::with(['pegawai.teams', 'jenisPekerjaan.team', 'semuaRealisasi'])
            ->whereIn('pegawai_id', $memberIds)
            ->where('asal', $userPegawai->nama);

        if ($bulan) $tasks->whereMonth('created_at', $bulan);
        if ($tahun) $tasks->whereYear('created_at', $tahun);

        if ($search !== '') {
            $keywords = preg_split('/[\s,]+/', $search, -1, PREG_SPLIT_NO_EMPTY);
            $tasks->where(function ($q) use ($keywords) {
                foreach ($keywords as $word) {
                    $q->orWhereHas('pegawai', fn($qq) => $qq->where('nama', 'like', "%$word%"))
                        ->orWhereHas('jenisPekerjaan', fn($qq) => $qq->where('nama_pekerjaan', 'like', "%$word%"));
                }
            });
        }

        $tasks = $tasks->get();
        $tasks->transform(fn($t) => $this->calculateTask($t));

        // Data untuk Excel
        $rows = $tasks->map(function ($t, $i) {
            return [
                'No'                => $i + 1,
                'Nama Pegawai'      => $t->pegawai->nama ?? '-',
                'Nama Tim'          => $t->namaTim,
                'Tugas'             => $t->jenisPekerjaan->nama_pekerjaan ?? '-',
                'Target'            => $t->target,
                'Realisasi'         => $t->totalRealisasi,
                'Histori Perubahan' => $t->semuaRealisasi->map(function ($r) {
                    $tgl = $r->tanggal_realisasi ? Carbon::parse($r->tanggal_realisasi)->format('d M Y') : '-';
                    $status = $r->is_approved ? '' : ' (Menunggu Approve)';
                    return "$tgl: {$r->realisasi}$status";
                })->implode('; '),
                'Bobot'             => $t->bobot,
                'Hari Telat'        => $t->hariTelat,
                'Nilai Akhir'       => $t->nilaiAkhir,
                'Status'            => $t->status,
            ];
        });

        return Excel::download(
            new class($rows) implements
                \Maatwebsite\Excel\Concerns\FromCollection,
                \Maatwebsite\Excel\Concerns\WithHeadings,
                \Maatwebsite\Excel\Concerns\WithStyles,
                \Maatwebsite\Excel\Concerns\WithColumnWidths,
                \Maatwebsite\Excel\Concerns\WithEvents
            {
                private $rows;
                public function __construct($rows)
                {
                    $this->rows = $rows;
                }

                public function collection()
                {
                    return new \Illuminate\Support\Collection($this->rows);
                }

                public function headings(): array
                {
                    return array_keys($this->rows->first() ?? []);
                }

                // Styling langsung
                public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
                {
                    $highestRow    = $sheet->getHighestRow();
                    $highestColumn = $sheet->getHighestColumn();

                    // Header
                    $sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray([
                        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill'      => [
                            'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => '4F81BD']
                        ],
                        'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                        'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
                    ]);

                    // Isi tabel
                    $sheet->getStyle('A2:' . $highestColumn . $highestRow)->applyFromArray([
                        'alignment' => ['vertical' => 'center'],
                        'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
                    ]);

                    // Kolom No rata tengah
                    $sheet->getStyle('A2:A' . $highestRow)->getAlignment()->setHorizontal('center');

                    return [];
                }

                // Lebar kolom
                public function columnWidths(): array
                {
                    return [
                        'A' => 5,   // No
                        'B' => 30,  // Nama Pegawai
                        'C' => 20,  // Nama Tim
                        'D' => 60,  // Tugas
                        'E' => 10,  // Target
                        'F' => 12,  // Realisasi
                        'G' => 40,  // Histori Perubahan
                        'H' => 10,  // Bobot
                        'I' => 12,  // Hari Telat
                        'J' => 15,  // Nilai Akhir
                    ];
                }

                // Auto filter biar rapi
                public function registerEvents(): array
                {
                    return [
                        \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $event) {
                            $sheet = $event->sheet->getDelegate();
                            $highestColumn = $sheet->getHighestColumn();
                            $highestRow    = $sheet->getHighestRow();

                            // Aktifkan filter pada header
                            $sheet->setAutoFilter("A1:{$highestColumn}1");

                            // Auto height rows
                            foreach (range(1, $highestRow) as $row) {
                                $sheet->getRowDimension($row)->setRowHeight(-1);
                            }

                            // Wrap text di kolom panjang
                            $sheet->getStyle("D1:D{$highestRow}")->getAlignment()->setWrapText(true);
                            $sheet->getStyle("G1:G{$highestRow}")->getAlignment()->setWrapText(true);
                        },
                    ];
                }
            },
            'laporan_dashboard_admin.xlsx'
        );
    }

    /**
     * Perhitungan tugas (agar konsisten untuk index & export)
     */
    private function calculateTask($t)
    {
        $approved = $t->semuaRealisasi->where('is_approved', true);
        $totalRealisasi = $approved->sum('realisasi');
        $progress = $t->target > 0 ? min($totalRealisasi / $t->target, 1) : 0;
        $bobot = $t->jenisPekerjaan->bobot ?? 0;

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

        $status = match (true) {
            $approved->isEmpty() => ($t->semuaRealisasi->isNotEmpty() ? 'Menunggu Persetujuan' : 'Belum Dikerjakan'),
            $totalRealisasi < $t->target => 'Ongoing',
            default => 'Selesai Dikerjakan'
        };

        $t->namaTim        = $t->jenisPekerjaan->team->nama_tim ?? '-';
        $t->bobot          = $bobot;
        $t->hariTelat      = $hariTelat;
        $t->nilaiAkhir     = round($nilaiAkhir, 2);
        $t->status         = $status;
        $t->totalTarget    = $t->target ?? 0;
        $t->totalRealisasi = $totalRealisasi ?? 0;

        return $t;
    }
}
