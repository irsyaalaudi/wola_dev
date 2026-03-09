<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Tugas;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $pegawaiId = auth()->user()->pegawai?->id;

        // Ambil parameter filter
        $bulan = $request->input('bulan');
        $tahun = $request->input('tahun');
        $search = $request->input('search');

        // Query dasar
        $query = Tugas::with(['jenisPekerjaan.teams', 'semuaRealisasi'])
            ->where('pegawai_id', $pegawaiId)
            ->where(function ($q) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', now()->toDateString());
            });

        if ($bulan) {
            $query->whereMonth('created_at', $bulan);
        }
        if ($tahun) {
            $query->whereYear('created_at', $tahun);
        }

        if ($search) {
            $query->whereHas('jenisPekerjaan', function ($q) use ($search) {
                $q->where('nama_pekerjaan', 'like', "%{$search}%");
            });
        }

        $tugasSendiri = $query->get();

        $namaBulan = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
        ];

        $rincian = $tugasSendiri->map(function ($t) use ($namaBulan) {
            $totalRealisasi = $t->semuaRealisasi->sum('realisasi');
            $progress = $t->target > 0 ? min($totalRealisasi / $t->target, 1) : 0;
            $bobot = $t->jenisPekerjaan->bobot ?? 0;

            // Hari telat
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
                    $hariTelat = Carbon::parse($t->deadline)->diffInDays(Carbon::parse($tanggalCapai100));
                }
            } else {
                if (Carbon::now()->gt(Carbon::parse($t->deadline))) {
                    $hariTelat = Carbon::parse($t->deadline)->diffInDays(Carbon::now());
                }
            }

            // Penalti & nilai akhir
            $penalti = $bobot * 0.5 * $hariTelat;
            $nilaiAkhir = max(0, ($bobot * $progress) - $penalti);

            // Status tugas
            $status = $t->status ?? 'pending';

            $tglRef = $t->start_date ?? $t->created_at;
            $tanggal = Carbon::parse($tglRef)->format('d');
            $bulanNama = $namaBulan[(int)Carbon::parse($tglRef)->format('m')];

            // Nama tim pemberi tugas
            // $namaTim = $t->jenisPekerjaan->team->nama_tim ?? '-';

            $namaTim = $t->jenisPekerjaan->teams->first()->nama_tim ?? '-';

            return (object) [
                'tugas_id' => $t->id,
                'nama_pekerjaan' => $t->jenisPekerjaan->nama_pekerjaan ?? '-',
                'nama_tim' => $namaTim,
                'tanggal' => $tanggal,
                'bulan' => $bulanNama,
                'target' => $t->target,
                'realisasi' => $totalRealisasi,
                'bobot' => $bobot,
                'hariTelat' => $hariTelat,
                'nilaiAkhir' => round($nilaiAkhir, 2),
                'status' => $status,
            ];
        });

        $totalTugas = $tugasSendiri->count();
        $totalBobot = $tugasSendiri->sum(fn($t) => $t->jenisPekerjaan->bobot ?? 0);

        if ($bulan && $tahun) {
            $labelBulanTahun = strtoupper($namaBulan[(int) $bulan]) . ' ' . $tahun;
        } elseif ($bulan && !$tahun) {
            $labelBulanTahun = strtoupper($namaBulan[(int) $bulan]) . ' - Semua Tahun';
        } elseif (!$bulan && $tahun) {
            $labelBulanTahun = 'Semua Bulan - ' . $tahun;
        } else {
            $labelBulanTahun = 'Semua Bulan & Tahun';
        }

        return view('user.dashboard', [
            'totalTugas' => $totalTugas,
            'totalBobot' => $totalBobot,
            'rincian' => $rincian,
            'labelBulanTahun' => $labelBulanTahun,
        ]);
    }
}
