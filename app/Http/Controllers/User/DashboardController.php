<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Tugas;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Helpers\NilaiHelper;

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
        if ($search) {
            $query->whereHas('jenisPekerjaan', function ($q) use ($search) {
                $q->where('nama_pekerjaan', 'like', "%{$search}%");
            });
        }
        $year = $tahun ?? now()->year;

        if ($bulan) {
            $startDate = Carbon::create($year, $bulan, 1)->startOfMonth();
            $endDate   = Carbon::create($year, $bulan, 1)->endOfMonth();
        } elseif ($tahun) {
            $startDate = Carbon::create($year, 1, 1)->startOfYear();
            $endDate   = Carbon::create($year, 12, 31)->endOfYear();
        } else {
            $startDate = null;
            $endDate   = null;
        }

        if ($startDate && $endDate) {

            $query->whereDate('start_date', '<=', $endDate)
                ->whereDate('deadline', '>=', $startDate);
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

            $nilai = NilaiHelper::hitung($t);

            $totalRealisasi = $nilai['totalRealisasi'];
            $hariTelat = $nilai['hariTelat'];
            $nilaiAkhir = $nilai['nilaiAkhir'];

            $bobot = $t->jenisPekerjaan->bobot ?? 0;

            $status = $t->status ?? 'pending';

            $tglRef = $t->start_date ?? $t->created_at;
            $tanggal = Carbon::parse($tglRef)->format('d');
            $bulanNama = $namaBulan[(int) Carbon::parse($tglRef)->format('m')];

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
