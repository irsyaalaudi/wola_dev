<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pegawai;
use App\Models\JenisPekerjaan;
use App\Models\Tugas;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Carbon\Carbon;
use App\Exports\TemplateTugasExport;
use App\Imports\TugasImport;

class PekerjaanController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $user = auth()->user();
        $pegawai = $user->pegawai;

        $teams = $pegawai?->teams ?? collect();
        $teamIds = $teams->pluck('id');

        $tugas = Tugas::with(['pegawai', 'jenisPekerjaan', 'realisasi'])
            ->where('asal', $pegawai->nama ?? $user->name)
            ->whereHas('jenisPekerjaan', fn($q) => $q->whereIn('tim_id', $teamIds))
            ->when($search, fn($query) => $query->where(function ($q) use ($search) {
                $q->orWhereHas('pegawai', fn($q2) => $q2->where('nama', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%"))
                  ->orWhereHas('jenisPekerjaan', fn($q3) => $q3->where('nama_pekerjaan', 'like', "%{$search}%"));
            }))
            ->get();

        $pegawaiList = Pegawai::all();

        $jenisPekerjaanModal = JenisPekerjaan::whereHas('team.pegawais', function ($q) use ($pegawai) {
            $q->where('pegawai_team.is_leader', 1)
              ->where('pegawai_team.pegawai_id', $pegawai->id);
        })->whereIn('tim_id', $teamIds)->get();

        return view('admin.pekerjaan.index', [
            'tugas' => $tugas,
            'pegawai' => $pegawaiList,
            'jenisPekerjaanModal' => $jenisPekerjaanModal,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'pegawai_id' => 'required|exists:pegawais,id',
            'jenis_pekerjaan_id' => 'required|exists:jenis_pekerjaans,id',
            'target' => 'required|numeric',
            'satuan' => 'required|string',
            'deadline' => 'required|date',
        ]);

        $pegawai = auth()->user()->pegawai;
        $teams = $pegawai?->teams ?? collect();
        $teamIds = $teams->pluck('id');

        // Validasi: pastikan jenis pekerjaan milik tim user
        $validJenis = JenisPekerjaan::whereIn('tim_id', $teamIds)
            ->pluck('id')
            ->toArray();

        if (!in_array($request->jenis_pekerjaan_id, $validJenis)) {
            return back()->withErrors(['jenis_pekerjaan_id' => 'Jenis pekerjaan tidak valid untuk tim Anda.']);
        }

        $pemberi = $pegawai->nama ?? auth()->user()->name ?? 'Tidak diketahui';

        Tugas::create([
            'pegawai_id' => $request->pegawai_id,
            'jenis_pekerjaan_id' => $request->jenis_pekerjaan_id,
            'target' => $request->target,
            'satuan' => $request->satuan,
            'asal' => $pemberi,
            'deadline' => $request->deadline,
        ]);

        return redirect()->route('admin.pekerjaan.index')->with('success', 'Tugas berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $tugas = Tugas::with('realisasi')->findOrFail($id);

        // Cek apakah sudah dikerjakan
        if ($tugas->realisasi && $tugas->realisasi->realisasi >= $tugas->target) {
            return redirect()->route('admin.pekerjaan.index')
                ->with('error', 'Tugas sudah dikerjakan dan tidak bisa diedit.');
        }

        $request->validate([
            'pegawai_id' => 'required|exists:pegawais,id',
            'jenis_pekerjaan_id' => 'required|exists:jenis_pekerjaans,id',
            'target' => 'required|numeric',
            'satuan' => 'required|string',
            'deadline' => 'required|date',
        ]);

        $pegawai = auth()->user()->pegawai;
        $teams = $pegawai?->teams ?? collect();
        $teamIds = $teams->pluck('id');

        // Validasi: pastikan jenis pekerjaan milik tim user
        $validJenis = JenisPekerjaan::whereIn('tim_id', $teamIds)
            ->pluck('id')
            ->toArray();

        if (!in_array($request->jenis_pekerjaan_id, $validJenis)) {
            return back()->withErrors(['jenis_pekerjaan_id' => 'Jenis pekerjaan tidak valid untuk tim Anda.']);
        }

        $pemberi = $pegawai->nama ?? auth()->user()->name ?? 'Tidak diketahui';

        $tugas->update([
            'pegawai_id' => $request->pegawai_id,
            'jenis_pekerjaan_id' => $request->jenis_pekerjaan_id,
            'target' => $request->target,
            'satuan' => $request->satuan,
            'asal' => $pemberi,
            'deadline' => $request->deadline,
        ]);

        return redirect()->route('admin.pekerjaan.index')->with('success', 'Tugas berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $tugas = Tugas::findOrFail($id);
        $tugas->delete();

        return redirect()->route('admin.pekerjaan.index')->with('success', 'Tugas berhasil dihapus.');
    }

    public function export()
    {
        $teamIds = auth()->user()->teams->pluck('id');

        return Excel::download(new class($teamIds) implements FromCollection, WithHeadings, WithStyles {
            protected $teamIds;
            public function __construct($teamIds)
            {
                $this->teamIds = $teamIds;
            }

            public function collection()
            {
                return Tugas::with(['pegawai.teams', 'jenisPekerjaan'])
                    ->whereHas('pegawai.teams', fn($q) => $q->whereIn('teams.id', $this->teamIds))
                    ->get()
                    ->map(fn($tugas, $index) => [
                        'No' => $index + 1,
                        'Pegawai' => $tugas->pegawai->nama ?? '-',
                        'Jenis Pekerjaan' => $tugas->jenisPekerjaan->nama_pekerjaan ?? '-',
                        'Target' => $tugas->target,
                        'Satuan' => $tugas->satuan,
                        'Pemberi Pekerjaan' => $tugas->asal, // PERBAIKAN: Ubah label
                        'Deadline' => $tugas->deadline ? Carbon::parse($tugas->deadline)->format('d-m-Y') : '-',
                    ]);
            }

            public function headings(): array
            {
                return [
                    'No',
                    'Pegawai',
                    'Jenis Pekerjaan',
                    'Target',
                    'Satuan',
                    'Pemberi Pekerjaan',
                    'Deadline'
                ];
            }

            public function styles(Worksheet $sheet)
            {
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                // Header
                $sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => 'center'],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                ]);

                // Data
                $sheet->getStyle('A2:' . $highestColumn . $highestRow)->applyFromArray([
                    'alignment' => ['horizontal' => 'left'],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                ]);

                // Kolom No rata tengah
                $sheet->getStyle('A2:A' . $highestRow)->getAlignment()->setHorizontal('center');

                return [];
            }
        }, 'tugas.xlsx');
    }
