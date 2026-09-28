<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Entry point handed to Excel::import() for the "Pre-order Cars" sheet.
 *
 * Pinned to sheet index 0 so any extra sheets (e.g. Notes) in the workbook
 * are never touched.
 */
class PreOrderCarsImport implements WithMultipleSheets
{
    public PreOrderCarsRowsImport $rowsImport;

    public function __construct()
    {
        $this->rowsImport = new PreOrderCarsRowsImport();
    }

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            0 => $this->rowsImport,
        ];
    }

    /**
     * @return Collection<int, Collection<int, mixed>>
     */
    public function getRows(): Collection
    {
        return $this->rowsImport->rows;
    }
}

/**
 * Raw reader for the "Pre-order Cars" sheet.
 *
 * No WithHeadingRow — Arabic headers don't slugify reliably into ASCII keys;
 * every row is read as a plain indexed collection and mapped by POSITION in
 * PreOrderCarsImportService per this fixed column order:
 *
 *   0  العلامة التجارية          -> brand
 *   1  الموديل                   -> model
 *   2  الفئة (Finition)          -> finition
 *   3  سنة الصنع                 -> manufacture_year
 *   4  اللون                     -> color
 *   5  السعر                     -> price
 *   6  جمركة                    -> customs_fees             (سيارات جديدة: سنة الصنع ≥ السنة الحالية)
 *   7  جمركة +3                  -> customs_fees_under_three (أقل من 3 سنوات، وليست جديدة)
 *   8  مدة التجهيز                -> preparation_days
 *   9  مدة الشحن                  -> shipping_days
 *
 * Row 1 is the header row and is skipped via WithStartRow(2).
 */
class PreOrderCarsRowsImport implements ToCollection, WithStartRow
{
    /**
     * @var Collection<int, Collection<int, mixed>>
     */
    public Collection $rows;

    public function __construct()
    {
        $this->rows = collect();
    }

    public function startRow(): int
    {
        return 2;
    }

    /**
     * @param  Collection<int, Collection<int, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        $this->rows = $rows
            ->filter(fn (Collection $row) => $row->filter(fn ($cell) => $cell !== null && $cell !== '')->isNotEmpty())
            ->values();
    }
}
