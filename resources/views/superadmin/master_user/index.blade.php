@extends('layouts.app')

@section('page-title', 'Master | User')

@section('content')
<div class="bg-white rounded-2xl p-6 mb-12 border border-gray-200">

  <!-- Header & Action -->
  <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4 flex-wrap">
    <h2 class="text-2xl font-semibold text-blue-600">Manajemen User & Pegawai</h2>

    <div class="flex flex-col sm:flex-row items-center gap-3">
      <!-- Form Search -->
      <form method="GET" action="{{ route('superadmin.master_user.index') }}" class="flex gap-3 w-full sm:w-auto">
        <input type="text" name="search" value="{{ request('search') }}"
          class="px-4 py-2 w-full sm:w-64 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 bg-white/50 backdrop-blur-sm placeholder-gray-500"
          placeholder="Cari nama pegawai, NIP, email...">
        <button type="submit"
          class="px-4 py-2 rounded-lg border border-gray-400 text-gray-600 font-medium bg-white/40 backdrop-blur-sm hover:bg-gray-100 hover:text-gray-700 transition duration-200 ease-in-out transform hover:scale-105">
          <i class="fas fa-search mr-1"></i> Cari
        </button>
      </form>

      <!-- Tombol Export -->
      <a href="{{ route('superadmin.master_user.export') }}"
        class="inline-flex items-center px-4 py-2 rounded-lg border border-green-400 text-green-600 font-medium bg-green-200/20 backdrop-blur-sm shadow-sm hover:bg-green-300/30 hover:border-green-500 hover:text-green-700 transition duration-200 ease-in-out transform hover:scale-105">
        <i class="fas fa-file-excel mr-2"></i> Export Tabel
      </a>

      <!-- Tombol Import -->
      <button @click="openImport = true"
        class="inline-flex items-center px-4 py-2 rounded-lg border border-purple-400 text-purple-600 font-medium bg-purple-200/20 backdrop-blur-sm shadow-sm hover:bg-purple-300/30 hover:border-purple-500 hover:text-purple-700 transition duration-200 ease-in-out transform hover:scale-105">
        <i class="fas fa-file-upload mr-2"></i> Upload Data
      </button>

      <!-- Tombol Tambah User -->
      <button @click="openCreate = true"
        class="inline-flex items-center px-4 py-2 rounded-lg border border-blue-500 text-blue-600 font-medium bg-blue-200/20 backdrop-blur-sm shadow-sm hover:bg-blue-300/30 hover:border-blue-600 hover:text-blue-700 transition duration-200 ease-in-out transform hover:scale-105">
        <i class="fas fa-user-plus mr-2"></i> Tambah User
      </button>
    </div>
  </div>

  <!-- Pesan Sukses / Error -->
  @if(session('success'))
  <div class="mb-4 bg-green-50 text-green-700 px-4 py-2 rounded-md border border-green-200">
    {{ session('success') }}
  </div>
  @endif

  @if(session('error'))
  <div class="mb-4 bg-red-50 text-red-700 px-4 py-2 rounded-md border border-red-200">
    {!! session('error') !!}
  </div>
  @endif

  <!-- Tabel User -->
  <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
    <table class="w-full table-auto text-sm text-gray-700">
      <thead class="bg-gradient-to-r from-blue-100 to-blue-200 text-center text-sm text-gray-700">
        <tr>
          <th class="p-3 border">No.</th>
          <th class="p-3 border text-left">Nama Pegawai</th>
          <th class="p-3 border">NIP</th>
          <th class="p-3 border">Tim</th>
          <th class="p-3 border">Tim Yang Dipimpin</th>
          <th class="p-3 border">Jabatan</th>
          <th class="p-3 border">Email</th>
          <th class="p-3 border">Role</th>
          <th class="p-3 border">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($users as $user)
        <tr class="even:bg-gray-50 hover:bg-blue-50 transition">
        <td class="p-3 border text-center">
          {{ $users->firstItem() + $loop->index }}
        </td>
          
          <td class="p-3 border">{{ $user->pegawai->nama ?? '-' }}</td>
          <td class="p-3 border text-center">{{ $user->pegawai->nip ?? '-' }}</td>

          <!-- Tim (semua tim) -->
          <td class="p-3 border text-center">
            @if($user->pegawai && $user->pegawai->teams->count() > 0)
            @foreach($user->pegawai->teams as $team)
            <span class="inline-block bg-blue-100 text-blue-600 px-2 py-1 rounded text-xs m-0.5">
              {{ $team->nama_tim }}
            </span>
            @endforeach
            @else
            -
            @endif
          </td>


          <!-- Tim Yang Dipimpin -->
          <td class="p-3 border text-center">
            @if($user->pegawai && $user->pegawai->teams->where('pivot.is_leader', true)->count() > 0)
            @foreach($user->pegawai->teams->where('pivot.is_leader', true) as $team)
            <span class="inline-block bg-green-100 text-green-600 px-2 py-1 rounded text-xs m-0.5">
              <strong>{{ $team->nama_tim }}</strong>
            </span>
            @endforeach
            @else
            -
            @endif
          </td>

          <td class="p-3 border text-center">{{ $user->pegawai->jabatan ?? '-' }}</td>
          <td class="p-3 border">{{ $user->email }}</td>
          <td class="p-3 border text-center capitalize">{{ $user->role }}</td>
          <td class="p-3 border">
            <div class="flex justify-center gap-2">
              <!-- Tombol Edit -->
              <button @click="openEdit = {{ $user->id }}; role='{{ $user->role }}'"
                class="px-3 py-1 rounded-lg border border-yellow-400 text-yellow-600 bg-yellow-100/40 backdrop-blur-sm text-xs hover:bg-yellow-200 hover:text-yellow-700 transition">
                <i class="fas fa-edit mr-1"></i> Edit
              </button>

              <!-- Tombol Hapus -->
              <form action="{{ route('superadmin.master_user.destroy', $user->id) }}" method="POST"
                onsubmit="return confirm('Hapus user ini?')">
                @csrf @method('DELETE')
                <button type="submit"
                  class="px-3 py-1 rounded-lg border border-red-500 text-red-600 bg-red-100/40 backdrop-blur-sm text-xs hover:bg-red-200 hover:text-red-700 transition">
                  <i class="fas fa-trash mr-1"></i> Hapus
                </button>
              </form>
            </div>
          </td>
        </tr>

        <!-- Modal Edit User -->
