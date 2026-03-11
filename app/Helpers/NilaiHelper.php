<?php

namespace App\Helpers;

use Carbon\Carbon;

class NilaiHelper
{
    public static function hitung($tugas)
    {
        // hanya hitung realisasi yang sudah approved
        $approved = $tugas->semuaRealisasi->where('is_approved', true);

        $totalRealisasi = $approved->sum('realisasi');

        $target = $tugas->target ?? 0;

        // progress
        $progress = $target > 0 ? min($totalRealisasi / $target, 1) : 0;

        // urutkan realisasi
        $realisasiSortir = $approved->sortBy('tanggal_realisasi');

        $akumulasi = 0;
        $tanggalCapai100 = null;

        foreach ($realisasiSortir as $r) {

            $akumulasi += $r->realisasi;

            if ($akumulasi >= $target) {
                $tanggalCapai100 = $r->tanggal_realisasi;
                break;
            }
        }

        $hariTelat = 0;

        if ($tanggalCapai100) {

            if (Carbon::parse($tanggalCapai100)->gt(Carbon::parse($tugas->deadline))) {

                $hariTelat = Carbon::parse($tugas->deadline)
                    ->startOfDay()
                    ->diffInDays(Carbon::parse($tanggalCapai100)->startOfDay());
            }

        } else {

            if (Carbon::now()->gt(Carbon::parse($tugas->deadline))) {

                $hariTelat = Carbon::parse($tugas->deadline)
                    ->startOfDay()
                    ->diffInDays(Carbon::now()->startOfDay());
            }

        }

        // bobot pekerjaan
        $bobot = $tugas->jenisPekerjaan->bobot ?? 0;

        // penalti 5% per hari telat
        $penalti = $bobot * 0.05 * $hariTelat;

        // nilai akhir
        $nilaiAkhir = max(0, ($bobot * $progress) - $penalti);

        return [
            'totalRealisasi' => $totalRealisasi,
            'progress' => $progress,
            'hariTelat' => $hariTelat,
            'penalti' => $penalti,
            'nilaiAkhir' => round($nilaiAkhir, 2),
            'bobot' => $bobot
        ];
    }
}