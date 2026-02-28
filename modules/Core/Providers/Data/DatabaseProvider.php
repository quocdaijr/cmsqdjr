<?php

namespace Modules\Core\Providers\Data;

use Modules\Core\Contracts\DataProviderInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DatabaseProvider implements DataProviderInterface
{
    public function __construct(protected Model $model)
    {
    }

    public function search(array $filters): array
    {
        $query = $this->buildQuery($filters);

        $results = $query->get();

        return $this->formatResponse($results);
    }

    public function find(int $id): ?array
    {
        $result = $this->model->find($id);

        if (!$result) {
            return null;
        }

        return [
            '_id' => $result->id,
            '_source' => $result->toArray(),
        ];
    }

    public function paginate(array $filters, int $perPage = 15, int $page = 1): array
    {
        $query = $this->buildQuery($filters);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'hits' => $this->formatResponse($paginator->items())['hits'],
        ];
    }

    /**
     * Build Eloquent query from filters
     */
    protected function buildQuery(array $filters): Builder
    {
        $query = $this->model->query();

        // Keyword search (title, content, description)
        if (isset($filters['keyword']) && !empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                  ->orWhere('content', 'like', "%{$keyword}%")
                  ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        // Name search (for categories/tags)
        if (isset($filters['name']) && !empty($filters['name'])) {
            $query->where('name', 'like', "%{$filters['name']}%");
        }

        // Status filter (for posts: published = 1)
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Category filter (many-to-many)
        if (isset($filters['category_ids']) && !empty($filters['category_ids'])) {
            $query->whereHas('categories', function ($q) use ($filters) {
                $q->whereIn('categories.id', (array)$filters['category_ids']);
            });
        }

        // Tag filter (many-to-many)
        if (isset($filters['tag_ids']) && !empty($filters['tag_ids'])) {
            $query->whereHas('tags', function ($q) use ($filters) {
                $q->whereIn('tags.id', (array)$filters['tag_ids']);
            });
        }

        // Date range filter
        if (isset($filters['date_from'])) {
            $query->where('published_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->where('published_at', '<=', $filters['date_to']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        // Handle published_at for models that have it
        if (method_exists($this->model, 'getTable')) {
            $table = $this->model->getTable();
            $columns = \Schema::getColumnListing($table);
            if (in_array('published_at', $columns)) {
                $sortBy = $filters['sort_by'] ?? 'published_at';
            }
        }

        $query->orderBy($sortBy, $sortOrder);

        // Load relationships (like Elasticsearch embedded data)
        $with = [];
        if (method_exists($this->model, 'categories')) {
            $with[] = 'categories';
        }
        if (method_exists($this->model, 'tags')) {
            $with[] = 'tags';
        }
        if (method_exists($this->model, 'files')) {
            $with[] = 'files';
        }
        if (method_exists($this->model, 'user')) {
            $with[] = 'user';
        }

        if (!empty($with)) {
            $query->with($with);
        }

        return $query;
    }

    /**
     * Format response to match Elasticsearch structure
     */
    protected function formatResponse($results): array
    {
        $items = is_array($results) ? $results : $results->toArray();

        return [
            'total' => count($items),
            'hits' => collect($items)->map(function ($item) {
                return [
                    '_id' => $item['id'] ?? $item->id,
                    '_source' => is_array($item) ? $item : $item->toArray(),
                ];
            })->toArray(),
        ];
    }
}
