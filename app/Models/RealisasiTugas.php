<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RealisasiTugas extends Model
{
    protected $fillable = [
        'tugas_id',
        'realisasi',
        'tanggal_realisasi',
        'catatan',
        'file_bukti',
        'is_approved',
    ];

    public function tugas()
    {
        return $this->belongsTo(Tugas::class);
    }
}