<template x-if="openEdit === {{ $user->id }}">
  <div class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="bg-white/90 backdrop-blur-md rounded-2xl w-full max-w-md relative border border-gray-200 shadow-xl">
      
      <!-- Konten Modal Scrollable -->
      <div class="p-6 max-h-[80vh] overflow-y-auto">
        <button @click="openEdit = null" 
                class="absolute top-3 right-4 text-gray-400 text-2xl hover:text-red-500">&times;</button>
        <h3 class="text-lg font-semibold mb-4 text-gray-700">Edit Data User</h3>

        <form action="{{ route('superadmin.master_user.update', $user->id) }}" method="POST">
          @csrf @method('PUT')
          <div class="grid grid-cols-1 gap-3">
            <!-- Data Pegawai -->
            <input type="text" name="nama" class="border rounded-lg px-3 py-2" 
                   placeholder="Nama Pegawai" value="{{ $user->pegawai->nama ?? '' }}" required>
            <input type="text" name="nip" class="border rounded-lg px-3 py-2" 
                   placeholder="NIP" value="{{ $user->pegawai->nip ?? '' }}" required>
            <input type="text" name="jabatan" class="border rounded-lg px-3 py-2" 
                   placeholder="Jabatan" value="{{ $user->pegawai->jabatan ?? '' }}" required>

            <!-- Pilih Tim -->
            <label class="font-medium">Pilih Tim</label>
            <div class="flex flex-wrap gap-2">
              @foreach($teams as $team)
              <label class="inline-flex items-center gap-1">
                <input type="checkbox" name="teams[]" value="{{ $team->id }}" 
                       class="form-checkbox text-blue-600"
                       {{ $user->pegawai && $user->pegawai->teams->pluck('id')->contains($team->id) ? 'checked' : '' }}>
                <span>{{ $team->nama_tim }}</span>
              </label>
              @endforeach
            </div>

            <!-- Data User -->
            <input type="email" name="email" class="border rounded-lg px-3 py-2" 
                   value="{{ $user->email }}" required>
            <input type="password" name="password" class="border rounded-lg px-3 py-2" 
                   placeholder="Password baru (kosongkan jika tidak ganti)">

            <!-- Pilih Role -->
            <select name="role" x-model="role" class="border rounded-lg px-3 py-2" required>
              <option disabled>-- Pilih Role --</option>
              <option value="superadmin" {{ $user->role === 'superadmin' ? 'selected' : '' }}>Superadmin</option>
              <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Admin</option>
              <option value="user" {{ $user->role === 'user' ? 'selected' : '' }}>User</option>
            </select>

            <!-- Ketua Tim -->
            <div x-show="role === 'admin'" class="mt-3">
              <label class="block text-sm font-medium text-gray-600 mb-2">Ketua Tim</label>
              <div class="flex flex-col gap-2 max-h-40 overflow-y-auto border rounded-lg p-2">
                @foreach($teams as $team)
                @php
                  $currentLeader = $team->pegawais->firstWhere('pivot.is_leader', true);
                  $isDisabled = $currentLeader && ($currentLeader->id ?? 0) !== ($user->pegawai->id ?? 0);
                  $isChecked = $currentLeader && ($currentLeader->id ?? 0) === ($user->pegawai->id ?? 0);
                @endphp
                <label class="flex items-center gap-2 {{ $isDisabled ? 'text-gray-400' : '' }}">
                  <input type="radio" name="leader[]" value="{{ $team->id }}"
                         @if($isDisabled) disabled @endif
                         @if($isChecked) checked @endif
                         class="form-radio {{ $isDisabled ? 'text-gray-400 cursor-not-allowed' : 'text-blue-600' }}">
                  <span>{{ $team->nama_tim }}</span>
                </label>
                @endforeach
              </div>
              <small class="text-xs text-gray-500">Hanya bisa memilih satu ketua tim...</small>
            </div>
          </div>

          <div class="mt-4 flex justify-end gap-2">
            <button type="button" @click="openEdit = null"
              class="px-4 py-2 rounded-lg border border-gray-400 bg-gray-100/60 text-gray-700 hover:bg-gray-200 transition">Batal</button>
            <button type="submit"
              class="px-4 py-2 rounded-lg border border-green-500 bg-green-100/60 text-green-700 hover:bg-green-200 transition">Simpan</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>


        @empty
        <tr>
          <td colspan="8" class="text-center py-6 text-gray-500">Tidak ada data user atau pegawai.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Import -->
