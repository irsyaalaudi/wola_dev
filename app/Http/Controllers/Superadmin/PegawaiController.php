<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Pegawai;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Style\Border;



class PegawaiController extends Controller
{
    public function index(Request $request)
    {
        $search = strtolower($request->input('search'));

        // Gunakan 'teams' plural
        $query = Pegawai::with(['teams', 'user']);

        if ($search) {
            $query->where(function ($q) use ($search) {

                $q->where('nip', 'like', "%{$search}%")
                    ->orWhere('jabatan', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $data = $query->get();

        return view('superadmin.master_pegawai.index', compact('data'));
    }

    public function export()
    {
        $export = new class implements FromCollection, WithHeadings, \Maatwebsite\Excel\Concerns\WithStyles, \Maatwebsite\Excel\Concerns\ShouldAutoSize {
            public function collection()
            {
                $pegawai = Pegawai::with('teams')->get();

                return $pegawai->map(function ($item, $index) {
                    return [
                        'No'           => $index + 1,
                        'Nama Pegawai' => $item->nama,
                        'NIP'          => $item->nip,
                        'Jabatan'      => $item->jabatan,
                        'Tim'          => $item->teams->pluck('nama_tim')->implode(', ') ?: '-',
                    ];
                });
            }

            public function headings(): array
            {
                return [
                    'No',
                    'Nama Pegawai',
                    'NIP',
                    'Jabatan',
                    'Tim',
                ];
            }

            public function styles(Worksheet $sheet)
            {
                $highestRow    = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                // Style header
                $sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                ]);

                // Style isi tabel
                $sheet->getStyle('A2:' . $highestColumn . $highestRow)->applyFromArray([
                    'alignment' => ['vertical' => 'center'],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                ]);

                // Kolom No rata tengah
                $sheet->getStyle('A2:A' . $highestRow)->getAlignment()->setHorizontal('center');

                return [];
            }
        };

        return Excel::download($export, 'data_pegawai.xlsx');
    }
}
