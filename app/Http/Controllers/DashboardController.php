<?php

namespace App\Http\Controllers;

use App\Models\Tugas;
use App\Models\JenisPekerjaan;
use App\Models\Progress;
use App\Models\NilaiAkhirUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // public function admin()
    // {
    //     // **1. Total Project (menghitung jumlah proyek yang ada)**
    //     $totalProyek = Progress::count(); // Mengambil total proyek yang ada

    //     // **2. Total Tim (menghitung jumlah tim yang ada dalam progress)**
    //     $totalTim = Progress::with('user.tim')->distinct('user_id')->count(); // Menghitung jumlah tim berdasarkan user tim

    //     // **3. Produktivitas (jumlah bobot pekerjaan yang sudah diselesaikan)**
    //     $totalProduktivitas = Pekerjaan::sum('bobot'); // Menjumlahkan bobot pekerjaan

    //     // **4. Most Active (pegawai dengan jumlah kegiatan terbanyak)**
    //     $mostActive = Pekerjaan::select('user_id', 'users.nama', DB::raw('COUNT(*) as jumlah_kegiatan'))
    //         ->join('users', 'users.id', '=', 'pekerjaans.user_id')  // Melakukan join dengan tabel users
    //         ->groupBy('user_id', 'users.nama')  // Mengelompokkan berdasarkan user_id dan name
    //         ->orderByDesc('jumlah_kegiatan')  // Mengurutkan berdasarkan jumlah kegiatan terbanyak
    //         ->limit(1)  // Ambil yang paling aktif
    //         ->first();  // Ambil 1 user dengan kegiatan terbanyak

    //     // Passing data ke view
    //     return view('admin.dashboard', compact(
    //         'totalProyek', 'totalTim', 'totalProduktivitas', 'mostActive'
    //     ));
    // }
    // public function admin(Request $request)
    // {
    //     $bulan = $request->input('bulan');
    //     $tahun = $request->input('tahun');
    //     $search = $request->input('search');

    //     $year = $tahun ?? now()->year;

    //     if ($bulan) {
    //         $startDate = Carbon::create($year, $bulan, 1)->startOfMonth();
    //         $endDate   = Carbon::create($year, $bulan, 1)->endOfMonth();
    //     } else {
    //         $startDate = Carbon::create($year, 1, 1)->startOfYear();
    //         $endDate   = Carbon::create($year, 12, 31)->endOfYear();
    //     }

    //     $queryPekerjaan = JenisPekerjaan::whereBetween('created_at', [$startDate, $endDate]);

    //     if ($search) {
    //         $queryPekerjaan->where('nama_pekerjaan', 'like', "%{$search}%");
    //     }

    //     // **1. Total Project**
    //     $totalProyek = Progress::whereBetween('created_at', [$startDate, $endDate])->count();

    //     // **2. Total Tim**
    //     $totalTim = Progress::whereBetween('created_at', [$startDate, $endDate])
    //         ->distinct('user_id')
    //         ->count('user_id');

    //     // **3. Produktivitas**
    //     $totalProduktivitas = JenisPekerjaan::whereBetween('created_at', [$startDate, $endDate])
    //         ->sum('bobot');

    //     // **4. Most Active**
    //     $mostActive = JenisPekerjaan::select('user_id', 'users.nama', DB::raw('COUNT(*) as jumlah_kegiatan'))
    //         ->join('users', 'users.id', '=', 'pekerjaans.user_id')
    //         ->whereBetween('pekerjaans.created_at', [$startDate, $endDate])
    //         ->groupBy('user_id', 'users.nama')
    //         ->orderByDesc('jumlah_kegiatan')
    //         ->first();

    //     return view('admin.dashboard', compact(
    //         'totalProyek',
    //         'totalTim',
    //         'totalProduktivitas',
    //         'mostActive',
    //         'bulan',
    //         'tahun',
    //         'search'
    //     ));
    // }
