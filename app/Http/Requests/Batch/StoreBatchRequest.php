<?php

namespace App\Http\Requests\Batch;

use App\Models\Batch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Batch::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id'         => ['required', 'integer', 'exists:suppliers,id'],
            'container_opener_id' => ['nullable', 'integer', 'exists:container_openers,id'],
            'purchase_date'       => ['nullable', 'date'],

            // total_cost_foreign is NOT accepted from user input — it is
            // always derived automatically from the batch's cars
            // (see Batch::recomputeTotalCostForeign()). A batch created
            // here has no cars yet, so it starts at 0 until cars are
            // added/imported.

            // exchange_rate is NOT accepted from user input — it is always
            // derived automatically from supplier payments by the model.
            // Any submitted value is silently ignored (not in $fillable).

            'status'  => ['nullable', Rule::in([
                Batch::STATUS_PARTIAL,
                Batch::STATUS_FULLY_PAID,
            ])],
            'notes' => ['nullable', 'string'],

            // The fields below mirror the columns accepted by the Excel
            // batch import. They are nested so the batch and its cars can
            // be created in one atomic request.
            'cars' => ['required', 'array', 'min:1'],
            'cars.*.brand' => ['required', 'string', 'max:100'],
            'cars.*.model' => ['required', 'string', 'max:100'],
            'cars.*.finition' => ['nullable', 'string', 'max:255'],
            'cars.*.manufacture_year' => ['required', 'integer', 'min:1980', 'max:'.(now()->year + 1)],
            'cars.*.color' => ['nullable', 'string', 'max:50'],
            'cars.*.vin' => ['nullable', 'string', 'max:50', 'distinct', 'unique:cars,vin'],
            'cars.*.foreign_purchase_price' => ['required', 'numeric', 'min:0'],
            'cars.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'cars.*.tracking_number' => ['nullable', 'string', 'max:255'],
            'cars.*.customer_name' => ['required', 'string', 'max:255'],
            'cars.*.passport_no' => ['required', 'string', 'max:100'],
            'cars.*.national_id' => ['required', 'string', 'max:100'],
            'cars.*.shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'cars.*.arrival_date' => ['nullable', 'date'],
        ];
    }
}
