<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Tugas;
use App\Models\RealisasiTugas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Exports\TugasUserExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Helpers\NilaiHelper;

class PekerjaanController extends Controller
{
    public function index(Request $request)
    {
        $pegawaiId = auth()->user()->pegawai->id;

        $tugasQuery = Tugas::with(['semuaRealisasi', 'jenisPekerjaan'])
            ->where('pegawai_id', $pegawaiId)
            ->where(function ($q) {
                $q->whereNull('start_date')
                ->orWhere('start_date', '<=', now()->toDateString());
            });

        // Filter nama pekerjaan
        if ($request->filled('search')) {
            $tugasQuery->whereHas('jenisPekerjaan', function ($q) use ($request) {
                $q->where('nama_pekerjaan', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('jenis_pekerjaan')) {
            $tugasQuery->whereHas('jenisPekerjaan', function ($q) use ($request) {
                $q->where('nama_pekerjaan', 'like', '%' . $request->jenis_pekerjaan . '%');
            });
        }

        if ($request->filled('tim')) {
            $tugasQuery->whereHas('jenisPekerjaan.teams', function ($q) use ($request) {
                $q->where('id', $request->tim);
            });
        }

        // Filter bulan
        if ($request->filled('bulan')) {
            $tugasQuery->whereMonth('deadline', $request->bulan);
        }

        // Filter Tahun
        if ($request->filled('tahun')) {
            $tugasQuery->whereYear('deadline', $request->tahun);
        }

        // Filter Waktu
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $tugasQuery->whereBetween('deadline', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $tugasQuery->where('deadline', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $tugasQuery->where('deadline', '<=', $request->end_date);
        }

        // Filter Tim
        if ($request->filled('tim')) {
            $tugasQuery->whereHas('jenisPekerjaan.teams', function ($q) use ($request) {
                $q->where('id', $request->tim);
            });
        }

        $tugas = $tugasQuery->get();

        $timList = \App\Models\Team::whereHas('pegawais', function ($q) use ($pegawaiId) {
            $q->where('pegawai_id', $pegawaiId);
        })->get();

        foreach ($tugas as $t) {
            // total realisasi & progress
            $totalRealisasi = $t->semuaRealisasi->sum('realisasi');
            $progress = $t->target > 0 ? min($totalRealisasi / $t->target, 1) : 0;

            // bobot dari jenis pekerjaan
            $bobot = $t->jenisPekerjaan->bobot ?? 0;

            // Cek keterlambatan
            $realisasiSortir = $t->semuaRealisasi->sortBy('tanggal_realisasi');

            // Cari tanggal saat realisasi pertama kali mencapai 100%
            $akumulasi = 0;
            $tanggalCapai100 = null;
            foreach ($realisasiSortir as $r) {
                $akumulasi += $r->realisasi;
                if ($akumulasi >= $t->target) {
                    $tanggalCapai100 = $r->tanggal_realisasi;
                    break; // berhenti di sini, input setelah 100% diabaikan
                }
            }

            $hariTelat = 0;
            $penalti = 0;

            if ($tanggalCapai100) {
                // sudah 100%, cek apakah terlambat
                if (Carbon::parse($tanggalCapai100)->gt(Carbon::parse($t->deadline))) {
                    $hariTelat = Carbon::parse($t->deadline)
                        ->diffInDays(Carbon::parse($tanggalCapai100));
                }
            } else {
                // belum 100%, cek apakah sudah lewat deadline
                if (Carbon::now()->gt(Carbon::parse($t->deadline))) {
                    $hariTelat = Carbon::parse($t->deadline)->diffInDays(Carbon::now());
                }
            }

            $nilai = NilaiHelper::hitung($t);

            $t->setAttribute('bobot_asli', $nilai['bobot']);
            $t->setAttribute('penalti', $nilai['penalti']);
            $t->setAttribute('nilai_akhir', $nilai['nilaiAkhir']);

            $t->setAttribute(
                'is_late',
                $hariTelat > 0
            );

            // rincian histori realisasi
            $akumulasi = 0;
            $rincian = $t->semuaRealisasi->sortBy('tanggal_realisasi')->map(function ($r) use ($t, &$akumulasi) {
                $akumulasi += $r->realisasi;
                $persen = $t->target > 0 ? round(($akumulasi / $t->target) * 100, 2) : 0;

                return [
                    'id' => $r->id,
                    'tanggal_input' => $r->created_at->format('d-m-Y H:i'),
                    'tanggal_realisasi' => Carbon::parse($r->tanggal_realisasi)->format('d-m-Y'),
                    'jumlah' => $r->realisasi,
                    'catatan' => $r->catatan,
                    'akumulasi' => $akumulasi,
                    'persen' => $persen,
                    'file_bukti' => $r->file_bukti,
                ];
            });
            $t->setAttribute('rincian', $rincian);
        }

        return view('user.pekerjaan.index', compact('tugas', 'timList'));
    }

    public function storeRealisasi(Request $request, $tugas_id)
    {
        $pegawaiId = auth()->user()->pegawai->id;

        $tugas = Tugas::where('id', $tugas_id)
            ->where('pegawai_id', $pegawaiId)
            ->firstOrFail();

        $tanggalAwal = $tugas->start_date ?? $tugas->created_at->toDateString();

        $validated = $request->validate([
            'realisasi' => 'required|numeric|min:1',
            'tanggal_realisasi' => "required|date|after_or_equal:$tanggalAwal",
            'catatan' => 'nullable|string',
            'file_bukti' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        // upload file bukti
        if ($request->hasFile('file_bukti')) {
            $validated['file_bukti'] = $request->file('file_bukti')->store('bukti', 'public');
        }

        $validated['tugas_id'] = $tugas->id;

        RealisasiTugas::create($validated);
        $totalRealisasi = $tugas->semuaRealisasi()->sum('realisasi');
        $tugas->update([
            'status' => $totalRealisasi >= $tugas->target ? 'waiting_approval' : 'on_progress'
        ]);

        return back()
            ->with('success', 'Realisasi berhasil disimpan.')
            ->with('scroll_to', $tugas_id);

    }

    public function updateRealisasi(Request $request, $id)
    {
        $pegawaiId = auth()->user()->pegawai->id;

        $realisasi = RealisasiTugas::where('id', $id)
            ->whereHas('tugas', fn($q) => $q->where('pegawai_id', $pegawaiId))
            ->firstOrFail();

        $tugas = $realisasi->tugas;
        $tanggalAwal = $tugas->start_date ?? $tugas->created_at->toDateString();

        $validated = $request->validate([
            'realisasi' => 'required|numeric|min:1',
            'tanggal_realisasi' => "required|date|after_or_equal:$tanggalAwal",
            'catatan' => 'nullable|string',
            'file_bukti' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        // update file bukti
        if ($request->hasFile('file_bukti')) {
            if ($realisasi->file_bukti) {
                Storage::disk('public')->delete($realisasi->file_bukti);
            }
            $validated['file_bukti'] = $request->file('file_bukti')->store('bukti', 'public');
        } else {
            $validated['file_bukti'] = $realisasi->file_bukti;
        }

        $realisasi->update($validated);
        $totalRealisasi = $tugas->semuaRealisasi()->sum('realisasi'); 
        $tugas->update([
            'status' => $totalRealisasi >= $tugas->target ? 'waiting_approval' : 'on_progress'
        ]);

        return back()->with('success', 'Realisasi berhasil diupdate.');
    }


    public function export(Request $request)
    {
        $query = Tugas::with(['jenisPekerjaan.teams', 'semuaRealisasi'])
            ->where('pegawai_id', auth()->user()->pegawai->id);

        if ($request->bulan) {
            $query->whereMonth('created_at', $request->bulan);
        }

        if ($request->tahun) {
            $query->whereYear('created_at', $request->tahun);
        }

        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->jenis_pekerjaan) {
            $query->whereHas('jenisPekerjaan', function ($q) use ($request) {
                $q->where('nama_pekerjaan', 'like', '%'.$request->jenis_pekerjaan.'%');
            });
        }

        $tugas = $query->get();

        return Excel::download(
            new \App\Exports\TugasUserExport($tugas),
            'laporan_tugas_user.xlsx'
        );
    }
}
