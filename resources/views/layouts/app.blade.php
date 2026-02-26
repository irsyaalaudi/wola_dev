<!DOCTYPE html>
<html lang="id" class="h-full bg-gray-100">

<head>
    <meta charset="UTF-8">
    <title>{{ ucfirst(Auth::user()->role ?? 'User') }} Panel | WOLA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="{{ asset('logo BPS only.png') }}">

    <!-- Tailwind CSS & Alpine.js -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Font & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>

<body class="h-full" x-data="userModal()">

    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-60 bg-blue-800 text-white flex flex-col px-8 py-10 fixed inset-y-0 left-0 z-40">
            <div class="mb-6 text-center border-b border-blue-600 pb-10">
                <img src="{{ asset('logo.png') }}" alt="Logo" class="w-40 mx-auto">
            </div>

            <nav class="space-y-1 text-sm">
                @if(Auth::user()->role === 'superadmin')
                {{-- Menu Superadmin --}}
                <a href="{{ route('superadmin.dashboard') }}" class="{{ request()->routeIs('superadmin.dashboard') ? 'bg-yellow-400 text-blue-900 font-semibold' : 'hover:bg-blue-700' }} flex items-center px-4 py-2 rounded transition">
                    <i class="fas fa-home w-5"></i>
                    <span class="ml-3">Dashboard</span>
                </a>
                <a href="{{ route('superadmin.progress.index') }}" class="{{ request()->routeIs('superadmin.progress.index') ? 'bg-yellow-400 text-blue-900 font-semibold' : 'hover:bg-blue-700' }} flex items-center px-4 py-2 rounded transition">
                    <i class="fas fa-chart-line w-5"></i>
                    <span class="ml-3">Progress</span>
                </a>
                <a href="{{ route('superadmin.pekerjaan.index') }}" class="{{ request()->routeIs('superadmin.pekerjaan.index') ? 'bg-yellow-400 text-blue-900 font-semibold' : 'hover:bg-blue-700' }} flex items-center px-4 py-2 rounded transition">
                    <i class="fas fa-briefcase w-5"></i>
                    <span class="ml-3">Pekerjaan</span>
                </a>

                {{-- Dropdown Master --}}
                @php
                $isMasterRoute = request()->is('master*') || request()->routeIs(
                'superadmin.master_user.index',
                'superadmin.jenis-pekerjaan.index',
                'superadmin.master_pegawai.index',
                'superadmin.jenis-tim.index'
                );
                @endphp

                <div x-data="{ open: {{ $isMasterRoute ? 'true' : 'false' }} }" x-init="$watch('open', value => localStorage.setItem('masterOpen', value))">
                    <button @click="open = !open" class="flex items-center justify-between w-full px-4 py-2 rounded transition focus:outline-none hover:bg-blue-700" :class="{ 'bg-blue-700': open }">
                        <div class="flex items-center">
                            <i class="fas fa-cogs w-5"></i>
                            <span class="ml-3 font-medium">Master</span>
                        </div>
                        <i :class="{ 'rotate-90': open }" class="fas fa-chevron-right transform transition-transform duration-200"></i>
                    </button>

                    <div x-show="open" x-collapse class="ml-8 mt-2 space-y-1 text-sm">
                        <a href="{{ route('superadmin.master_user.index') }}" class="{{ request()->routeIs('superadmin.master_user.index') ? 'bg-yellow-400 text-blue-900 font-semibold' : 'text-blue-200 hover:bg-blue-700 hover:text-white' }} flex items-center px-3 py-2 rounded transition">
                            <i class="fas fa-users w-4 mr-2"></i>
                            <span>User</span>
                        </a>
                        <a href="{{ route('superadmin.jenis-pekerjaan.index') }}" class="{{ request()->routeIs('superadmin.jenis-pekerjaan.index') ? 'bg-yellow-400 text-blue-900 font-semibold' : 'text-blue-200 hover:bg-blue-700 hover:text-white' }} flex items-center px-3 py-2 rounded transition">
                            <i class="fas fa-tasks w-4 mr-2"></i>
                            <span>Jenis Pekerjaan</span>
                        </a>
                        <a href="{{ route('superadmin.master_pegawai.index') }}" class="{{ request()->routeIs('superadmin.master_pegawai.index') ? 'bg-yellow-400 text-blue-900 font-semibold' : 'text-blue-200 hover:bg-blue-700 hover:text-white' }} flex items-center px-3 py-2 rounded transition">
                            <i class="fas fa-user-tie w-4 mr-2"></i>
                            <span>Pegawai</span>
                        </a>
                        <a href="{{ route('superadmin.jenis-tim.index') }}" class="{{ request()->routeIs('superadmin.jenis-tim.index') ? 'bg-yellow-400 text-blue-900 font-semibold' : 'text-blue-200 hover:bg-blue-700 hover:text-white' }} flex items-center px-3 py-2 rounded transition">
                            <i class="fas fa-users-cog w-4 mr-2"></i>
                            <span>Jenis Tim</span>
                        </a>
                    </div>
                </div>

                <a href="{{ route('superadmin.support') }}" class="{{ request()->routeIs('superadmin.support') ? 'bg-yellow-400 text-blue-900 font-semibold' : 'hover:bg-blue-700' }} flex items-center px-4 py-2 rounded transition">
                    <i class="fas fa-life-ring w-5"></i>
                    <span class="ml-3">Support</span>
                </a>

                @elseif(Auth::user()->role === 'admin')
                {{-- Menu Admin --}}
                <a href="{{ route('admin.dashboard') }}"
                    class="{{ request()->routeIs('admin.dashboard') ? 'bg-yellow-400 text-blue-900 font-semibold' : 'hover:bg-blue-700' }} flex items-center px-4 py-2 rounded transition">
                    <i class="fas fa-home w-5"></i>
                    <span class="ml-3">Dashboard</span>
                </a>
                <a href="{{ route('admin.progress.index') }}"
                    class="{{ request()->routeIs('admin.progress.index') ? 'bg-yellow-400 text-blue-900 font-semibold' : 'hover:bg-blue-700' }} flex items-center px-4 py-2 rounded transition">
                    <i class="fas fa-chart-line w-5"></i>
                    <span class="ml-3">Progress</span>
                </a>
                <a href="{{ route('admin.pekerjaan.index') }}"
                    class="{{ request()->routeIs('admin.pekerjaan.index') ? 'bg-yellow-400 text-blue-900 font-semibold' : 'hover:bg-blue-700' }} flex items-center px-4 py-2 rounded transition">
                    <i class="fas fa-briefcase w-5"></i>
                    <span class="ml-3">Pekerjaan</span>
                </a>


                {{-- Jika admin juga anggota tim, tampilkan menu tambahan --}}
                @if(Auth::user()->pegawai && Auth::user()->pegawai->teams->isNotEmpty())
                <hr class="my-3 border-blue-600">
                <p class="text-xs uppercase tracking-wide text-blue-200 font-semibold px-4">Menu Tim</p>
                <a href="{{ route('user.dashboard') }}"
                    class="{{ request()->routeIs('user.dashboard') ? 'bg-yellow-400 text-blue-900 font-semibold' : 'hover:bg-blue-700' }} flex items-center px-4 py-2 rounded transition">
                    <i class="fas fa-home w-5"></i>
                    <span class="ml-3">Dashboard Tim</span>
                </a>
                <a href="{{ route('user.pekerjaan.index') }}"
                    class="{{ request()->routeIs('user.pekerjaan.index') ? 'bg-yellow-400 text-blue-900 font-semibold' : 'hover:bg-blue-700' }} flex items-center px-4 py-2 rounded transition">
                    <i class="fas fa-briefcase w-5"></i>
                    <span class="ml-3">Pekerjaan Tim</span>
                </a>
                <hr class="my-3 border-blue-600">
                <a href="{{ route('admin.support') }}"
                    class="{{ request()->routeIs('admin.support') ? 'bg-yellow-400 text-blue-900 font-semibold' : 'hover:bg-blue-700' }} flex items-center px-4 py-2 rounded transition">
                    <i class="fas fa-life-ring w-5"></i>
                    <span class="ml-3">Support</span>
                </a>
                @endif



                @elseif(Auth::user()->role === 'user')
                {{-- Menu User --}}
                <a href="{{ route('user.dashboard') }}"
                    class="{{ request()->routeIs('user.dashboard') ? 'bg-yellow-400 text-blue-900 font-semibold' : 'hover:bg-blue-700' }} flex items-center px-4 py-2 rounded transition">
                    <i class="fas fa-home w-5"></i>
                    <span class="ml-3">Dashboard</span>
                </a>

                <a href="{{ route('user.pekerjaan.index') }}"
                    class="{{ request()->routeIs('user.pekerjaan.index') ? 'bg-yellow-400 text-blue-900 font-semibold' : 'hover:bg-blue-700' }} flex items-center px-4 py-2 rounded transition">
                    <i class="fas fa-briefcase w-5"></i>
                    <span class="ml-3">Pekerjaan</span>
                </a>

                <a href="{{ route('user.support') }}"
                    class="{{ request()->routeIs('user.support') ? 'bg-yellow-400 text-blue-900 font-semibold' : 'hover:bg-blue-700' }} flex items-center px-4 py-2 rounded transition">
                    <i class="fas fa-life-ring w-5"></i>
                    <span class="ml-3">Support</span>
                </a>
                @endif

            </nav>

            <!-- Logout Button -->
            <div x-data="{ showLogout: false }" class="mt-auto px-4">
                <button @click="showLogout = true" class="w-full bg-red-600 hover:bg-red-700 text-white py-3 font-semibold rounded-xl mt-6 flex items-center justify-center">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </button>

                <!-- Modal Logout -->
                <div x-show="showLogout" x-transition class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50" style="display: none;">
                    <div @click.outside="showLogout = false" class="bg-white rounded-xl p-6 w-80 shadow-lg text-center">
                        <i class="fas fa-exclamation-circle text-red-500 text-4xl mb-4"></i>
                        <h2 class="text-lg font-semibold mb-1">Yakin ingin logout?</h2>
                        <p class="text-sm text-gray-600">Anda akan keluar dari sesi ini.</p>
                        <div class="flex justify-center space-x-3 mt-5">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded shadow-sm">Yakin</button>
                                <button type="button" @click="showLogout = false" class="bg-gray-300 hover:bg-gray-400 text-black px-4 py-2 rounded shadow-sm">Batal</button>
                            </form>

                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col ml-60">
            <!-- Navbar -->
            <header class="bg-white shadow px-6 py-4 flex justify-between items-center sticky top-0 z-10">
                <h2 class="text-xl font-semibold text-blue-800">
                    @yield('page-title', 'WOLA - Workload Application')
                </h2>

                <div class="flex flex-col items-end text-gray-600 font-medium leading-tight">
                    <div class="text-base">
                        {{ ucfirst(Auth::user()->name ?? 'User') }}
                    </div>
                    <div class="text-sm text-gray-500">
                        @php
                        $role = ucfirst(Auth::user()->role ?? 'Guest');

                        $leaderTeams = Auth::user()?->teams
                        ?->filter(fn($t) => $t->pivot?->is_leader)
                        ->pluck('nama_tim')
                        ->toArray() ?? [];

                        @endphp

                        @if(count($leaderTeams) > 0)
                        {{ $role }} (Ketua Tim {{ implode(', ', $leaderTeams) }})
                        @else
                        {{ $role }}
                        @endif
                    </div>
                </div>
            </header>


            <!-- Page Content -->
            <!-- <main class="flex-1 overflow-auto p-6 bg-gray-100 min-w-0"> -->
            <main class="flex-1 overflow-y-auto overflow-x-hidden p-6 bg-gray-100 min-w-0">
                @yield('content')
            </main>

            {{-- layouts/app.blade.php --}}

            @if(session('show_welcome_popup'))
            @php
            $role = strtolower(Auth::user()->role);
            $roleTitle = ucfirst($role);

            $messages = [
            'superadmin' => [
            'title' => 'Selamat Datang di Panel Superadmin!',
            'desc' => '<strong>WOLA</strong> adalah platform manajemen dan pemantauan kinerja yang dirancang untuk mempermudah pengelolaan pekerjaan secara <em>real-time</em>, transparan, dan efisien bagi Superadmin.'
            ],
            'admin' => [
            'title' => 'Selamat Datang di Panel Admin!',
            'desc' => '<strong>WOLA</strong> membantu Admin memantau dan mengelola pekerjaan dengan cepat, akurat, dan berbasis data.'
            ],
            'pegawai' => [
            'title' => 'Selamat Datang di Panel Pegawai!',
            'desc' => '<strong>WOLA</strong> membantu Pegawai melacak tugas, melaporkan progres, dan bekerja lebih efektif.'
            ],
            ];

            $popup = $messages[$role] ?? [
            'title' => "Selamat Datang di Panel $roleTitle!",
            'desc' => '<strong>WOLA</strong> adalah platform manajemen dan pemantauan kinerja untuk mendukung pekerjaan Anda.'
            ];
            @endphp

            <div
                x-data="{ show: true }"
                x-show="show"
                x-cloak
                x-transition.opacity.duration.300ms
                @click.self="show = false"
                class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 cursor-pointer">
                <div
                    @click.stop
                    class="bg-white max-w-xl w-full mx-4 p-8 rounded-2xl shadow-2xl text-center relative animate-fade-in cursor-default">
                    <!-- Icon -->
                    <img src="{{ asset('icon_dashboard.png') }}" alt="Dashboard Icon"
                        class="w-20 h-20 mx-auto mb-4 rounded-full shadow-lg">

                    <!-- Judul -->
                    <h2 class="text-2xl md:text-3xl font-extrabold text-blue-700 mb-4">
                        {!! $popup['title'] !!}
                    </h2>

                    <!-- Deskripsi -->
                    <p class="text-gray-700 mb-6 leading-relaxed text-sm md:text-base">
                        {!! $popup['desc'] !!}
                    </p>

                    <!-- Tombol Tutup -->
                    <button
                        @click="show = false"
                        class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-full transition duration-200 shadow">
                        Tutup
                    </button>
                </div>
            </div>
            @endif

        </div>
    </div>

</body>

</html>