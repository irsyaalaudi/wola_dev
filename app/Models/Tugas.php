<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tugas extends Model
{
    protected $fillable = [
        'jenis_pekerjaan_id',
        'pegawai_id',
        'target',
        'asal',
        'satuan',
        'deadline',
        'created_at',
    ];

    public function jenisPekerjaan()
    {
        return $this->belongsTo(JenisPekerjaan::class);
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class);
    }

    // Relasi default (satu realisasi terakhir / utama)
    public function realisasi()
    {
        return $this->hasOne(RealisasiTugas::class);
    }

    // Relasi banyak realisasi
    public function semuaRealisasi()
    {
        return $this->hasMany(RealisasiTugas::class);
    }

    // Relasi realisasi yang sudah di-approve admin
    public function realisasiApproved()
    {
        return $this->hasMany(RealisasiTugas::class)->where('is_approved', true);
    }
}
