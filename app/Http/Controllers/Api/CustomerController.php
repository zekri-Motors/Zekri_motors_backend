<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerDocumentResource;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\CustomerDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CustomerController extends Controller
{
    /**
     * List customers, scoped by who's asking:
     *   - admin/super-admin (customers.view): every customer.
     *   - agent (customers.view_assigned only): only their own customers.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        $user = $request->user();

        // Whoever can see orders (in whatever scope) can see how much of
        // them is still unpaid — same permission gate CustomerResource
        // already expects for its on-the-fly total_remaining calculation,
        // so we simply pre-compute it here to avoid N+1 queries.
        $canSeeRemaining = $user->can('orders.view') || $user->can('orders.view_assigned');

        $query = Customer::query()
            ->withCount('orders')
            ->when($canSeeRemaining, fn($q) => $q->withSum('orders as total_remaining_sum', 'remaining_amount'))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search');
                $q->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('national_id', 'like', "%{$term}%");
                });
            });

        if ($user->agent) {
            $query->where('agent_id', $user->agent->id);
        } elseif ($user->can('customers.view')) {
            $query->when($request->filled('agent_id'), fn($q) => $q->where('agent_id', $request->integer('agent_id')));
        } elseif ($user->can('customers.view_assigned')) {
            $query->where('agent_id', $user->agent?->id ?? 0);
        }

        $customers = $query->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return response()->json(CustomerResource::collection($customers)->response()->getData(true));
    }

    /**
     * Create a customer account — full automated flow:
     *
     *  1. Generate a random secure password.
     * Create a customer profile record.
     *
     * Customers are NOT system users — they have no login and no role.
     * They track their orders via the public passport-lookup endpoint.
     * This endpoint simply creates the customer profile (name, phone,
     * passport, etc.) and optionally links them to an agent.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        
        
        if ($request->user()->agent) {
            $agentId = $request->user()->agent->id;
        } elseif ($request->user()->can('customers.view')) {
            $agentId = $request->validated('agent_id');
        }

        $customer = Customer::create([
            'agent_id'    => $agentId ?? null,
            'name'        => $request->validated('name'),
            'email'       => $request->validated('email'),
            'phone'       => $request->validated('phone'),
            'national_id' => $request->validated('national_id'),
            'passport_no' => $request->validated('passport_no'),
            'address'     => $request->validated('address'),
        ]);

        return response()->json([
            'message' => 'تم إضافة العميل بنجاح',
            'data'    => new CustomerResource($customer->load('agent')),
        ], 201);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);
 
        $customer->load([
            'agent',
            'orders.car',
            'orders.car.firstOrder.customer',
            'orders.car.currentOrder.customer',
            'customerDocuments',
        ]);
 
        if ($request->user()->can('customer_payments.view')) {
            $customer->load('payments');
        }
 
        return response()->json([
            'data' => new CustomerResource($customer),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث بيانات العميل بنجاح',
            'data'    => new CustomerResource($customer->load('agent')),
        ]);
    }

    /**
     * Delete a customer. Blocked if they have any orders.
     * Also soft-deletes the linked User account so they can no longer log in.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        if ($customer->orders()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف العميل لوجود طلبات مرتبطة به',
            ], 422);
        }

        $customer->delete($customer->id);

        return response()->json(['message' => 'تم حذف العميل بنجاح']);
    }

    // ------------------------------------------------------------------
    // Customer Documents (profile files)
    // ------------------------------------------------------------------

    /**
     * List all documents attached to a customer's profile.
     */
    public function documents(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return response()->json([
            'data' => CustomerDocumentResource::collection(
                $customer->customerDocuments()->latest()->get()
            ),
        ]);
    }

    /**
     * Upload one or more documents to a customer's profile.
     *
     * Accepts multipart/form-data with field `files[]` (multiple files).
     * Each file is stored under `customer-documents/{customer_id}/`
     * on the configured default Storage disk (usually `local` in dev,
     * `s3` in production — set FILESYSTEM_DISK in .env).
     */
    public function uploadDocuments(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $files = $request->file('files');
        if (empty($files)) {
            return response()->json([
                'message' => 'يجب إرسال ملفات للرفع.',
                'errors' => ['files' => ['يجب إرسال ملفات للرفع.']]
            ], 422);
        }

        $fileArray = is_array($files) ? $files : [$files];
        $titles = $request->input('titles', []);
        if (!is_array($titles)) {
            $titles = [$titles];
        }

        $validator = \Illuminate\Support\Facades\Validator::make(
            [
                'files' => $fileArray,
                'titles' => $titles,
            ],
            [
                'files'    => ['required', 'array'],
                'files.*'  => ['required', 'file', 'max:10240'], // 10 MB per file
                'titles'   => ['nullable', 'array'],
                'titles.*' => ['nullable', 'string', 'max:150'],
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $uploaded = [];

        foreach ($fileArray as $index => $file) {
            $path = $file->store("customer-documents/{$customer->id}", 'public');

            $doc = CustomerDocument::create([
                'customer_id' => $customer->id,
                'title'       => data_get($titles, $index)
                    ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'file_path'   => $path,
                'file_type'   => $file->getMimeType(),
                'file_size'   => $this->humanFileSize($file->getSize()),
                'uploaded_by' => $request->user()->id,
            ]);

            $uploaded[] = $doc;
        }

        return response()->json([
            'message' => 'تم رفع ' . count($uploaded) . ' ملف/ملفات بنجاح',
            'data'    => CustomerDocumentResource::collection(collect($uploaded)),
        ], 201);
    }

    /**
     * Delete a single customer document.
     * Removes both the DB record and the physical file.
     */
    public function deleteDocument(Customer $customer, CustomerDocument $document): JsonResponse
    {
        $this->authorize('update', $customer);

        if ($document->customer_id !== $customer->id) {
            return response()->json(['message' => 'الملف لا ينتمي لهذا العميل'], 404);
        }

        Storage::disk('public')->delete($document->file_path);
        $document->delete($customer->id);

        return response()->json(['message' => 'تم حذف الملف بنجاح']);
    }

    // ------------------------------------------------------------------

    protected function humanFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1) . ' ' . $units[$i];
    }
}