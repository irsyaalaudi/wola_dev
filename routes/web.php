<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;

// SUPERADMIN CONTROLLERS
use App\Http\Controllers\Superadmin\DashboardController as SuperadminDashboardController;
use App\Http\Controllers\Superadmin\JenisPekerjaanController;
use App\Http\Controllers\Superadmin\JenisTimController;
use App\Http\Controllers\Superadmin\PegawaiController;
use App\Http\Controllers\Superadmin\ProgressController;
use App\Http\Controllers\Superadmin\PekerjaanController;
use App\Http\Controllers\Superadmin\UserController;
use App\Http\Controllers\Superadmin\SupportController as SuperadminSupportController;

// EKSPORT DATA JENIS PEKERJAAN
use App\Exports\JenisPekerjaanExport;
use Maatwebsite\Excel\Facades\Excel;

// ADMIN CONTROLLERS
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ProgressController as AdminProgressController;
use App\Http\Controllers\Admin\PekerjaanController as AdminPekerjaanController;
use App\Http\Controllers\Admin\PegawaiController as AdminPegawaiController;
use App\Http\Controllers\Admin\SupportController as AdminSupportController;

// USER CONTROLLERS
use App\Http\Controllers\User\DashboardController as UserDashboardController;
use App\Http\Controllers\User\PekerjaanController as UserPekerjaanController;
use App\Http\Controllers\User\PegawaiController as UserPegawaiController;
use App\Http\Controllers\User\SupportController as UserSupportController;

// =========================
// AUTH ROUTES
// =========================
Route::get('/', [AuthController::class, 'showLogin'])->name('login');
Route::post('/', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');


// =========================
// ADMIN PANEL ROUTES
// =========================
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/export', [AdminDashboardController::class, 'exportExcel'])
        ->name('dashboard.export');
    Route::get('pegawai', [AdminPegawaiController::class, 'index'])->name('pegawai.index');
    Route::get('pegawai/export', [AdminPegawaiController::class, 'exportExcel'])->name('pegawai.export');
    Route::get('pekerjaan', [AdminPekerjaanController::class, 'index'])->name('pekerjaan.index');
    Route::get('pekerjaan/create', [AdminPekerjaanController::class, 'create'])->name('pekerjaan.create');
    Route::post('pekerjaan', [AdminPekerjaanController::class, 'store'])->name('pekerjaan.store');
    Route::put('pekerjaan/{id}', [AdminPekerjaanController::class, 'update'])->name('pekerjaan.update');
    Route::delete('pekerjaan/{id}', [AdminPekerjaanController::class, 'destroy'])->name('pekerjaan.destroy');
    Route::get('pekerjaan/export', [AdminPekerjaanController::class, 'export'])->name('pekerjaan.export');
    Route::post('pekerjaan/import', [AdminPekerjaanController::class, 'import'])->name('pekerjaan.import');
    Route::get('progress', [AdminProgressController::class, 'index'])->name('progress.index');
    Route::get('progress/export', [AdminProgressController::class, 'export'])->name('progress.export');
    Route::get('/support', [AdminSupportController::class, 'index'])->name('support');
    Route::patch('/progress/{id}/approve', [AdminProgressController::class, 'approve'])->name('progress.approve');
    Route::get('pekerjaan/template', [AdminPekerjaanController::class, 'downloadTemplate'])
     ->name('pekerjaan.template');

});


// =========================
// SUPERADMIN PANEL ROUTES
// =========================