<template x-if="openImport">
  <div class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="bg-white/90 backdrop-blur-md p-6 rounded-2xl w-full max-w-md relative border border-gray-200 shadow-xl">
      <button @click="openImport = false" class="absolute top-3 right-4 text-gray-400 text-2xl hover:text-red-500">&times;</button>
      <h2 class="text-lg font-semibold mb-4 text-gray-700">Import Data User & Pegawai</h2>
      <form action="{{ route('superadmin.master_user.import') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="file" name="file" accept=".xlsx,.xls" class="border rounded-lg w-full px-3 py-2 mb-4" required>
        <div class="flex justify-end gap-2">
          <button type="button" @click="openImport = false" class="px-4 py-2 rounded-lg border border-gray-400 bg-gray-100/60 text-gray-700 hover:bg-gray-200 transition">Batal</button>
          <button type="submit" class="px-4 py-2 rounded-lg border border-purple-500 bg-purple-100/60 text-purple-700 hover:bg-purple-200 transition">Upload</button>
        </div>
      </form>
    </div>
  </div>
</template>

<!-- Modal Create -->
<template x-if="openCreate">
  <div class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="bg-white/90 backdrop-blur-md rounded-2xl w-full max-w-md relative border border-gray-200 shadow-xl">
      
      <!-- Konten Modal Scrollable -->
      <div class="p-6 max-h-[80vh] overflow-y-auto">
        <button @click="openCreate = false" 
                class="absolute top-3 right-4 text-gray-400 text-2xl hover:text-red-500">&times;</button>
        <h2 class="text-lg font-semibold mb-4 text-gray-700">Tambah User & Pegawai</h2>

        <form action="{{ route('superadmin.master_user.store') }}" method="POST">
          @csrf
          <div class="grid grid-cols-1 gap-3">
            <input type="text" name="nama" class="border rounded-lg px-3 py-2" placeholder="Nama Pegawai" required>
            @error('nama')
              <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror

            <input type="text" name="nip" class="border rounded-lg px-3 py-2" placeholder="NIP" required>
            @error('nip')
              <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror

            <input type="text" name="jabatan" class="border rounded-lg px-3 py-2" placeholder="Jabatan" required>
            @error('jabatan')
              <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror

            <!-- Pilih Tim -->
            <label class="font-medium">Pilih Tim</label>
            <div class="flex flex-wrap gap-2">
              @foreach($teams as $team)
              <label class="inline-flex items-center gap-1">
                <input type="checkbox" name="teams[]" value="{{ $team->id }}" class="form-checkbox text-blue-600">
                <span>{{ $team->nama_tim }}</span>
              </label>
              @endforeach
            </div>
            @error('teams')
              <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror

            <input type="email" name="email" class="border rounded-lg px-3 py-2" placeholder="Email" required>
            <input type="password" name="password" class="border rounded-lg px-3 py-2" placeholder="Password" required minlength="6">
            <small class="text-xs text-gray-500">Password minimal 6 karakter</small>

            <select name="role" x-model="role" class="border rounded-lg px-3 py-2" required>
              <option disabled selected>-- Pilih Role --</option>
              <option value="superadmin">Superadmin</option>
              <option value="admin">Admin</option>
              <option value="user">User</option>
            </select>

            <!-- Radio Ketua Tim -->
            <div x-show="role === 'admin'" class="mt-3">
              <label class="block text-sm font-medium text-gray-600 mb-2">Ketua Tim</label>
              <div class="flex flex-col gap-2 max-h-40 overflow-y-auto border rounded-lg p-2">
                @foreach($teams as $team)
                  @php
                    $currentLeader = $team->pegawais->firstWhere('pivot.is_leader', true);
                    $isDisabled = $currentLeader;
                  @endphp
                  <label class="flex items-center gap-2 {{ $isDisabled ? 'text-gray-400' : '' }}">
                    <input type="radio" name="leader[]" value="{{ $team->id }}"
                      @if($isDisabled) disabled @endif
                      class="form-radio {{ $isDisabled ? 'text-gray-400 cursor-not-allowed' : 'text-blue-600' }}">
                    <span>{{ $team->nama_tim }}</span>
                  </label>
                @endforeach
              </div>
              <small class="text-xs text-gray-500">
                Hanya bisa memilih satu ketua tim. Jika tim sudah ada ketua lain, radio akan abu-abu dan tidak bisa dipilih.
              </small>
            </div>
          </div>

          <div class="mt-4 flex justify-end gap-2">
            <button type="button" @click="openCreate = false" 
              class="px-4 py-2 rounded-lg border border-gray-400 bg-gray-100/60 text-gray-700 hover:bg-gray-200 transition">Batal</button>
            <button type="submit" 
              class="px-4 py-2 rounded-lg border border-blue-500 bg-blue-100/60 text-blue-700 hover:bg-blue-200 transition">Simpan</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>


