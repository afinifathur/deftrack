<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class BatchXlsxImport implements ToArray, WithHeadingRow
{
    public function array(array $rows)
    {
        // Cukup return array -> akan diproses di controller
        return $rows;
    }
}
