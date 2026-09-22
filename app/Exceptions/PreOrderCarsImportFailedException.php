<?php

namespace App\Exceptions;

class PreOrderCarsImportFailedException extends \Exception
{
    /**
     * @param  array<int, array{row:int|null, errors: array<int,string>}>  $errorRows
     */
    public function __construct(private readonly array $errorRows)
    {
        parent::__construct('فشل استيراد سيارات الطلب المسبق، راجع تفاصيل الأخطاء أدناه');
    }

    /**
     * @return array<int, array{row:int|null, errors: array<int,string>}>
     */
    public function errors(): array
    {
        return $this->errorRows;
    }
}
