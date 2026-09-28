<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CarMedia\StoreCarMediaRequest;
use App\Http\Requests\CarMedia\UpdateCarMediaRequest;
use App\Http\Resources\CarMediaResource;
use App\Models\Car;
use App\Models\CarMedia;
use App\Models\PreOrderCar;
use App\Services\MediaUploadResolver;
use App\Services\TagResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarMediaController extends Controller
{
    /**
     * List all media belonging to regular and pre-order cars together.
     */
    public function all(Request $request): JsonResponse
    {
        return $this->search($request);
    }

    /**
     * All media belonging to one specific car, optionally filtered by
     * type and/or tags.
     */
    public function index(Request $request, Car $car): JsonResponse
    {
        $this->authorize('view', $car);

        $media = $car->media()
            ->with('tags')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('tags'), fn ($q) => $q->whereHas(
                'tags',
                fn ($tq) => $tq->whereIn('name', $this->parseTags($request->string('tags')))
            ))
            ->orderByDesc('is_cover')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => CarMediaResource::collection($media)]);
    }

    public function store(StoreCarMediaRequest $request, Car $car): JsonResponse
    {
        $attributes = MediaUploadResolver::resolve(
            $request->file('file'),
            $request->input('url'),
            $request->input('type'),
            folder: 'car-media',
        );

        $media = $car->media()->create($attributes);

        if ($request->filled('tags')) {
            $media->tags()->sync(TagResolver::resolveIds($request->input('tags'), $request->user()));
        }

        return response()->json([
            'message' => 'تمت إضافة الميديا بنجاح',
            'data' => new CarMediaResource($media->load('tags')),
        ], 201);
    }

    /**
     * All media belonging to one pre-order catalog car.
     */
    public function preOrderIndex(Request $request, PreOrderCar $preOrderCar): JsonResponse
    {
        $this->authorize('view', $preOrderCar);

        $media = $preOrderCar->media()
            ->with('tags')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('tags'), fn ($q) => $q->whereHas(
                'tags',
                fn ($tq) => $tq->whereIn('name', $this->parseTags($request->string('tags')))
            ))
            ->orderByDesc('is_cover')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => CarMediaResource::collection($media)]);
    }

    /**
     * Attach media to a pre-order catalog car.
     */
    public function storeForPreOrder(StoreCarMediaRequest $request, PreOrderCar $preOrderCar): JsonResponse
    {
        $this->authorize('view', $preOrderCar);

        $attributes = MediaUploadResolver::resolve(
            $request->file('file'),
            $request->input('url'),
            $request->input('type'),
            folder: 'car-media',
        );

        $media = $preOrderCar->media()->create($attributes + [
            'title' => $request->input('title'),
            'is_cover' => $request->boolean('is_cover'),
            'sort_order' => $request->integer('sort_order', 0),
            'uploaded_by' => $request->user()->id,
        ]);

        if ($request->filled('tags')) {
            $media->tags()->sync(TagResolver::resolveIds($request->input('tags'), $request->user()));
        }

        if ($media->is_cover) {
            $preOrderCar->media()->where('id', '!=', $media->id)->update(['is_cover' => false]);
        }

        return response()->json([
            'message' => 'تمت إضافة الميديا بنجاح',
            'data' => new CarMediaResource($media->load(['tags', 'preOrderCar'])),
        ], 201);
    }

    public function update(UpdateCarMediaRequest $request, CarMedia $carMedia): JsonResponse
    {
        $carMedia->update($request->safe()->except('tags'));

        if ($request->has('tags')) {
            $carMedia->tags()->sync(TagResolver::resolveIds($request->input('tags', []), $request->user()));
        }

        if ($request->boolean('is_cover')) {
            $ownerMedia = $carMedia->car_id !== null
                ? $carMedia->car->media()
                : $carMedia->preOrderCar->media();

            $ownerMedia->where('id', '!=', $carMedia->id)->update(['is_cover' => false]);
        }

        return response()->json([
            'message' => 'تم تحديث الميديا بنجاح',
            'data' => new CarMediaResource($carMedia->fresh('tags')),
        ]);
    }

    public function destroy(CarMedia $carMedia): JsonResponse
    {
        $this->authorize('delete', $carMedia);

        // Model's deleting() event removes the underlying file from disk
        // (when the row owns one) and detaches its tags.
        $carMedia->delete();

        return response()->json(['message' => 'تم حذف الميديا بنجاح']);
    }

    /**
     * Search car-linked media across the whole system by the car's
     * name (brand/model), the media type, and/or its tags.
     *
     * GET /car-media/search?car_name=Camry&type=image&tags=خارجية,محرك
     */
    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CarMedia::class);

        $media = CarMedia::query()
            ->with([
                'car:id,brand,model,manufacture_year,vin',
                'preOrderCar:id,brand,model,manufacture_year',
                'tags',
            ])
            ->when($request->filled('car_name'), function ($q) use ($request) {
                $term = $request->string('car_name');
                $q->where(function ($mediaQuery) use ($term) {
                    $mediaQuery->whereHas('car', function ($carQuery) use ($term) {
                        $carQuery->where('brand', 'like', "%{$term}%")
                            ->orWhere('model', 'like', "%{$term}%");
                    })->orWhereHas('preOrderCar', function ($preOrderCarQuery) use ($term) {
                        $preOrderCarQuery->where('brand', 'like', "%{$term}%")
                            ->orWhere('model', 'like', "%{$term}%");
                    });
                });
            })
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('tags'), fn ($q) => $q->whereHas(
                'tags',
                fn ($tq) => $tq->whereIn('name', $this->parseTags($request->string('tags')))
            ))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json(CarMediaResource::collection($media)->response()->getData(true));
    }

    /**
     * "خارجية,محرك" -> ['خارجية', 'محرك'], trimmed and empties dropped.
     *
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
