<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Tugas;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TemplateTugasExport;

class PekerjaanController extends Controller
{
    public function index()
    {
        $query = Tugas::query();

        // 🔎 Search nama_pekerjaan
        if ($search = request('search')) {
            $query->whereHas('jenisPekerjaan', function ($q) use ($search) {
                $q->where('nama_pekerjaan', 'like', '%' . $search . '%');
            });
        }

        // Filter deadline
        if ($deadlineMonth = request('deadline_month')) {
            $query->whereMonth('deadline', $deadlineMonth);
        }
        if ($deadlineYear = request('deadline_year')) {
            $query->whereYear('deadline', $deadlineYear);
        }

        // Filter realisasi yang approved
        if (request('realisasi_month') || request('realisasi_year')) {
            $query->whereHas('semuaRealisasi', function ($q) {
                $q->where('is_approved', true);
                if ($bulan = request('realisasi_month')) {
                    $q->whereMonth('tanggal_realisasi', $bulan);
                }
                if ($tahun = request('realisasi_year')) {
                    $q->whereYear('tanggal_realisasi', $tahun);
                }
            });
        }

        // Sorting
        if ($sortBy = request('sort_by')) {
            $sortOrder = request('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);
        }

        // Ambil semua untuk statistik
        $allTugas = $query->with([
            'jenisPekerjaan.teams',
            'semuaRealisasi',
            'pegawai.user',
            'pegawai.teams'
        ])->get();

        // Ambil untuk tabel (realisasi approved saja)
        $tugas = $query->with([
            'jenisPekerjaan.teams',
            'semuaRealisasi' => function ($q) {
                $q->where('is_approved', true);
            },
            'pegawai.teams'
        ])->paginate(10)->withQueryString();

        // Tambahkan bobot, keterlambatan, nilai akhir, status, dan nama tim pemberi tugas
        $tugas->getCollection()->transform(function ($t) {
            // Ambil realisasi yang sudah approved
            $approvedRealisasi = $t->semuaRealisasi->where('is_approved', true);

            $totalRealisasi = $approvedRealisasi->sum('realisasi');
            $progress = $t->target > 0 ? min($totalRealisasi / $t->target, 1) : 0;

            // Bobot dari jenis pekerjaan
            $bobot = $t->jenisPekerjaan->bobot ?? 0;

            // Keterlambatan (dari realisasi terakhir yang approved)
            $lastDate = $approvedRealisasi->max('tanggal_realisasi');
            $hariTelat = 0;
            if ($lastDate && Carbon::parse($lastDate)->gt(Carbon::parse($t->deadline))) {
                $hariTelat = Carbon::parse($lastDate)->diffInDays(Carbon::parse($t->deadline));
            }

            // Penalti 10% per hari keterlambatan
            $penalti = $bobot * 0.1 * $hariTelat;

            // Nilai akhir
            $nilaiAkhir = max(0, ($bobot * $progress) - $penalti);

            // 🔹 Nama tim pemberi tugas (bukan semua tim pegawai)
            $namaTim = $t->jenisPekerjaan->team->nama_tim ?? '-';

            // Tambahkan properti baru ke model
            $t->bobot       = $bobot;
            $t->hariTelat   = $hariTelat;
            $t->nilaiAkhir  = round($nilaiAkhir, 2);
            $t->namaTim     = $namaTim;

            return $t;
        });

        $totalTugas   = $allTugas->count();
        $tugasSelesai        = (clone $query)->where('status', 'done')->count();
        $tugasOngoing        = (clone $query)->where('status', 'on_progress')->count();
        $tugasBelum          = (clone $query)->where('status', 'pending')->count();
        $tugasWaitingApproval = (clone $query)->where('status', 'waiting_approval')->count();

        $persentaseSelesai = $totalTugas ? round(($tugasSelesai / $totalTugas) * 100, 2) : 0;

        return view('superadmin.pekerjaan.index', compact(
            'tugas',
            'totalTugas',
            'tugasSelesai',
            'tugasWaitingApproval',
            'tugasOngoing',
            'tugasBelum',
            'persentaseSelesai'
        ));
    }
        public function downloadTemplate()
{
    return Excel::download(
        new TemplateTugasExport(auth()->user()),
        'Template_Tugas.xlsx'
    );
}
}
