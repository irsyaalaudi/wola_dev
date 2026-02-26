<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jenis_pekerjaans', function (Blueprint $table) {
            $table->id();
            $table->string('nama_pekerjaan');
            $table->string('satuan');
            $table->integer('volume')->default(0);
            $table->double('bobot')->default(0);
            $table->string('pemberi_pekerjaan')->nullable();
            $table->foreignId('tim_id')->nullable()->constrained('teams')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jenis_pekerjaans');
    }
};
