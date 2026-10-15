<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

/**
 * Querying — and, for a deliberately narrow set of models, writing — any
 * model listed in config/api.php. There is no destroy anywhere, and
 * store/update only work for models config/api.php marks `writable`,
 * restricted to that model's own fillable columns either way.
 */
class ModelController extends Controller
{
    /**
     * Lists every model key this API exposes, with its allowed ?with=
     * relations and current row count — a map of what's queryable.
     */
    public function listAvailable(): JsonResponse
    {
        $models = collect(Config::get('api.models'))
            ->map(fn (array $config, string $key) => [
                'key' => $key,
                'relations' => $config['relations'],
                'count' => $config['model']::query()->count(),
            ])
            ->values();

        return response()->json($models);
    }

    public function index(string $model, Request $request): JsonResponse
    {
        $config = $this->resolve($model);

        /** @var class-string<Model> $class */
        $class = $config['model'];
        $query = $class::query();

        foreach ((array) $request->input('filter', []) as $field => $value) {
            if (in_array($field, $this->filterableColumns($class), true)) {
                $query->where($field, $value);
            }
        }

        $with = array_values(array_intersect(
            array_filter(explode(',', (string) $request->query('with', ''))),
            $config['relations'],
        ));

        if ($with) {
            $query->with($with);
        }

        $perPage = min((int) $request->query('per_page', 25), 100) ?: 25;

        return response()->json($query->paginate($perPage));
    }

    public function show(string $model, int|string $id, Request $request): JsonResponse
    {
        $config = $this->resolve($model);

        /** @var class-string<Model> $class */
        $class = $config['model'];
        $query = $class::query();

        $with = array_values(array_intersect(
            array_filter(explode(',', (string) $request->query('with', ''))),
            $config['relations'],
        ));

        if ($with) {
            $query->with($with);
        }

        $record = $query->findOrFail($id);

        return response()->json($record);
    }

    public function store(string $model, Request $request): JsonResponse
    {
        $config = $this->resolveWritable($model);

        /** @var class-string<Model> $class */
        $class = $config['model'];

        $data = $request->only((new $class)->getFillable());

        $record = $class::create($data);

        return response()->json($record, 201);
    }

    public function update(string $model, int|string $id, Request $request): JsonResponse
    {
        $config = $this->resolveWritable($model);

        /** @var class-string<Model> $class */
        $class = $config['model'];
        $record = $class::query()->findOrFail($id);

        $data = $request->only((new $class)->getFillable());

        $record->update($data);

        return response()->json($record);
    }

    /**
     * @return array{model: class-string<Model>, relations: array<int, string>}
     */
    private function resolve(string $model): array
    {
        $config = Config::get("api.models.{$model}");

        abort_unless($config, 404, "Unknown model \"{$model}\". See GET /api/v1/models for the list.");

        return $config;
    }

    /**
     * @return array{model: class-string<Model>, relations: array<int, string>}
     */
    private function resolveWritable(string $model): array
    {
        $config = $this->resolve($model);

        abort_unless($config['writable'] ?? false, 403, "Model \"{$model}\" is read-only through this API.");

        return $config;
    }

    /**
     * @param  class-string<Model>  $class
     * @return array<int, string>
     */
    private function filterableColumns(string $class): array
    {
        return [...(new $class)->getFillable(), 'id'];
    }
}
