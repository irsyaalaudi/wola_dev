<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_pekerjaan_teams', function (Blueprint $table) {
            $table->unsignedBigInteger('jenis_pekerjaan_id');
            $table->unsignedBigInteger('team_id');

            $table->foreign('jenis_pekerjaan_id')
                  ->references('id')
                  ->on('jenis_pekerjaans')
                  ->onDelete('cascade');

            $table->foreign('team_id')
                  ->references('id')
                  ->on('teams')
                  ->onDelete('cascade');

            $table->primary(['jenis_pekerjaan_id', 'team_id']);
            $table->timestamps();
        });

        $grouped = DB::table('jenis_pekerjaans')
            ->select('nama_pekerjaan', DB::raw('MIN(id) as master_id'))
            ->groupBy('nama_pekerjaan')
            ->get();

        foreach ($grouped as $group) {
            $duplicates = DB::table('jenis_pekerjaans')
                ->where('nama_pekerjaan', $group->nama_pekerjaan)
                ->get();

            $teamIds = $duplicates
                ->pluck('tim_id')
                ->filter() 
                ->unique()
                ->values();

            foreach ($teamIds as $teamId) {
                $teamExists = DB::table('teams')->where('id', $teamId)->exists();
                if (!$teamExists) continue;

                DB::table('jenis_pekerjaan_teams')->insertOrIgnore([
                    'jenis_pekerjaan_id' => $group->master_id,
                    'team_id'            => $teamId,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }
        }
        foreach ($grouped as $group) {
            $duplicateIds = DB::table('jenis_pekerjaans')
                ->where('nama_pekerjaan', $group->nama_pekerjaan)
                ->where('id', '!=', $group->master_id)
                ->pluck('id');

            if ($duplicateIds->isEmpty()) continue;

            DB::table('tugas')
                ->whereIn('jenis_pekerjaan_id', $duplicateIds)
                ->update(['jenis_pekerjaan_id' => $group->master_id]);
        }

        foreach ($grouped as $group) {
            DB::table('jenis_pekerjaans')
                ->where('nama_pekerjaan', $group->nama_pekerjaan)
                ->where('id', '!=', $group->master_id)
                ->delete();
        }

        Schema::table('jenis_pekerjaans', function (Blueprint $table) {
            try {
                if (Schema::hasColumn('jenis_pekerjaans', 'tim_id')) {
                    $table->dropForeign(['tim_id']);
                }
            } catch (\Exception $e) { }

            // Daftar kolom yang akan dihapus
            $columnsToDrop = [];
            if (Schema::hasColumn('jenis_pekerjaans', 'tim_id')) $columnsToDrop[] = 'tim_id';
            if (Schema::hasColumn('jenis_pekerjaans', 'pemberi_pekerjaan')) $columnsToDrop[] = 'pemberi_pekerjaan';
            if (Schema::hasColumn('jenis_pekerjaans', 'volume')) $columnsToDrop[] = 'volume';

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    public function down(): void
    {
        Schema::table('jenis_pekerjaans', function (Blueprint $table) {
            if (!Schema::hasColumn('jenis_pekerjaans', 'tim_id')) {
                $table->unsignedBigInteger('tim_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('jenis_pekerjaans', 'pemberi_pekerjaan')) {
                $table->string('pemberi_pekerjaan')->nullable()->after('bobot');
            }
            if (!Schema::hasColumn('jenis_pekerjaans', 'volume')) {
                $table->integer('volume')->nullable()->after('satuan');
            }
        });
        $pivotData = DB::table('jenis_pekerjaan_teams')
            ->select('jenis_pekerjaan_id', DB::raw('MIN(team_id) as team_id'))
            ->groupBy('jenis_pekerjaan_id')
            ->get();

        foreach ($pivotData as $row) {
            DB::table('jenis_pekerjaans')
                ->where('id', $row->jenis_pekerjaan_id)
                ->update(['tim_id' => $row->team_id]);
        }

        Schema::dropIfExists('jenis_pekerjaan_teams');
    }
};