<!-- Paginasi -->
@if ($users->hasPages())
  <div class="flex items-center justify-between border-t border-white/10 px-4 py-3 sm:px-6">
    <!-- Mobile Previous/Next -->
    <div class="flex flex-1 justify-between sm:hidden">
      @if ($users->onFirstPage())
        <span class="relative inline-flex items-center rounded-md border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-gray-400 cursor-not-allowed">Previous</span>
      @else
        <a href="{{ $users->previousPageUrl() }}" 
           class="relative inline-flex items-center rounded-md border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-gray-200 hover:bg-white/10">Previous</a>
      @endif

      @if ($users->hasMorePages())
        <a href="{{ $users->nextPageUrl() }}" 
           class="relative ml-3 inline-flex items-center rounded-md border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-gray-200 hover:bg-white/10">Next</a>
      @else
        <span class="relative ml-3 inline-flex items-center rounded-md border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-gray-400 cursor-not-allowed">Next</span>
      @endif
    </div>

    <!-- Desktop -->
    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
      <div>
        <p class="text-sm text-black">
          Menampilkan
          <span class="font-medium">{{ $users->firstItem() }}</span>
          sampai
          <span class="font-medium">{{ $users->lastItem() }}</span>
          data dari
          <span class="font-medium">{{ $users->total() }}</span>
          data keseluruhan
        </p>
      </div>
      <div>
        <nav aria-label="Pagination" class="isolate inline-flex -space-x-px rounded-md">
          {{-- Tombol Previous --}}
          @if ($users->onFirstPage())
            <span class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 cursor-not-allowed">
              <span class="sr-only">Previous</span>
              <svg viewBox="0 0 20 20" fill="currentColor" class="size-5">
                <path d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" />
              </svg>
            </span>
          @else
            <a href="{{ $users->previousPageUrl() }}" 
               class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 hover:bg-white/5">
              <span class="sr-only">Previous</span>
              <svg viewBox="0 0 20 20" fill="currentColor" class="size-5">
                <path d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" />
              </svg>
            </a>
          @endif

          {{-- Nomor Halaman --}}
          @foreach ($users->getUrlRange(1, $users->lastPage()) as $page => $url)
            @if ($page == $users->currentPage())
              <span aria-current="page" class="relative z-10 inline-flex items-center bg-blue-700 px-4 py-2 text-sm font-semibold text-white">{{ $page }}</span>
            @else
              <a href="{{ $url }}" 
                 class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-400 hover:bg-white/5">{{ $page }}</a>
            @endif
          @endforeach

          {{-- Tombol Next --}}
          @if ($users->hasMorePages())
            <a href="{{ $users->nextPageUrl() }}" 
               class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 hover:bg-white/5">
              <span class="sr-only">Next</span>
              <svg viewBox="0 0 20 20" fill="currentColor" class="size-5">
                <path d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" />
              </svg>
            </a>
          @else
            <span class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 cursor-not-allowed">
              <span class="sr-only">Next</span>
              <svg viewBox="0 0 20 20" fill="currentColor" class="size-5">
                <path d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" />
              </svg>
            </span>
          @endif
        </nav>
      </div>
    </div>
  </div>
@endif

<!-- Footer -->
<footer class="text-center text-sm text-gray-500 py-4 border-t mt-8">
  © {{ date('Y') }} <strong>WOLA</strong>. All rights reserved.
</footer>

<script>
  document.addEventListener('alpine:init', () => {
    Alpine.data('userModal', () => ({
      openCreate: false,
      openEdit: null,
      openImport: false,
      role: 'user',
    }));
  });
</script>
@endsection

@section('body-attrs', 'x-data="userModal()"')