<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * A multi-sheet .xlsx report (REP-7). maatwebsite/excel 4.x requires the Export marker interface.
 */
class Workbook implements Export, WithMultipleSheets
{
    /**
     * @param  list<Sheet>  $sheets
     */
    public function __construct(private readonly array $sheets) {}

    public function sheets(): array
    {
        return $this->sheets;
    }
}
