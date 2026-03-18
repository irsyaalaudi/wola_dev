<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Tugas;
use App\Models\Team;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ExportLaporanController extends Controller
{
    public function index(Request $request)
    {
        $pegawaiId = auth()->user()->pegawai->id;

        $query = Tugas::with(['jenisPekerjaan.teams', 'semuaRealisasi'])
            ->where('pegawai_id', $pegawaiId);

        // Filter jenis pekerjaan
        if ($request->filled('jenis_pekerjaan')) {
            $query->whereHas('jenisPekerjaan', function ($q) use ($request) {
                $q->where('nama_pekerjaan', 'like', '%' . $request->jenis_pekerjaan . '%');
            });
        }

        // Filter tim
        if ($request->filled('tim')) {
            $query->whereHas('jenisPekerjaan.teams', function ($q) use ($request) {
                $q->where('id', $request->tim);
            });
        }


        // =========================
        // FILTER BULAN (HANYA JIKA TIDAK ADA TANGGAL)
        // =========================
        if (!$request->filled('start_date') && !$request->filled('end_date')) {

            if ($request->filled('bulan') && $request->filled('tahun')) {

                $awalBulan = Carbon::create($request->tahun, $request->bulan, 1)->startOfMonth();
                $akhirBulan = Carbon::create($request->tahun, $request->bulan, 1)->endOfMonth();

                $query->where(function ($q) use ($awalBulan, $akhirBulan) {
                    $q->whereDate('start_date', '<=', $akhirBulan)
                        ->whereDate('deadline', '>=', $awalBulan);
                });
            }
        }

        // Filter tanggal
        // =========================
        // FILTER TANGGAL (FIX OVERLAP)
        // =========================
        if ($request->filled('start_date') && $request->filled('end_date')) {

            $query->where(function ($q) use ($request) {
                $q->whereDate('start_date', '<=', $request->end_date)
                    ->whereDate('deadline', '>=', $request->start_date);
            });
        } elseif ($request->filled('start_date')) {

            $query->whereDate('deadline', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {

            $query->whereDate('start_date', '<=', $request->end_date);
        }

        $tugas = $query->get();

        $timList = Team::whereHas('pegawais', function ($q) use ($pegawaiId) {
            $q->where('pegawai_id', $pegawaiId);
        })->get();

        return view('user.export_laporan.index', compact('tugas', 'timList'));
    }
}