Route::prefix('superadmin')->name('superadmin.')->middleware(['auth', 'role:superadmin'])->group(function () {
    // Dashboard Route
    Route::get('dashboard', [SuperadminDashboardController::class, 'index'])
        ->name('dashboard');

    Route::get('dashboard/export', [SuperadminDashboardController::class, 'exportExcel'])
        ->name('dashboard.export');
    // User Routes
    Route::get('user', [UserController::class, 'index'])->name('master_user.index');
    Route::post('user/create', [UserController::class, 'create'])->name('master_user.create');
    Route::post('user', [UserController::class, 'store'])->name('master_user.store');
    Route::put('user/{id}', [UserController::class, 'update'])->name('master_user.update');
    Route::delete('user/{id}', [UserController::class, 'destroy'])->name('master_user.destroy');
    Route::get('user/export', [UserController::class, 'export'])->name('master_user.export');
    Route::post('user/import', [UserController::class, 'import'])->name('master_user.import');


    // Jenis Pekerjaan Routes
    Route::get('jenis-pekerjaan', [JenisPekerjaanController::class, 'index'])->name('jenis-pekerjaan.index');
    Route::post('jenis-pekerjaan/create', [JenisPekerjaanController::class, 'create'])->name('jenis-pekerjaan.create');
    Route::post('jenis-pekerjaan', [JenisPekerjaanController::class, 'store'])->name('jenis-pekerjaan.store');
    Route::put('jenis-pekerjaan/{id}', [JenisPekerjaanController::class, 'update'])->name('jenis-pekerjaan.update');
    Route::delete('jenis-pekerjaan/{id}', [JenisPekerjaanController::class, 'destroy'])->name('jenis-pekerjaan.destroy');

    // ✅ Tambahan Export Jenis Pekerjaan (langsung dari Controller)
    Route::get('jenis-pekerjaan/export', [JenisPekerjaanController::class, 'export'])->name('jenis-pekerjaan.export');
    // Tambah Import data Jenis Pekerjaan
    Route::post('jenis-pekerjaan/import', [JenisPekerjaanController::class, 'import'])->name('jenis-pekerjaan.import');

    // Jenis Tim Routes
    Route::get('jenis-tim', [JenisTimController::class, 'index'])->name('jenis-tim.index');
    Route::post('jenis-tim', [JenisTimController::class, 'store'])->name('jenis-tim.store');
    Route::put('jenis-tim/{id}', [JenisTimController::class, 'update'])->name('jenis-tim.update');
    Route::delete('jenis-tim/{id}', [JenisTimController::class, 'destroy'])->name('jenis-tim.destroy');
    Route::get('jenis-tim/export', [JenisTimController::class, 'export'])->name('jenis-tim.export');
    Route::post('jenis-tim/import', [JenisTimController::class, 'import'])->name('jenis-tim.import');



    // Pegawai Routes
    Route::get('pegawai', [PegawaiController::class, 'index'])->name('master_pegawai.index');
    Route::get('pegawai/export', [PegawaiController::class, 'export'])->name('master_pegawai.export');


    // Progress Routes
    Route::get('progress', [ProgressController::class, 'index'])->name('progress.index');
    Route::get('progress/{id}', [ProgressController::class, 'show'])->name('progress.show');
    Route::get('/progress/export/kinerja', [ProgressController::class, 'exportKinerja'])->name('progress.export.kinerja');
    Route::get('/progress/export/nilai-akhir', [ProgressController::class, 'exportNilaiAkhir'])->name('progress.export.nilaiAkhir');

    // Pekerjaan Routes
    Route::get('pekerjaan', [PekerjaanController::class, 'index'])->name('pekerjaan.index');

    // Support Route
    Route::get('/support', [SuperadminSupportController::class, 'index'])->name('support');
});


Route::get('/user/pekerjaan/export', [UserPekerjaanController::class, 'export'])
    ->name('user.pekerjaan.export');

Route::get('/user/pekerjaan/export', 
[App\Http\Controllers\User\PekerjaanController::class, 'export'])
->name('user.pekerjaan.export');

Route::get('/jenis-pekerjaan/template', 
    [JenisPekerjaanController::class,'downloadTemplate']
)->name('superadmin.jenis-pekerjaan.template');

Route::post('/jenis-pekerjaan/import',
    [JenisPekerjaanController::class,'import']
)->name('superadmin.jenis-pekerjaan.import');

// =========================
// USER PANEL ROUTES
// =========================
// USER PANEL ROUTES (admin juga bisa akses)
Route::prefix('user')->name('user.')->middleware(['auth', 'role:user,admin'])->group(function () {
    Route::get('dashboard', [UserDashboardController::class, 'index'])->name('dashboard');

    Route::get('pekerjaan', [UserPekerjaanController::class, 'index'])->name('pekerjaan.index');
    Route::post('pekerjaan/{id}/realisasi', [UserPekerjaanController::class, 'storeRealisasi'])->name('pekerjaan.realisasi');
    Route::put('pekerjaan/{id}/realisasi', [UserPekerjaanController::class, 'updateRealisasi'])->name('pekerjaan.realisasi.update');

    Route::get('pegawai', [UserPegawaiController::class, 'index'])->name('pegawai.index');
    Route::get('/support', [UserSupportController::class, 'index'])->name('support');
});