/*     
    public function model(array $row)
{
    if (empty($row['pegawai_id']) || empty($row['jenis_pekerjaan_id'])) {
        return null;
    }

    // Ambil ID dari string "id - nama"
    $pegawaiId = explode(' - ', $row['pegawai_id'])[0] ?? null;
    $jenisId   = explode(' - ', $row['jenis_pekerjaan_id'])[0] ?? null;

    if (!$pegawaiId || !$jenisId) {
        return null;
    }

    $jenis = JenisPekerjaan::where('id', $jenisId)
        ->whereIn('tim_id', $this->teamIds)
        ->first();

    if (!$jenis) {
        return null;
    }

    return new Tugas([
        'pegawai_id' => $pegawaiId,
        'jenis_pekerjaan_id' => $jenisId,
        'target' => $row['target'] ?? 0,
        'satuan' => $jenis->satuan,
        'asal' => auth()->user()->pegawai->nama ?? auth()->user()->name,
        'deadline' => $row['deadline'] ?? null,
    ]);
}
*/

    public function downloadTemplate()
{
    return Excel::download(
        new TemplateTugasExport(auth()->user()),
        'Template_Tugas.xlsx'
    );
}
public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:5120'
        ]);

        $teamIds = auth()->user()->pegawai?->teams->pluck('id') ?? [];

        Excel::import(
            new TugasImport($teamIds),
            $request->file('file')
        );

        return redirect()
            ->route('admin.pekerjaan.index')
            ->with('success', 'Data tugas berhasil diimport.');
    }

}