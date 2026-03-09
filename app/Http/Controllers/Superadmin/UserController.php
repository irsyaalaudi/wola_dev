<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Pegawai;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{
    // =========================
    // CLEAN STRING HELPER
    // =========================
    private function cleanString($string)
    {
        if (is_null($string)) return null;

        // ubah ke string biasa
        $string = (string) $string;

        // hapus karakter non-breaking space (U+00A0) dan invisible char lain
        $string = preg_replace('/[\x{00A0}\x{200B}\x{200C}\x{200D}]/u', ' ', $string);

        // ganti koma “aneh” (misalnya koma fullwidth) jadi koma biasa
        $string = str_replace(['，'], ',', $string);

        // rapikan spasi berlebihan
        $string = preg_replace('/\s+/', ' ', $string);

        // trim depan belakang
        return trim($string);
    }
    
    // =========================
    // INDEX
    // =========================
    public function index(Request $request)
    {
        $search = $request->input('search');

        $users = User::with(['pegawai.teams'])
            ->whereHas('pegawai')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('role', 'like', "%{$search}%")
                        ->orWhereHas('pegawai', function ($q2) use ($search) {
                            $q2->where('nip', 'like', "%{$search}%")
                               ->orWhere('jabatan', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('name')
            ->paginate(20);

        $teams = Team::all();

        return view('superadmin.master_user.index', compact('users', 'teams'));
    }

    // =========================
    // STORE
    // =========================
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama'     => 'required',
            'nip'      => 'required|unique:pegawais,nip',
            'jabatan'  => 'required',
            'teams'   => 'required|array|min:1',
            'teams.*' => 'exists:teams,id',
            'leader'   => 'nullable|exists:teams,id',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role'     => 'required|in:superadmin,admin,user',
        ]);

        $user = User::create([
            'name'     => $validated['nama'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role'     => $validated['role'],
        ]);

        $pegawai = Pegawai::create([
            'user_id' => $user->id,
            'nip'     => $validated['nip'],
            'jabatan' => $validated['jabatan'],
        ]);

        // Sinkronisasi tim dan leader
        $syncData = [];
        foreach ($validated['teams'] as $teamId) {
            $isLeader = false;
            if (!empty($validated['leader']) && in_array($teamId, $validated['leader'])) {
                $existingLeader = Team::find($teamId)->pegawais()->wherePivot('is_leader', true)->first();
                if (!$existingLeader) $isLeader = true;
            }
            $syncData[$teamId] = ['is_leader' => $isLeader];
        }
        $pegawai->teams()->sync($syncData);

        return back()->with('success', 'User & Pegawai berhasil ditambahkan.');
    }

    // =========================
    // UPDATE
    // =========================
    public function update(Request $request, $id)
    {
        $user = User::with('pegawai.teams')->findOrFail($id);

        $validated = $request->validate([
            'nama'    => 'required',
            'nip'     => 'required|unique:pegawais,nip,' . $user->pegawai->id, // gunakan pegawai->id, bukan pegawai_id
            'jabatan' => 'required',
            'teams'   => 'required|array|min:1',
            'teams.*' => 'exists:teams,id',
            'leader'  => 'nullable|array',
            'leader.*' => 'exists:teams,id',
            'name'    => 'required',
            'email'   => 'required|email|unique:users,email,' . $id,
            'password' => 'nullable|min:6',
            'role'    => 'required|in:superadmin,admin,user',
        ]);

        $user->pegawai->update([
            'nip'     => $validated['nip'],
            'jabatan' => $validated['jabatan'],
        ]);

        // Sinkronisasi tim + leader
        $syncData = [];
        foreach ($validated['teams'] as $teamId) {
            $isLeader = false;
            if (!empty($validated['leader']) && in_array($teamId, $validated['leader'])) {
                $existingLeader = Team::find($teamId)
                    ->pegawais()
                    ->wherePivot('is_leader', true)
                    ->where('pegawai_id', '!=', $user->pegawai->id)
                    ->first();
                if (!$existingLeader) $isLeader = true;
            }
            $syncData[$teamId] = ['is_leader' => $isLeader];
        }
        $user->pegawai->teams()->sync($syncData);

        // Update user
        $user->update([
            'name'     => $validated['nama'],
            'email'    => $validated['email'],
            'role'     => $validated['role'],
            'password' => !empty($validated['password']) ? Hash::make($validated['password']) : $user->password,
        ]);

        return back()->with('success', 'User & Pegawai berhasil diperbarui.');
    }

    // =========================
    // DELETE
    // =========================
   public function destroy($id)
{
    $user = User::with('pegawai.teams')->findOrFail($id);

    if ($user->pegawai) {
        // putus semua relasi pegawai
        $user->pegawai->teams()->detach();

        // hapus pegawai
        $user->pegawai->delete();
    }

    // hapus user
    $user->delete();

    return redirect()
        ->route('superadmin.master_user.index')
        ->with('success', 'User & seluruh data terkait berhasil dihapus.');
}



// =========================
// EXPORT
// =========================
public function export()
{
    $users = User::with(['pegawai.teams'])->get();

    return Excel::download(new class($users) implements \Maatwebsite\Excel\Concerns\FromCollection, 
                                                       \Maatwebsite\Excel\Concerns\WithHeadings, 
                                                       \Maatwebsite\Excel\Concerns\WithMapping {
        private $users;

        public function __construct($users)
        {
            $this->users = $users;
        }

        // Data yang diexport
        public function collection()
        {
            return $this->users;
        }

        // Header kolom
        public function headings(): array
        {
            return [
                'Nama Pegawai',
                'NIP',
                'Jabatan',
                'Email',
                'Role',
                'Tim',
                'Tim yang Dipimpin'
            ];
        }

        // Mapping data per baris
        public function map($user): array
        {
            return [
                $user->name,
                $user->pegawai->nip ?? '-',
                $user->pegawai->jabatan ?? '-',
                $user->email,
                $user->role,
                $user->pegawai ? $user->pegawai->teams->pluck('nama_tim')->implode(', ') : '-',
                $user->pegawai ? $user->pegawai->teams->where('pivot.is_leader', true)->pluck('nama_tim')->implode(', ') : '-',
            ];
        }
    }, 'users.xlsx');
}

    // =========================
    // IMPORT
    // =========================
    public function import(Request $request)
{
    $request->validate(['file' => 'required|mimes:xlsx,xls']);

    $errors = [];

    $import = new class($errors) implements \Maatwebsite\Excel\Concerns\ToCollection, \Maatwebsite\Excel\Concerns\WithHeadingRow {
        private $errors;

        public function __construct(&$errors)
        {
            $this->errors = &$errors;
        }

        // ✅ PERBAIKAN: Normalisasi yang lebih robust
        private function normalize($string)
        {
            if (is_null($string)) return '';

            $s = (string) $string;

            // hapus karakter invisible/BOM/non-breaking space
            $s = preg_replace('/[\x{00A0}\x{200B}\x{200C}\x{200D}\x{FEFF}\x{00AD}]/u', '', $s);

            // ganti koma fullwidth jadi koma biasa
            $s = str_replace('，', ',', $s);

            // hapus spasi berlebihan
            $s = preg_replace('/\s+/u', ' ', $s);

            // trim & lowercase
            return strtolower(trim($s));
        }

        // ✅ PERBAIKAN: Fuzzy matching untuk nama tim yang panjang
        private function findTeamId($teamName, $teamMap)
        {
            $normalized = $this->normalize($teamName);
            
            if ($normalized === '' || $normalized === '-') return null;

            // 1. Coba exact match dulu
            if (isset($teamMap[$normalized])) {
                return $teamMap[$normalized];
            }

            // 2. Coba tanpa prefix "statistik"
            $withoutPrefix = preg_replace('/^statistik\s+/i', '', $normalized);
            if ($withoutPrefix !== $normalized && isset($teamMap[$withoutPrefix])) {
                return $teamMap[$withoutPrefix];
            }

            // 3. Fuzzy match: cari tim yang mengandung substring (untuk nama panjang)
            foreach ($teamMap as $dbTeamName => $id) {
                // jika nama dari excel adalah bagian dari nama di DB
                if (strlen($normalized) > 10 && strpos($dbTeamName, $normalized) !== false) {
                    return $id;
                }
                
                // atau sebaliknya: nama di DB adalah bagian dari nama excel
                if (strlen($dbTeamName) > 10 && strpos($normalized, $dbTeamName) !== false) {
                    return $id;
                }
            }

            return null;
        }

        public function collection(\Illuminate\Support\Collection $rows)
        {
            // Build team map
            $teams = \App\Models\Team::all();
            $teamMap = [];
            foreach ($teams as $team) {
                $teamMap[$this->normalize($team->nama_tim)] = $team->id;
            }

            foreach ($rows as $index => $row) {
                try {
                    // Validasi email
                    if (empty($row['email'])) {
                        throw new \Exception('Email kosong');
                    }
                    if (\App\Models\User::where('email', $row['email'])->exists()) {
                        throw new \Exception('Email sudah ada');
                    }

                    // Buat User DULU (karena pegawai butuh user_id)
                    $user = \App\Models\User::create([
                        'name'     => $row['nama_pegawai'] ?? 'user',
                        'email'    => $row['email'],
                        'password' => \Illuminate\Support\Facades\Hash::make($row['password'] ?? 'password123'),
                        'role'     => $row['role'] ?? 'user',
                    ]);

                    // Buat pegawai dengan user_id, nama tidak lagi disimpan di pegawais
                    $pegawai = \App\Models\Pegawai::create([
                        'user_id' => $user->id,
                        'nip'     => $row['nip'] ?? null,
                        'jabatan' => $row['jabatan'] ?? null,
                    ]);

                    // ✅ PERBAIKAN: Handle tim dengan delimiter yang lebih smart
                    $teamIds = [];
                    if (!empty($row['tim'])) {
                        $rawTeams = $row['tim'];
                        
                        // Jika tidak ada koma, berarti satu tim saja
                        if (strpos($rawTeams, ',') === false) {
                            $teamId = $this->findTeamId($rawTeams, $teamMap);
                            if ($teamId) $teamIds[] = $teamId;
                        } else {
                            // Ada koma: coba deteksi pola
                            // Jika diawali "Statistik" kemungkinan nama panjang
                            if (stripos(trim($rawTeams), 'statistik') === 0) {
                                // Treat as single team name
                                $teamId = $this->findTeamId($rawTeams, $teamMap);
                                if ($teamId) $teamIds[] = $teamId;
                            } else {
                                // Split by comma
                                $names = array_map('trim', explode(',', $rawTeams));
                                foreach ($names as $nm) {
                                    $teamId = $this->findTeamId($nm, $teamMap);
                                    if ($teamId) $teamIds[] = $teamId;
                                }
                            }
                        }
                        
                        $teamIds = array_values(array_unique($teamIds));
                    }

                    // ✅ Handle tim yang dipimpin (sama seperti di atas)
                    $leaderIds = [];
                    if (!empty($row['tim_yang_dipimpin'])) {
                        $rawLeaders = $row['tim_yang_dipimpin'];
                        
                        if (strpos($rawLeaders, ',') === false) {
                            $teamId = $this->findTeamId($rawLeaders, $teamMap);
                            if ($teamId) $leaderIds[] = $teamId;
                        } else {
                            if (stripos(trim($rawLeaders), 'statistik') === 0) {
                                $teamId = $this->findTeamId($rawLeaders, $teamMap);
                                if ($teamId) $leaderIds[] = $teamId;
                            } else {
                                $names = array_map('trim', explode(',', $rawLeaders));
                                foreach ($names as $nm) {
                                    $teamId = $this->findTeamId($nm, $teamMap);
                                    if ($teamId) $leaderIds[] = $teamId;
                                }
                            }
                        }
                        
                        $leaderIds = array_values(array_unique($leaderIds));
                    }

                    // Sync teams dengan leader check
                    $syncData = [];
                    foreach ($teamIds as $tid) {
                        $existingLeader = \App\Models\Team::find($tid)
                            ->pegawais()
                            ->wherePivot('is_leader', true)
                            ->first();

                        $isLeader = in_array($tid, $leaderIds) && !$existingLeader;
                        $syncData[$tid] = ['is_leader' => $isLeader];
                    }
                    $pegawai->teams()->sync($syncData);

                    // User sudah dibuat sebelum pegawai di atas

                } catch (\Exception $e) {
                    $this->errors[] = "Baris " . ($index + 2) . " gagal: " . $e->getMessage();
                }
            }
        }
    };

    \Maatwebsite\Excel\Facades\Excel::import($import, $request->file('file'));

    if (!empty($errors)) {
        $errorMessage = implode('<br>', $errors);
        return back()->with('error', "Beberapa data gagal diimport:<br>" . $errorMessage);
    }

    return back()->with('success', 'Data user berhasil diimport.');
}
}