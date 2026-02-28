<?php

namespace Modules\Core\Contracts;

interface DataProviderInterface
{
    /**
     * Search for items with filters
     *
     * @param array $filters - Search filters (keyword, category_ids, tag_ids, etc.)
     * @return array - Standardized response: ['total' => int, 'hits' => array]
     */
    public function search(array $filters): array;

    /**
     * Find a single item by ID
     *
     * @param int $id
     * @return array|null
     */
    public function find(int $id): ?array;

    /**
     * Paginate results
     *
     * @param array $filters
     * @param int $perPage
     * @param int $page
     * @return array - ['total', 'per_page', 'current_page', 'hits']
     */
    public function paginate(array $filters, int $perPage = 15, int $page = 1): array;
}
