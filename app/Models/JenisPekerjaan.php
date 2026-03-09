<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JenisPekerjaan extends Model
{
    protected $fillable = [
        'nama_pekerjaan',
        'satuan',
        'bobot', 
    ];

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'jenis_pekerjaan_teams', 'jenis_pekerjaan_id', 'team_id')
                    ->withTimestamps();
    }

    public function getTeamAttribute()
    {
        return $this->teams->first();
    }
    
    public function tugas()
    {
        return $this->hasMany(Tugas::class);
    }

}
