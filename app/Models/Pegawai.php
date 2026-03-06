<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pegawai extends Model
{
    protected $fillable = ['user_id', 'nip', 'jabatan'];

    /**
     * Relasi ke User (1 Pegawai = 1 User)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getNamaAttribute()
    {
        return $this->user ? $this->user->name : null;
    }
    
    /**
     * Relasi ke Team (Many-to-Many)
     * pivot: pegawai_team
     * kolom tambahan: is_leader
     */
    public function teams()
    {
        return $this->belongsToMany(Team::class, 'pegawai_team', 'pegawai_id', 'team_id')
                    ->withPivot('is_leader')
                    ->withTimestamps();
    }

    /**
     * Relasi ke Tugas (1 Pegawai = banyak tugas)
     */
    public function tugas()
    {
        return $this->hasMany(Tugas::class, 'pegawai_id');
    }

    /**
     * Relasi ke Progress (1 Pegawai = 1 Progress)
     */
    public function progress()
    {
        return $this->hasOne(Progress::class, 'pegawai_id');
    }
}
