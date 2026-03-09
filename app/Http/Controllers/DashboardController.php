<?php

namespace App\Http\Controllers;

use App\Models\Pekerjaan;
use App\Models\Progress;
use App\Models\NilaiAkhirUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function admin()
    {
        // **1. Total Project (menghitung jumlah proyek yang ada)**
        $totalProyek = Progress::count(); // Mengambil total proyek yang ada

        // **2. Total Tim (menghitung jumlah tim yang ada dalam progress)**
        $totalTim = Progress::with('user.tim')->distinct('user_id')->count(); // Menghitung jumlah tim berdasarkan user tim

        // **3. Produktivitas (jumlah bobot pekerjaan yang sudah diselesaikan)**
        $totalProduktivitas = Pekerjaan::sum('bobot'); // Menjumlahkan bobot pekerjaan

        // **4. Most Active (pegawai dengan jumlah kegiatan terbanyak)**
        $mostActive = Pekerjaan::select('user_id', 'users.nama', DB::raw('COUNT(*) as jumlah_kegiatan'))
            ->join('users', 'users.id', '=', 'pekerjaans.user_id')  // Melakukan join dengan tabel users
            ->groupBy('user_id', 'users.nama')  // Mengelompokkan berdasarkan user_id dan name
            ->orderByDesc('jumlah_kegiatan')  // Mengurutkan berdasarkan jumlah kegiatan terbanyak
            ->limit(1)  // Ambil yang paling aktif
            ->first();  // Ambil 1 user dengan kegiatan terbanyak

        // Passing data ke view
        return view('admin.dashboard', compact(
            'totalProyek', 'totalTim', 'totalProduktivitas', 'mostActive'
        ));
    }

    public function superadmin()
    {
        // **1. Total Project (menghitung jumlah proyek yang ada)**
        $totalProyek = Progress::count(); // Mengambil total proyek yang ada

        // **2. Total Tim (menghitung jumlah tim yang ada dalam progress)**
        $totalTim = Progress::with('user.tim')->distinct('user_id')->count(); // Menghitung jumlah tim berdasarkan user tim

        // **3. Produktivitas (jumlah bobot pekerjaan yang sudah diselesaikan)**
        $totalProduktivitas = Pekerjaan::sum('bobot'); // Menjumlahkan bobot pekerjaan

        // **4. Most Active (pegawai dengan jumlah kegiatan terbanyak)**
        $mostActive = Pekerjaan::select('user_id', 'users.nama', DB::raw('COUNT(*) as jumlah_kegiatan'))
            ->join('users', 'users.id', '=', 'pekerjaans.user_id')  // Melakukan join dengan tabel users
            ->groupBy('user_id', 'users.nama')  // Mengelompokkan berdasarkan user_id dan name
            ->orderByDesc('jumlah_kegiatan')  // Mengurutkan berdasarkan jumlah kegiatan terbanyak
            ->limit(1)  // Ambil yang paling aktif
            ->first();  // Ambil 1 user dengan kegiatan terbanyak

        // Passing data ke view
        return view('superadmin.dashboard', compact(
            'totalProyek', 'totalTim', 'totalProduktivitas', 'mostActive'
        ));
    }
}
