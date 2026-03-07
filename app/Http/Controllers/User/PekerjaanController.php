<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Tugas;
use App\Models\RealisasiTugas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class PekerjaanController extends Controller
{
    public function index(Request $request)
    {
        $pegawaiId = auth()->user()->pegawai?->id;

        $tugasQuery = Tugas::with(['semuaRealisasi', 'jenisPekerjaan'])
            ->where('pegawai_id', $pegawaiId);

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

        $tugas = $tugasQuery->get();

        foreach ($tugas as $t) {
            // total realisasi & progress
            $totalRealisasi = $t->semuaRealisasi->sum('realisasi');
            $progress = $t->target > 0 ? ($totalRealisasi / $t->target) : 0;

            // bobot dari jenis pekerjaan
            $bobot = $t->jenisPekerjaan->bobot ?? 0;

            // keterlambatan
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

            // Sudah selesai tepat waktu = selesai sebelum atau tepat deadline
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

            // nilai akhir (bobot * progress – penalti)
            $nilaiAkhir = max(0, ($bobot * $progress) - $penalti);

            // atribut untuk Blade
            $t->setAttribute('bobot_asli', $bobot);
            $t->setAttribute('penalti', $penalti);
            $t->setAttribute('nilai_akhir', $nilaiAkhir);

            $t->setAttribute(
                'is_late',
                !$selesaiTepat && $hariTelat > 0
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

        return view('user.pekerjaan.index', compact('tugas'));
    }

    public function storeRealisasi(Request $request, $tugas_id)
    {
        $pegawaiId = auth()->user()->pegawai?->id;

        $tugas = Tugas::where('id', $tugas_id)
            ->where('pegawai_id', $pegawaiId)
            ->firstOrFail();

        $tanggalAwal = $tugas->created_at->toDateString();

        $validated = $request->validate([
            'realisasi' => 'required|numeric|min:1',
            'tanggal_realisasi' => "required|date|after_or_equal:$tanggalAwal",
            'catatan' => 'nullable|string',
            'file_bukti' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        // sisa target
        // $currentTotal = $tugas->semuaRealisasi()->sum('realisasi');
        // $sisa = $tugas->target - $currentTotal;
        // if ($validated['realisasi'] > $sisa) {
        //     return back()->withErrors(['realisasi' => "Maksimal realisasi yang bisa dimasukkan hanya $sisa"]);
        // }

        // upload file bukti
        if ($request->hasFile('file_bukti')) {
            $validated['file_bukti'] = $request->file('file_bukti')->store('bukti', 'public');
        }

        $validated['tugas_id'] = $tugas->id;

        RealisasiTugas::create($validated);

        return back()->with('success', 'Realisasi berhasil disimpan.');
    }

    public function updateRealisasi(Request $request, $id)
    {
        $pegawaiId = auth()->user()->pegawai?->id;

        $realisasi = RealisasiTugas::where('id', $id)
            ->whereHas('tugas', fn($q) => $q->where('pegawai_id', $pegawaiId))
            ->firstOrFail();

        $tugas = $realisasi->tugas;
        $tanggalAwal = $tugas->created_at->toDateString();

        $validated = $request->validate([
            'realisasi' => 'required|numeric|min:1',
            'tanggal_realisasi' => "required|date|after_or_equal:$tanggalAwal",
            'catatan' => 'nullable|string',
            'file_bukti' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        // sisa target (selain yang sedang diedit)
        // $otherTotal = $tugas->semuaRealisasi()->where('id', '<>', $realisasi->id)->sum('realisasi');
        // $sisa = $tugas->target - $otherTotal;
        // if ($validated['realisasi'] > $sisa) {
        //     return back()->withErrors(['realisasi' => "Maksimal realisasi yang bisa dimasukkan hanya $sisa"]);
        // }

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

        return back()->with('success', 'Realisasi berhasil diupdate.');
    }
}
