<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Entry point handed to Excel::import() for the "Pre-order Cars" sheet.
 *
 * Same WithMultipleSheets trick as BatchCarsImport: pin the import to
 * sheet index 0 explicitly, so an extra "Notes" sheet elsewhere in the
 * workbook is never touched and can't silently overwrite these rows.
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
 * Raw reader for the actual "Pre-order Cars" sheet.
 *
 * No WithHeadingRow (Arabic headers don't slugify reliably into ASCII
 * array keys); every row is read as a plain indexed collection and mapped
 * by POSITION in PreOrderCarsImportService, per this fixed column order.
 *
 * NOTE: unlike BatchCarsRowsImport, there is deliberately NO supplier,
 * owner/customer, VIN, tracking, or any other operational field — only the
 * catalog model, its customer-facing price, and the applicable customs fee
 * (new vs. under-three-years, picked from the row based on manufacture_year):
 *
 *   0  العلامة التجارية                      -> brand
 *   1  الموديل                               -> model
 *   2  الفئة (Finition)                      -> finition
 *   3  سنة الصنع                             -> manufacture_year
 *   4  اللون                                 -> color
 *   5  السعر                                 -> price
 *   6  مصاريف الجمركة (جديدة)                -> customs (when year >= current)
 *   7  مصاريف الجمركة (أقل من 3 سنوات)       -> customs (when age < 3, not new)
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
