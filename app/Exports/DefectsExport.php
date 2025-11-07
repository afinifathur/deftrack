<?php
namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DefectsExport implements FromCollection, WithHeadings
{
    protected Collection $rows;
    public function __construct(Collection $rows){ $this->rows = $rows; }

    public function collection()
    {
        return $this->rows->map(function($r){
            return [
                $r->date,
                $r->department,
                $r->heat_number,
                $r->item_code,
                $r->defect_type,
                $r->qty_pcs,
                $r->qty_kg,
            ];
        });
    }

    public function headings(): array
    {
        return ['date','department','heat_number','item_code','defect_type','qty_pcs','qty_kg'];
    }
}
