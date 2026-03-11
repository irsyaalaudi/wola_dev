<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\JenisPekerjaan;
use App\Models\Team;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Exports\TemplateJenisPekerjaanExport;
use App\Imports\JenisPekerjaanImport;


class JenisPekerjaanController extends Controller
{
    public function index(Request $request)
    {
        $teams = Team::whereHas('pegawais', function ($q) {
            $q->where('pegawai_team.is_leader', 1);
        })->get();

        $query = JenisPekerjaan::with('teams');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_pekerjaan', 'like', "%$search%")
                    ->orWhere('satuan', 'like', "%$search%")
                    ->orWhereHas('teams', function ($q2) use ($search) {
                        $q2->where('nama_tim', 'like', "%$search%");
                    });
            });
        }

        $data = $query->get();

        return view('superadmin.master_jenis_pekerjaan.index', compact('data', 'teams'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_pekerjaan' => 'required|string',
            'satuan' => 'required|string',
            'bobot' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
                'regex:/^\d+(\.\d{1,2})?$/'
            ],
            'team_ids' => 'required|array|min:1',
            'team_ids.*' => 'exists:teams,id',
            'pemberi_pekerjaan' => 'nullable|string',
        ]);

        $bobot = str_replace(',', '.', $request->bobot);

        $jenis = JenisPekerjaan::create([
            'nama_pekerjaan' => $request->nama_pekerjaan,
            'satuan' => $request->satuan,
            'bobot' => floatval($bobot),
            'pemberi_pekerjaan' => $request->pemberi_pekerjaan,
        ]);
        $jenis->teams()->sync($request->team_ids);

        return back()->with('success', 'Jenis pekerjaan berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_pekerjaan' => 'required|string|max:255',
            'satuan'         => 'required|string|max:50',
            'bobot' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
                'regex:/^\d+(\.\d{1,2})?$/'
                ],
            'team_ids'       => 'required|array|min:1', 
        ], [
            'team_ids.required' => 'Pilih minimal satu tim.',
        ]);

        $item = JenisPekerjaan::findOrFail($id);

        $bobot = str_replace(',', '.', $request->bobot);

        $item->update([
            'nama_pekerjaan' => $request->nama_pekerjaan,
            'satuan' => $request->satuan,
            'bobot' => floatval($bobot),
        ]);
        
        $item->teams()->sync($request->team_ids);

        return back()->with('success', 'Jenis pekerjaan berhasil diperbarui.');
    }

    public function destroy($id)
    {
        JenisPekerjaan::findOrFail($id)->delete();
        return back()->with('success', 'Jenis pekerjaan berhasil dihapus.');
    }

    public function export()
    {
        return Excel::download(new class implements
            \Maatwebsite\Excel\Concerns\FromCollection,
            \Maatwebsite\Excel\Concerns\WithHeadings,
            \Maatwebsite\Excel\Concerns\ShouldAutoSize,
            \Maatwebsite\Excel\Concerns\WithStyles {
            public function collection()
            {
                $data = JenisPekerjaan::with('teams')->get();

                return $data->values()->map(function ($item, $index) {
                    return [
                        'No' => $index + 1,
                        'Nama Pekerjaan' => $item->nama_pekerjaan,
                        'Satuan' => $item->satuan,
                        'Bobot' => number_format($item->bobot, 2, ',', '.'),
                        'Pemberi Pekerjaan' => $item->pemberi_pekerjaan,
                        'Tim' => $item->teams->pluck('nama_tim')->implode(', ') ?: '-',
                    ];
                });
            }

            public function headings(): array
            {
                return [
                    'No',
                    'Nama Pekerjaan',
                    'Satuan',
                    'Bobot',
                    'Pemberi Pekerjaan',
                    'Tim'
                ];
            }

            public function styles(Worksheet $sheet)
            {
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                $sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => 'center'],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ]
                    ]
                ]);

                $sheet->getStyle('A2:' . $highestColumn . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ]
                    ]
                ]);

                $sheet->getStyle('D2:D' . $highestRow)->getNumberFormat()->setFormatCode('0.00');

                return [];
            }
        }, 'jenis_pekerjaan.xlsx');
    }



    public function downloadTemplate()
    {
        return Excel::download(
            new TemplateJenisPekerjaanExport,
            'Template_Jenis_Pekerjaan.xlsx'
        );
    }

    // public function import(Request $request)
    // {
    //     $request->validate([
    //         'file' => 'required|mimes:xlsx,xls'
    //     ]);

    //     Excel::import(new class implements \Maatwebsite\Excel\Concerns\OnEachRow, WithHeadingRow {
    //         public function onRow(\Maatwebsite\Excel\Row $row)
    //         {
    //             $data = $row->toArray();

    //             if (empty($data['nama_pekerjaan']) && empty($data['nama pekerjaan'])) {
    //                 return;
    //             }

    //             // logika cari team tetap sama seperti sebelumnya
    //             $team = null;
    //             $teamName = $data['tim'] ?? $data['nama_tim'] ?? null;
    //             if ($teamName) {
    //                 $cleanName = preg_replace('/\s+/u', ' ', trim($teamName));
    //                 $cleanName = preg_replace('/[[:^print:]]/u', '', $cleanName);
    //                 $team = Team::whereRaw('LOWER(nama_tim) = ?', [strtolower($cleanName)])->first();
    //             }

    //             $bobot = $data['bobot'] ?? 0;
    //             $bobot = str_replace(',', '.', (string) $bobot);
    //             if (!is_numeric($bobot))
    //                 $bobot = 0;

    //             $jenis = JenisPekerjaan::create([
    //                 'nama_pekerjaan' => $data['nama_pekerjaan'] ?? $data['nama pekerjaan'] ?? null,
    //                 'satuan' => $data['satuan'] ?? null,
    //                 'bobot' => floatval($bobot),
    //                 'pemberi_pekerjaan' => $data['pemberi_pekerjaan'] ?? $data['pemberi pekerjaan'] ?? null,
    //             ]);

    //             if ($team) {
    //                 $jenis->teams()->sync([$team->id]);
    //             }
    //         }
    //     }, $request->file('file'));

    //     return back()->with('success', 'Data Jenis Pekerjaan berhasil diimport.');
    // }


public function import(Request $request)
{
    $request->validate([
        'file' => 'required|mimes:xlsx,xls'
    ]);

    Excel::import(new JenisPekerjaanImport, $request->file('file'));

    return back()->with('success','Import berhasil');
}
    
}
