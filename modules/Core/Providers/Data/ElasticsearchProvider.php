<?php

namespace Modules\Core\Providers\Data;

use Modules\Core\Contracts\DataProviderInterface;

class ElasticsearchProvider implements DataProviderInterface
{
    protected $client;
    protected string $index;

    public function __construct($client, string $index)
    {
        $this->client = $client;
        $this->index = $index;
    }

    public function search(array $filters): array
    {
        // Build Elasticsearch query
        $params = [
            'index' => $this->index,
            'body' => $this->buildEsQuery($filters),
        ];

        $response = $this->client->search($params);

        return [
            'total' => $response['hits']['total']['value'] ?? 0,
            'hits' => $response['hits']['hits'] ?? [],
        ];
    }

    public function find(int $id): ?array
    {
        try {
            $response = $this->client->get([
                'index' => $this->index,
                'id' => $id,
            ]);

            return $response;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function paginate(array $filters, int $perPage = 15, int $page = 1): array
    {
        $from = ($page - 1) * $perPage;

        $params = [
            'index' => $this->index,
            'body' => $this->buildEsQuery($filters),
            'size' => $perPage,
            'from' => $from,
        ];

        $response = $this->client->search($params);

        $total = $response['hits']['total']['value'] ?? 0;

        return [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int)ceil($total / $perPage),
            'hits' => $response['hits']['hits'] ?? [],
        ];
    }

    /**
     * Build Elasticsearch query from filters
     */
    protected function buildEsQuery(array $filters): array
    {
        $must = [];

        // Keyword search
        if (isset($filters['keyword']) && !empty($filters['keyword'])) {
            $must[] = [
                'multi_match' => [
                    'query' => $filters['keyword'],
                    'fields' => ['title^3', 'content', 'description^2', 'name^3'],
                ],
            ];
        }

        // Name search (for categories/tags)
        if (isset($filters['name']) && !empty($filters['name'])) {
            $must[] = [
                'match' => [
                    'name' => $filters['name'],
                ],
            ];
        }

        // Status filter
        if (isset($filters['status'])) {
            $must[] = ['term' => ['status' => $filters['status']]];
        }

        // Category filter
        if (isset($filters['category_ids']) && !empty($filters['category_ids'])) {
            $must[] = ['terms' => ['categories.id' => $filters['category_ids']]];
        }

        // Tag filter
        if (isset($filters['tag_ids']) && !empty($filters['tag_ids'])) {
            $must[] = ['terms' => ['tags.id' => $filters['tag_ids']]];
        }

        // Date range filter
        if (isset($filters['date_from']) || isset($filters['date_to'])) {
            $range = [];
            if (isset($filters['date_from'])) {
                $range['gte'] = $filters['date_from'];
            }
            if (isset($filters['date_to'])) {
                $range['lte'] = $filters['date_to'];
            }
            $must[] = ['range' => ['published_at' => $range]];
        }

        $sortBy = $filters['sort_by'] ?? 'published_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        return [
            'query' => [
                'bool' => [
                    'must' => $must,
                ],
            ],
            'sort' => [
                $sortBy => [
                    'order' => $sortOrder,
                ],
            ],
        ];
    }
}
