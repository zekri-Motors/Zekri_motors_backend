<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tag\StoreTagRequest;
use App\Http\Requests\Tag\UpdateTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    /**
     * List tags, optionally filtered by a free-text search (`q` or `search`).
     * Used both for admin management and autocomplete when tagging media.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Tag::class);

        $term = $request->filled('q')
            ? (string) $request->string('q')
            : (string) $request->string('search');

        $tags = Tag::query()
            ->withCount(['carMedia', 'generalMedia'])
            ->when($term !== '', fn ($q) => $q->where('name', 'like', '%' . $term . '%'))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return response()->json(TagResource::collection($tags)->response()->getData(true));
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = Tag::create([
            'name' => trim($request->validated('name')),
        ]);

        return response()->json([
            'message' => 'تمت إضافة التاق بنجاح',
            'data' => new TagResource($tag->loadCount(['carMedia', 'generalMedia'])),
        ], 201);
    }

    public function show(Tag $tag): JsonResponse
    {
        $this->authorize('view', $tag);

        return response()->json([
            'data' => new TagResource($tag->loadCount(['carMedia', 'generalMedia'])),
        ]);
    }

    public function update(UpdateTagRequest $request, Tag $tag): JsonResponse
    {
        $tag->update([
            'name' => trim($request->validated('name')),
        ]);

        return response()->json([
            'message' => 'تم تحديث التاق بنجاح',
            'data' => new TagResource($tag->fresh()->loadCount(['carMedia', 'generalMedia'])),
        ]);
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $this->authorize('delete', $tag);

        $tag->delete();

        return response()->json(['message' => 'تم حذف التاق بنجاح']);
    }
}
