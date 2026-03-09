@extends('layouts.app')

@section('page-title', 'Master | Jenis Pekerjaan')

@section('content')

  <div x-data="{ openCreate: false, openEdit: null, openImport: false }"
    class="bg-white rounded-2xl p-6 mb-12 border border-gray-200">

    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
      <h2 class="text-xl font-semibold text-blue-600">Tabel Jenis Pekerjaan</h2>

      <div class="flex flex-wrap gap-2">
        <!-- Search Form -->
        <form method="GET" action="{{ route('superadmin.jenis-pekerjaan.index') }}" class="flex gap-3 w-full sm:w-auto">
          <input type="text" name="search" value="{{ request('search') }}" class="px-4 py-2 w-full sm:w-64 border border-gray-300 rounded-lg 
                         focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400
                         bg-white/50 backdrop-blur-sm placeholder-gray-500" placeholder="Cari nama pekerjaan...">
          <button type="submit" class="px-4 py-2 rounded-lg border border-gray-400 text-gray-600 font-medium 
                         bg-white/40 backdrop-blur-sm hover:bg-gray-100 hover:text-gray-700
                         transition duration-200 ease-in-out transform hover:scale-105">
            <i class="fas fa-search mr-1"></i> Cari
          </button>
        </form>

        @if(auth()->user()?->role === 'superadmin')
          <a href="{{ route('superadmin.jenis-pekerjaan.export') }}" class="inline-flex items-center px-4 py-2 rounded-lg border border-green-400 text-green-600 font-medium
                               bg-green-200/20 backdrop-blur-sm shadow-sm 
                               hover:bg-green-300/30 hover:border-green-500 hover:text-green-700
                               transition duration-200 ease-in-out transform hover:scale-105">
            <i class="fas fa-file-excel mr-2"></i> Export Tabel
          </a>

          <button @click="openImport = true" class="inline-flex items-center px-4 py-2 rounded-lg border border-purple-400 text-purple-600 font-medium
                               bg-purple-200/20 backdrop-blur-sm shadow-sm 
                               hover:bg-purple-300/30 hover:border-purple-500 hover:text-purple-700
                               transition duration-200 ease-in-out transform hover:scale-105">
            <i class="fas fa-file-upload mr-2"></i> Upload Data
          </button>
        @endif

        <!-- Tombol Tambah Jenis Pekerjaan -->
        <button @click="openCreate = true" class="inline-flex items-center px-4 py-2 rounded-lg border border-blue-500 text-blue-600 font-medium
                       bg-blue-200/20 backdrop-blur-sm shadow-sm 
                       hover:bg-blue-300/30 hover:border-blue-600 hover:text-blue-700
                       transition duration-200 ease-in-out transform hover:scale-105">
          <i class="fas fa-user-plus mr-2"></i> Tambah Jenis Pekerjaan
        </button>
      </div>
    </div>

    <!-- Notifikasi -->
    @if(session('success'))
      <div class="mb-4 bg-green-100 text-green-800 px-4 py-2 rounded">{{ session('success') }}</div>
    @endif

    @if($errors->any())
      <div class="mb-4 bg-red-100 text-red-700 px-4 py-2 rounded">
        <ul class="list-disc pl-5">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <!-- Tabel -->
    <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
      <table class="w-full table-auto text-sm text-gray-700">
        <thead class="bg-gradient-to-r from-blue-100 to-blue-200 text-center text-sm text-gray-700">
          <tr>
            <th class="p-3 border">No.</th>
            <th class="p-3 border">Nama</th>
            <th class="p-3 border">Satuan</th>

            <th class="p-3 border">Bobot</th>
            <th class="p-3 border">Tim</th>
            <th class="p-3 border">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($data as $item)
            <tr class="even:bg-gray-50">
              <td class="p-3 border text-center">{{ $loop->iteration }}</td>
              <td class="p-3 border">{{ $item->nama_pekerjaan }}</td>
              <td class="p-3 border text-center">{{ $item->satuan }}</td>

              <td class="p-3 border text-center">
                {{ rtrim(rtrim(number_format($item->bobot, 2, ',', '.'), '0'), ',') }}
              </td>
              <td class="p-3 border text-center">
                @if($item->teams->count() > 0)
                  @foreach($item->teams as $team)
                    <span class="inline-block bg-blue-100 text-blue-600 px-2 py-1 rounded text-xs m-0.5">
                      {{ $team->nama_tim }}
                    </span>
                  @endforeach
                @else
                  -
                @endif
              </td>
              <td class="p-3 border text-center">
                <div class="flex justify-center gap-2">
                  <!-- Tombol Edit -->
                  <button @click="openEdit = {{ $item->id }}" class="px-3 py-1 rounded-lg border border-yellow-400 text-yellow-600 bg-yellow-100/40 backdrop-blur-sm text-xs
                                       hover:bg-yellow-200 hover:text-yellow-700 transition">
                    <i class="fas fa-edit mr-1"></i> Edit
                  </button>

                  <form action="{{ route('superadmin.jenis-pekerjaan.destroy', $item->id) }}" method="POST"
                    onsubmit="return confirm('Hapus data ini?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="px-3 py-1 rounded-lg border border-red-500 text-red-600 bg-red-100/40 backdrop-blur-sm text-xs
                                         hover:bg-red-200 hover:text-red-700 transition">
                      <i class="fas fa-trash mr-1"></i> Hapus
                    </button>
                  </form>
                </div>
              </td>
            </tr>

            <!-- Modal Edit -->
            <template x-if="openEdit === {{ $item->id }}">
              <div class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50">
                <div
                  class="bg-white/90 backdrop-blur-md p-6 rounded-2xl w-full max-w-md relative border border-gray-200 shadow-xl">
                  <button @click="openEdit = null"
                    class="absolute top-3 right-4 text-gray-400 text-2xl hover:text-red-500">&times;</button>
                  <h3 class="text-lg font-semibold mb-4 text-gray-700">Edit Jenis Pekerjaan</h3>
                  <form action="{{ route('superadmin.jenis-pekerjaan.update', $item->id) }}" method="POST">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-1 gap-3">
                      <input name="nama_pekerjaan" class="border rounded px-3 py-2" value="{{ $item->nama_pekerjaan }}"
                        required>
                      <input name="satuan" class="border rounded px-3 py-2" value="{{ $item->satuan }}" required>

                      <input name="bobot" type="number" step="0.01" min="0" max="100" class="border rounded px-3 py-2"
                        value="{{ $item->bobot }}" required>
                      <label class="text-sm font-medium text-gray-600">Tim</label>
                      <div class="flex flex-wrap gap-2 border rounded px-3 py-2 max-h-40 overflow-y-auto">
                        @foreach($teams as $team)
                          <label class="inline-flex items-center gap-1">
                            <input type="checkbox" name="team_ids[]" value="{{ $team->id }}"
                              class="form-checkbox text-blue-600" {{ $item->teams->pluck('id')->contains($team->id) ? 'checked' : '' }}>
                            <span class="text-sm">{{ $team->nama_tim }}</span>
                          </label>
                        @endforeach
                      </div>
                    </div>
                    <div class="mt-4 flex justify-end gap-2">
                      <button type="button" @click="openEdit = null"
                        class="px-4 py-2 rounded-lg border border-gray-400 bg-gray-100/60 text-gray-700 hover:bg-gray-200 transition">Batal</button>
                      <button
                        class="px-4 py-2 rounded-lg border border-green-500 bg-green-100/60 text-green-700 hover:bg-green-200 transition">Simpan</button>
                    </div>
                  </form>
                </div>
              </div>
            </template>
          @empty
            <tr>
              <td colspan="6" class="text-center py-6 text-gray-500">Tidak ada data jenis pekerjaan.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <!-- Modal Create -->
    <template x-if="openCreate">
      <div class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50">
        <div
          class="bg-white/90 backdrop-blur-md p-6 rounded-2xl w-full max-w-xl relative border border-gray-200 shadow-xl">
          <button @click="openCreate = false"
            class="absolute top-3 right-4 text-gray-400 text-2xl hover:text-red-500">&times;</button>
          <h2 class="text-lg font-semibold mb-4 text-gray-700">Tambah Jenis Pekerjaan</h2>
          <form action="{{ route('superadmin.jenis-pekerjaan.store') }}" method="POST">
            @csrf
            <div class="grid grid-cols-1 gap-3">
              <input name="nama_pekerjaan" class="border rounded px-3 py-2" placeholder="Nama Pekerjaan" required>
              <input name="satuan" class="border rounded px-3 py-2" placeholder="Satuan" required>

              <input name="bobot" type="number" step="0.01" min="0" max="100" class="border rounded px-3 py-2"
                placeholder="Bobot (0-100)" required>
              <label class="text-sm font-medium text-gray-600">Tim</label>
              <div class="flex flex-wrap gap-2 border rounded px-3 py-2 max-h-40 overflow-y-auto">
                @foreach($teams as $team)
                  <label class="inline-flex items-center gap-1">
                    <input type="checkbox" name="team_ids[]" value="{{ $team->id }}" class="form-checkbox text-blue-600">
                    <span class="text-sm">{{ $team->nama_tim }}</span>
                  </label>
                @endforeach
              </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
              <button type="button" @click="openCreate = false"
                class="px-4 py-2 rounded-lg border border-gray-400 bg-gray-100/60 text-gray-700 hover:bg-gray-200 transition">
                Batal
              </button>
              <button type="submit"
                class="px-4 py-2 rounded-lg border border-blue-500 bg-blue-100/60 text-blue-700 hover:bg-blue-200 transition">
                Simpan
              </button>
            </div>
          </form>
        </div>
      </div>
    </template>

    <!-- Modal Import -->
    <template x-if="openImport">
      <div class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50">
        <div
          class="bg-white/90 backdrop-blur-md p-6 rounded-2xl w-full max-w-md relative border border-gray-200 shadow-xl">
          <button @click="openImport = false"
            class="absolute top-3 right-4 text-gray-400 text-2xl hover:text-red-500">&times;</button>
          <h2 class="text-lg font-semibold mb-4 text-gray-700">Import Data Jenis Pekerjaan</h2>
          <form action="{{ route('superadmin.jenis-pekerjaan.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="file" name="file" accept=".xlsx,.xls" class="border rounded-lg w-full px-3 py-2 mb-4" required>
            <div class="flex justify-end gap-2">
              <button type="button" @click="openImport = false"
                class="px-4 py-2 rounded-lg border border-gray-400 bg-gray-100/60 text-gray-700 hover:bg-gray-200 transition">
                Batal
              </button>
              <button type="submit"
                class="px-4 py-2 rounded-lg border border-purple-500 bg-purple-100/60 text-purple-700 hover:bg-purple-200 transition">
                Upload
              </button>
            </div>
          </form>
        </div>
      </div>
    </template>
  </div>

  <!-- Footer -->
  <footer class="text-center text-sm text-gray-500 py-4 border-t mt-8">
    © {{ date('Y') }} <strong>WOLA</strong>. All rights reserved.
  </footer>


@endsection