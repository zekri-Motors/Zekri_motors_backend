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
    // ── Column positions (0-indexed, matching the fixed Excel layout) ──────────

    private const COL_BRAND    = 0;  // العلامة التجارية
    private const COL_MODEL    = 1;  // الموديل
    private const COL_FINITION = 2;  // الفئة (Finition)
    private const COL_YEAR     = 3;  // سنة الصنع
    private const COL_COLOR    = 4;  // اللون
    private const COL_PRICE    = 5;  // السعر

    /** جمركة — سيارات جديدة (سنة الصنع ≥ السنة الحالية) */
    private const COL_CUSTOMS_NEW         = 6;

    /** جمركة +3 — أقل من 3 سنوات (وليست جديدة) */
    private const COL_CUSTOMS_UNDER_THREE = 7;

    /** مدة التجهيز (بالأيام) */
    private const COL_PREPARATION_DAYS   = 8;

    /** مدة الشحن (بالأيام) */
    private const COL_SHIPPING_DAYS      = 9;

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Import every pre-order car row from the uploaded file as "draft" records
     * (not yet visible to customers — publish() separately when ready).
     * Atomic: if any row fails, nothing from this import is kept.
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
                        'row'    => null,
                        'errors' => ['ملف الإكسل لا يحتوي على أي سيارات للطلب المسبق'],
                    ],
                ]);
            }

            $created = collect();
            $errors  = [];

            foreach ($import->getRows() as $index => $row) {
                // WithStartRow(2) skips the header, so this maps back to Excel row numbers.
                $rowNumber = $index + 2;

                try {
                    $created->push($this->importRow($row, $createdBy));
                } catch (\Throwable $e) {
                    $errors[] = [
                        'row'    => $rowNumber,
                        'errors' => [$e->getMessage()],
                    ];
                }
            }

            if ($errors !== [] || $created->isEmpty()) {
                if ($created->isEmpty() && $errors === []) {
                    $errors[] = [
                        'row'    => null,
                        'errors' => ['لم يتم إنشاء أي سيارة طلب مسبق من ملف الإكسل'],
                    ];
                }

                throw new PreOrderCarsImportFailedException($errors);
            }

            return [
                'created' => $created,
                'count'   => $created->count(),
                'errors'  => [],
            ];
        });
    }

    /**
     * @param  Collection<int, mixed>  $row
     */
    private function importRow(Collection $row, int $createdBy): PreOrderCar
    {
        $brand    = trim((string) $row->get(self::COL_BRAND));
        $model    = trim((string) $row->get(self::COL_MODEL));
        $finition = $this->nullableString($row->get(self::COL_FINITION));
        $year     = $row->get(self::COL_YEAR);
        $color    = $this->nullableString($row->get(self::COL_COLOR));
        $price    = $row->get(self::COL_PRICE);

        // PhpSpreadsheet يُعيد null للخلايا الفارغة وأحياناً للخلايا التي تحتوي 0؛
        // نُعالج null كـ 0 للحقول العددية الاختيارية (جمركة، مدة التجهيز، مدة الشحن).
        $customsNew        = $row->get(self::COL_CUSTOMS_NEW)        ?? 0;
        $customsUnderThree = $row->get(self::COL_CUSTOMS_UNDER_THREE) ?? 0;
        $preparationDays   = $row->get(self::COL_PREPARATION_DAYS)   ?? '';
        $shippingDays      = $row->get(self::COL_SHIPPING_DAYS)      ?? '';

        // ── Basic validations ─────────────────────────────────────────────────

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

        // ── Customs fees ──────────────────────────────────────────────────────

        if (! is_numeric($customsNew) || (float) $customsNew < 0) {
            throw new \RuntimeException('قيمة جمركة (سيارات جديدة) غير صالحة');
        }

        if (! is_numeric($customsUnderThree) || (float) $customsUnderThree < 0) {
            throw new \RuntimeException('قيمة جمركة +3 (أقل من 3 سنوات) غير صالحة');
        }

        // ── Preparation & shipping days ───────────────────────────────────────

        if (mb_strlen((string) $preparationDays) > 255) {
            throw new \RuntimeException('مدة التجهيز طويلة جداً (الحد الأقصى 255 حرفاً)');
        }

        if (mb_strlen((string) $shippingDays) > 255) {
            throw new \RuntimeException('مدة الشحن طويلة جداً (الحد الأقصى 255 حرفاً)');
        }

        // ── Persist ───────────────────────────────────────────────────────────

        return PreOrderCar::create([
            'brand'                    => $brand,
            'model'                    => $model,
            'finition'                 => $finition,
            'manufacture_year'         => $manufactureYear,
            'color'                    => $color,
            'price'                    => (float) $price,
            'customs_fees'             => (float) $customsNew,
            'customs_fees_under_three' => (float) $customsUnderThree,
            'preparation_days'         => (string) $preparationDays,
            'shipping_days'            => (string) $shippingDays,
            'created_by'               => $createdBy,
        ]);
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
