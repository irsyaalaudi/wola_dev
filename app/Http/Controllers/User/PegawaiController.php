<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Pegawai;
use Illuminate\Http\Request;


class PegawaiController extends Controller
{
    public function index(Request $request)
    {
        $teamId = auth()->user()->pegawai->team_id;

        // Mulai query untuk mendapatkan anggota tim
        $pegawaiQuery = Pegawai::where('team_id', $teamId);

        // Filter pencarian berdasarkan nama atau NIP
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $pegawaiQuery->where(function ($query) use ($search) {
                $query->where('nama', 'like', '%' . $search . '%')
                      ->orWhere('nip', 'like', '%' . $search . '%');
            });
        }

        // Eksekusi query untuk mendapatkan pegawai
        $pegawai = $pegawaiQuery->get();

        return view('user.pegawai.index', compact('pegawai'));
    }
}
