<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tugas', function (Blueprint $table) {
            $table->enum('status', ['pending', 'on_progress','waiting_approval', 'done'])
                  ->default('pending')
                  ->after('deadline');

            $table->date('start_date')
                  ->nullable()
                  ->after('asal');
        });

        DB::statement("
            UPDATE tugas t
            SET 
                t.start_date = DATE(t.created_at),
                t.status = CASE
                    WHEN EXISTS (
                        SELECT 1 FROM realisasi_tugas r
                        WHERE r.tugas_id = t.id AND r.is_approved = 1
                    ) THEN 'done'
                    WHEN EXISTS (
                        SELECT 1 FROM realisasi_tugas r
                        WHERE r.tugas_id = t.id
                    ) THEN 'on_progress'
                    ELSE 'pending'
                END
            WHERE t.start_date IS NULL
        ");

        Schema::table('tugas', function (Blueprint $table) {
            $table->dropColumn('satuan');
        });
    }

    public function down(): void
    {
        Schema::table('tugas', function (Blueprint $table) {
            $table->string('satuan')->nullable()->after('asal');
        });

        DB::statement("
            UPDATE tugas t
            INNER JOIN jenis_pekerjaans j ON j.id = t.jenis_pekerjaan_id
            SET t.satuan = j.satuan
        ");

        Schema::table('tugas', function (Blueprint $table) {
            $table->dropColumn(['status', 'start_date']);
        });
    }
};
