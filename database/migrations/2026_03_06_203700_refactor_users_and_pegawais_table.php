<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pegawais', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
        });

        DB::statement('
            UPDATE pegawais p
            INNER JOIN users u ON u.pegawai_id = p.id
            SET p.user_id = u.id
        ');

        $pegawaiTanpaUser = DB::table('pegawais')->whereNull('user_id')->count();

        if ($pegawaiTanpaUser > 0) {
            Schema::table('pegawais', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });

            $detailData = DB::table('pegawais')
                ->whereNull('user_id')
                ->select('id', 'nama', 'nip')
                ->get()
                ->map(fn($p) => "  - pegawai_id={$p->id}, nama={$p->nama}, nip={$p->nip}")
                ->implode("\n");

            throw new \RuntimeException(
                "MIGRASI DIBATALKAN: Terdapat {$pegawaiTanpaUser} pegawai yang tidak memiliki akun user.\n" .
                "Selesaikan data berikut terlebih dahulu sebelum menjalankan migrasi:\n" .
                $detailData . "\n\n" .
                "Pastikan setiap pegawai memiliki entri di tabel users dengan pegawai_id yang sesuai."
            );
        }

        Schema::table('pegawais', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade'); 

            $table->unique('user_id');
        });

        Schema::table('pegawais', function (Blueprint $table) {
            $table->dropColumn('nama');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pegawai_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('pegawai_id')->nullable()->after('role');
        });
        Schema::table('pegawais', function (Blueprint $table) {
            $table->string('nama')->nullable()->after('user_id');
        });

        DB::statement('
            UPDATE pegawais p
            INNER JOIN users u ON u.id = p.user_id
            SET p.nama = u.name
        ');

        DB::statement('
            UPDATE users u
            INNER JOIN pegawais p ON p.user_id = u.id
            SET u.pegawai_id = p.id
        ');

        Schema::table('pegawais', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('pegawais', function (Blueprint $table) {
            $table->string('nama')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('pegawai_id')->nullable(false)->change();
            $table->foreign('pegawai_id')
                  ->references('id')
                  ->on('pegawais')
                  ->onDelete('cascade');
        });
    }
};
