<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralMedia\StoreGeneralMediaRequest;
use App\Http\Requests\GeneralMedia\UpdateGeneralMediaRequest;
use App\Http\Resources\GeneralMediaResource;
use App\Models\GeneralMedia;
use App\Services\MediaUploadResolver;
use App\Services\TagResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeneralMediaController extends Controller
{
    /**
     * List/filter general media. `car_name` does not apply here (this
     * media isn't linked to any car) — filters are `type`, `tags`, and an
     * optional free-text `q` against the title.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', GeneralMedia::class);

        $media = GeneralMedia::query()
            ->with('tags')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%' . $request->string('q') . '%'))
            ->when($request->filled('tags'), fn ($q) => $q->whereHas(
                'tags',
                fn ($tq) => $tq->whereIn('name', $this->parseTags($request->string('tags')))
            ))
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json(GeneralMediaResource::collection($media)->response()->getData(true));
    }

    public function store(StoreGeneralMediaRequest $request): JsonResponse
    {
        $attributes = MediaUploadResolver::resolve(
            $request->file('file'),
            $request->input('url'),
            $request->input('type'),
            folder: 'general-media',
        );

        $media = GeneralMedia::create($attributes + [
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'sort_order' => $request->integer('sort_order', 0),
            'uploaded_by' => $request->user()->id,
        ]);

        if ($request->filled('tags')) {
            $media->tags()->sync(TagResolver::resolveIds($request->input('tags'), $request->user()));
        }

        return response()->json([
            'message' => 'تمت إضافة الميديا بنجاح',
            'data' => new GeneralMediaResource($media->load('tags')),
        ], 201);
    }

    public function update(UpdateGeneralMediaRequest $request, GeneralMedia $generalMedia): JsonResponse
    {
        $generalMedia->update($request->safe()->except('tags'));

        if ($request->has('tags')) {
            $generalMedia->tags()->sync(TagResolver::resolveIds($request->input('tags', []), $request->user()));
        }

        return response()->json([
            'message' => 'تم تحديث الميديا بنجاح',
            'data' => new GeneralMediaResource($generalMedia->fresh('tags')),
        ]);
    }

    public function destroy(GeneralMedia $generalMedia): JsonResponse
    {
        $this->authorize('delete', $generalMedia);

        $generalMedia->delete();

        return response()->json(['message' => 'تم حذف الميديا بنجاح']);
    }

    /**
     * @return array<int, string>
     */
    private function parseTags(string $raw): array
    {
        return collect(explode(',', $raw))
            ->map(fn ($tag) => trim($tag))
            ->filter(fn ($tag) => $tag !== '')
            ->values()
            ->all();
    }
}
