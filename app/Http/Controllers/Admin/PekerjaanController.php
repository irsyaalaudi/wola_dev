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
use PhpOffice\PhpSpreadsheet\Shared\Date;

class PekerjaanController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $user = auth()->user();
        $pegawai = $user->pegawai;

        $teams = $pegawai?->teams ?? collect();
        $teamIds = $teams->pluck('id');

        $tugas = Tugas::with(['pegawai', 'jenisPekerjaan', 'realisasi'])
            ->where('asal', $user->name)
            ->whereHas('jenisPekerjaan.teams', fn($q) => $q->whereIn('teams.id', $teamIds))
            ->when($status, function ($query) use ($status) {
                return $query->where('status', $status);
            })
            ->when($search, fn($query) => $query->where(function ($q) use ($search) {
                $q->orWhereHas('pegawai', fn($q2) => $q2->whereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"))
                    ->orWhere('nip', 'like', "%{$search}%"))
                    ->orWhereHas('jenisPekerjaan', fn($q3) => $q3->where('nama_pekerjaan', 'like', "%{$search}%"));
            }))
            ->get();

        $pegawaiList = Pegawai::with(['user', 'teams'])
            ->whereHas('teams.pegawais', function ($q) use ($pegawai) {
                $q->where('pegawai_team.pegawai_id', $pegawai->id)
                    ->where('pegawai_team.is_leader', 1);
            })
            ->get();

        $jenisPekerjaanModal = JenisPekerjaan::whereHas('teams', function ($q) use ($pegawai) {
            $q->whereHas('pegawais', function ($q2) use ($pegawai) {
                $q2->where('pegawai_team.pegawai_id', $pegawai->id)
                    ->where('pegawai_team.is_leader', 1);
            });
        })->with('teams')->get();

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
            'start_date' => 'required|date',
            'deadline' => 'required|date',
        ]);

        $pemberi = auth()->user()->name;

        $startDate = Carbon::parse($request->start_date);

        $deadline = Carbon::parse($request->deadline);
        Tugas::create([
            'pegawai_id' => $request->pegawai_id,
            'jenis_pekerjaan_id' => $request->jenis_pekerjaan_id,
            'target' => $request->target,
            'asal' => $pemberi,
            'start_date' => $startDate,
            'deadline' => $deadline,
            'status' => 'pending',
        ]);

        return redirect()->route('admin.pekerjaan.index')
            ->with('success', 'Tugas berhasil ditambahkan');
    }

    public function update(Request $request, $id)
    {
        $tugas = Tugas::findOrFail($id);

        if (in_array($tugas->status, ['waiting_approval', 'done'])) {
            return redirect()->route('admin.pekerjaan.index')
                ->with('error', 'Tugas sudah dikerjakan dan tidak bisa diedit.');
        }

        $request->validate([
            'pegawai_id' => 'required|exists:pegawais,id',
            'jenis_pekerjaan_id' => 'required|exists:jenis_pekerjaans,id',
            'target' => 'required|numeric',
            'start_date' => 'required|date',
            'deadline' => 'required|date'
        ]);

        $startDate = Carbon::parse($request->start_date);

        $deadline = Carbon::parse($request->deadline);

        $tugas->update([
            'pegawai_id' => $request->pegawai_id,
            'jenis_pekerjaan_id' => $request->jenis_pekerjaan_id,
            'target' => $request->target,
            'asal' => auth()->user()->name,
            'start_date' => $startDate,
            'deadline' => $deadline,
            'status' => 'pending',
        ]);

        return redirect()->route('admin.pekerjaan.index')
            ->with('success', 'Tugas berhasil diupdate')
            ->with('scroll_to', $tugas->id);
    }

    public function destroy($id)
    {
        $tugas = Tugas::findOrFail($id);
        $tugas->delete();

        return redirect()->route('admin.pekerjaan.index')->with('success', 'Tugas berhasil dihapus.');
    }

    public function export()
    {
        $teamIds = auth()->user()->pegawai->teams()
            ->wherePivot('is_leader', 1)
            ->pluck('teams.id');

        return Excel::download(
            new class($teamIds) implements FromCollection, WithHeadings, WithStyles {

                protected $teamIds;

                public function __construct($teamIds)
                {
                    $this->teamIds = $teamIds;
                }

                public function collection()
                {
                    return Tugas::with(['pegawai.teams', 'jenisPekerjaan.teams'])
                        ->whereHas('pegawai.teams', function ($q) {
                            $q->whereIn('teams.id', $this->teamIds);
                        })
                        ->whereHas('jenisPekerjaan.teams', function ($q) {
                            $q->whereIn('teams.id', $this->teamIds);
                        })
                        ->get()
                        ->map(fn($tugas, $index) => [
                            'No' => $index + 1,
                            'Pegawai' => $tugas->pegawai->user->name ?? '-',
                            'Jenis Pekerjaan' => $tugas->jenisPekerjaan->nama_pekerjaan ?? '-',
                            'Target' => $tugas->target,
                            'Satuan' => $tugas->jenisPekerjaan->satuan ?? '-',
                            'Pemberi Pekerjaan' => $tugas->asal,
                            'Deadline' => $tugas->deadline
                                ? \Carbon\Carbon::parse($tugas->deadline)->format('d-m-Y')
                                : '-',
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

                    $sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray([
                        'font' => ['bold' => true],
                        'alignment' => ['horizontal' => 'center'],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                    ]);

                    $sheet->getStyle('A2:' . $highestColumn . $highestRow)->applyFromArray([
                        'alignment' => ['horizontal' => 'left'],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                    ]);

                    $sheet->getStyle('A2:A' . $highestRow)
                        ->getAlignment()
                        ->setHorizontal('center');

                    return [];
                }
            },
            'tugas.xlsx'
        );
    }

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

        try {

            $teamIds = auth()->user()->pegawai->teams->pluck('id');

            Excel::import(
                new TugasImport($teamIds),
                $request->file('file')
            );

            return redirect()->route('admin.pekerjaan.index')
                ->with('success', 'Data tugas berhasil diimport.');
        } catch (\Exception $e) {

            return redirect()->route('admin.pekerjaan.index')
                ->with('error', $e->getMessage());
        }
    }
}
