<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JenisPekerjaan extends Model
{
    protected $fillable = [
        'nama_pekerjaan',
        'satuan',
        'volume',
        'bobot',
        'pemberi_pekerjaan',
        'tim_id',
    ];

    public function tugas()
    {
        return $this->hasMany(Tugas::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class, 'tim_id');
    }
}
