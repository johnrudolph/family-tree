<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\MediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Read-only access to every uploaded file (R2/S3 in production, local disk
 * in dev) via Media Library's own `media` table — one signed, short-lived
 * URL per row rather than raw disk access, same as the app itself ever
 * shows a file.
 */
class MediaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Media::query()->orderByDesc('id');

        if ($modelType = $request->query('model_type')) {
            $query->where('model_type', $modelType);
        }

        if ($collection = $request->query('collection_name')) {
            $query->where('collection_name', $collection);
        }

        $perPage = min((int) $request->query('per_page', 25), 100) ?: 25;

        $results = $query->paginate($perPage);
        $results->getCollection()->transform(fn (Media $media) => $this->present($media));

        return response()->json($results);
    }

    public function show(int $id): JsonResponse
    {
        $media = Media::query()->findOrFail($id);

        return response()->json($this->present($media));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Media $media): array
    {
        return [
            'id' => $media->id,
            'model_type' => $media->model_type,
            'model_id' => $media->model_id,
            'collection_name' => $media->collection_name,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'disk' => $media->disk,
            'custom_properties' => $media->custom_properties,
            'created_at' => $media->created_at,
            'url' => MediaUrl::of($media),
        ];
    }
}
