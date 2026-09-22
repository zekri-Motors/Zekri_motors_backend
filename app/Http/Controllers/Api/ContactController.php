<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreContactRequest;
use App\Http\Requests\Contact\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Contact::class);

        $contacts = Contact::query()
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%' . $request->string('search') . '%'))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return response()->json(ContactResource::collection($contacts)->response()->getData(true));
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = Contact::create($request->validated());

        return response()->json([
            'message' => 'تمت إضافة جهة الاتصال بنجاح',
            'data' => new ContactResource($contact),
        ], 201);
    }

    public function show(Contact $contact): JsonResponse
    {
        $this->authorize('view', $contact);

        return response()->json(['data' => new ContactResource($contact)]);
    }

    public function update(UpdateContactRequest $request, Contact $contact): JsonResponse
    {
        $contact->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث جهة الاتصال بنجاح',
            'data' => new ContactResource($contact->fresh()),
        ]);
    }

    public function destroy(Contact $contact): JsonResponse
    {
        $this->authorize('delete', $contact);

        $contact->delete();

        return response()->json(['message' => 'تم حذف جهة الاتصال بنجاح']);
    }
}
