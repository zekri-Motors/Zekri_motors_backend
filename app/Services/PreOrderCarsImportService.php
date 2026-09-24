<?php

namespace App\Services;

use App\Exceptions\PreOrderCarsImportFailedException;
use App\Imports\PreOrderCarsImport;
use App\Models\PreOrderCar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class PreOrderCarsImportService
{
    private const COL_BRAND = 0;

    private const COL_MODEL = 1;

    private const COL_FINITION = 2;

    private const COL_YEAR = 3;

    private const COL_COLOR = 4;

    private const COL_PRICE = 5;

    /** مصاريف الجمركة — سيارة جديدة (سنة الصنع ≥ السنة الحالية) */
    private const COL_CUSTOMS_NEW = 6;

    /** مصاريف الجمركة — أقل من 3 سنوات (ليست جديدة) */
    private const COL_CUSTOMS_UNDER_THREE = 7;

    /**
     * Import every pre-order car row from the uploaded file as "draft"
     * records (not yet visible to customers — publish() separately when
     * ready). Atomic: if any row fails, nothing from this import is kept.
     *
     * @return array{created: \Illuminate\Support\Collection<int, PreOrderCar>, count: int, errors: array<int, array{row:int|null, errors: array<int,string>}>}
     */
    public function import($file, int $createdBy): array
    {
        $import = new PreOrderCarsImport();
        Excel::import($import, $file);

        return DB::transaction(function () use ($import, $createdBy) {
            if ($import->getRows()->isEmpty()) {
                throw new PreOrderCarsImportFailedException([
                    [
                        'row' => null,
                        'errors' => ['ملف الإكسل لا يحتوي على أي سيارات للطلب المسبق'],
                    ],
                ]);
            }

            $created = collect();
            $errors = [];

            foreach ($import->getRows() as $index => $row) {
                // WithStartRow(2) skips the header, so this maps back to Excel row numbers.
                $rowNumber = $index + 2;

                try {
                    $created->push($this->importRow($row, $createdBy));
                } catch (\Throwable $e) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => [$e->getMessage()],
                    ];
                }
            }

            if ($errors !== [] || $created->isEmpty()) {
                if ($created->isEmpty() && $errors === []) {
                    $errors[] = [
                        'row' => null,
                        'errors' => ['لم يتم إنشاء أي سيارة طلب مسبق من ملف الإكسل'],
                    ];
                }

                throw new PreOrderCarsImportFailedException($errors);
            }

            return [
                'created' => $created,
                'count' => $created->count(),
                'errors' => [],
            ];
        });
    }

    /**
     * @param  Collection<int, mixed>  $row
     */
    private function importRow(Collection $row, int $createdBy): PreOrderCar
    {
        $brand = trim((string) $row->get(self::COL_BRAND));
        $model = trim((string) $row->get(self::COL_MODEL));
        $finition = $this->nullableString($row->get(self::COL_FINITION));
        $year = $row->get(self::COL_YEAR);
        $color = $this->nullableString($row->get(self::COL_COLOR));
        $price = $row->get(self::COL_PRICE);
        $customsNew = $row->get(self::COL_CUSTOMS_NEW);
        $customsUnderThree = $row->get(self::COL_CUSTOMS_UNDER_THREE);

        if ($brand === '' || $model === '') {
            throw new \RuntimeException('العلامة التجارية والموديل حقلان إلزاميان');
        }
        if (! is_numeric($year)) {
            throw new \RuntimeException('سنة الصنع غير صالحة');
        }
        if (! is_numeric($price) || (float) $price < 0) {
            throw new \RuntimeException('السعر غير صالح');
        }

        $manufactureYear = (int) $year;

        if (! PreOrderCar::isEligibleManufactureYear($manufactureYear)) {
            throw new \RuntimeException('الطلب المسبق متاح فقط للسيارات الجديدة أو التي عمرها أقل من 3 سنوات');
        }

        $customsFees = PreOrderCar::resolveCustomsFees(
            $manufactureYear,
            $customsNew,
            $customsUnderThree
        );

        return PreOrderCar::create([
            'supplier_id' => null,
            'container_opener_id' => null,
            'brand' => $brand,
            'model' => $model,
            'finition' => $finition,
            'manufacture_year' => $manufactureYear,
            'color' => $color,
            'price' => (float) $price,
            'customs_fees' => $customsFees,
            'status' => PreOrderCar::STATUS_DRAFT,
            'notes' => null,
            'created_by' => $createdBy,
        ]);
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