public function admin(Request $request)
{
    $bulan  = $request->bulan;
    $tahun  = $request->tahun;
    $search = $request->search;

    $year = $tahun ?? now()->year;

    if ($bulan) {
        $startDate = Carbon::create($year, $bulan, 1)->startOfMonth();
        $endDate   = Carbon::create($year, $bulan, 1)->endOfMonth();
    } else {
        $startDate = Carbon::create($year, 1, 1)->startOfYear();
        $endDate   = Carbon::create($year, 12, 31)->endOfYear();
    }

    /*
    ==============================
    QUERY TUGAS + JOIN PEKERJAAN
    ==============================
    */

    $query = Tugas::join('jenis_pekerjaans','tugas.jenis_pekerjaan_id','=','jenis_pekerjaans.id')
        ->join('pegawais','tugas.pegawai_id','=','pegawais.id')
        ->join('users','pegawais.user_id','=','users.id')
        ->whereBetween('tugas.created_at', [$startDate, $endDate]);

    if($search){
        $query->where('jenis_pekerjaans.nama_pekerjaan','like',"%$search%");
    }

    $tugas = $query->select(
        'tugas.*',
        'jenis_pekerjaans.nama_pekerjaan',
        'jenis_pekerjaans.bobot',
        'users.name as nama_pegawai'
    )->get();

    /*
    ==============================
    TOTAL PROJECT
    ==============================
    */

    $totalProyek = $tugas->count();

    /*
    ==============================
    TOTAL TIM (pegawai unik)
    ==============================
    */

    $totalTim = $tugas->pluck('pegawai_id')->unique()->count();

    /*
    ==============================
    PRODUKTIVITAS
    ==============================
    */

    $totalProduktivitas = $tugas->sum('bobot');

    /*
    ==============================
    MOST ACTIVE
    ==============================
    */

    $mostActive = $tugas
        ->groupBy('pegawai_id')
        ->map(function ($item) {
            return [
                'nama' => $item->first()->nama_pegawai,
                'jumlah_kegiatan' => $item->count()
            ];
        })
        ->sortByDesc('jumlah_kegiatan')
        ->first();

    return view('admin.dashboard', compact(
        'totalProyek',
        'totalTim',
        'totalProduktivitas',
        'mostActive',
        'search',
        'bulan',
        'tahun'
    ));
}

    // public function superadmin()
    // {
    //     // **1. Total Project (menghitung jumlah proyek yang ada)**
    //     $totalProyek = Progress::count(); // Mengambil total proyek yang ada

    //     // **2. Total Tim (menghitung jumlah tim yang ada dalam progress)**
    //     $totalTim = Progress::with('user.tim')->distinct('user_id')->count(); // Menghitung jumlah tim berdasarkan user tim

    //     // **3. Produktivitas (jumlah bobot pekerjaan yang sudah diselesaikan)**
    //     $totalProduktivitas = Pekerjaan::sum('bobot'); // Menjumlahkan bobot pekerjaan

    //     // **4. Most Active (pegawai dengan jumlah kegiatan terbanyak)**
    //     $mostActive = Pekerjaan::select('user_id', 'users.nama', DB::raw('COUNT(*) as jumlah_kegiatan'))
    //         ->join('users', 'users.id', '=', 'pekerjaans.user_id')  // Melakukan join dengan tabel users
    //         ->groupBy('user_id', 'users.nama')  // Mengelompokkan berdasarkan user_id dan name
    //         ->orderByDesc('jumlah_kegiatan')  // Mengurutkan berdasarkan jumlah kegiatan terbanyak
    //         ->limit(1)  // Ambil yang paling aktif
    //         ->first();  // Ambil 1 user dengan kegiatan terbanyak

    //     // Passing data ke view
    //     return view('superadmin.dashboard', compact(
    //         'totalProyek', 'totalTim', 'totalProduktivitas', 'mostActive'
    //     ));
    // }

    public function superadmin(Request $request)
    {
        $bulan = $request->input('bulan');
        $tahun = $request->input('tahun');

        $year = $tahun ?? now()->year;

        if ($bulan) {
            $startDate = Carbon::create($year, $bulan, 1)->startOfMonth();
            $endDate   = Carbon::create($year, $bulan, 1)->endOfMonth();
        } else {
            $startDate = Carbon::create($year, 1, 1)->startOfYear();
            $endDate   = Carbon::create($year, 12, 31)->endOfYear();
        }

        $totalProyek = Progress::whereBetween('created_at', [$startDate, $endDate])->count();

        $totalTim = Progress::whereBetween('created_at', [$startDate, $endDate])
            ->distinct('user_id')
            ->count('user_id');

        $totalProduktivitas = JenisPekerjaan::whereBetween('created_at', [$startDate, $endDate])
            ->sum('bobot');

        $mostActive = Tugas::select('pegawai_id', DB::raw('COUNT(*) as jumlah_kegiatan'))
            ->with('pegawai.user')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('pegawai_id')
            ->orderByDesc('jumlah_kegiatan')
            ->first();

        return view('superadmin.dashboard', compact(
            'totalProyek',
            'totalTim',
            'totalProduktivitas',
            'mostActive',
            'bulan',
            'tahun'
        ));
    }
}
