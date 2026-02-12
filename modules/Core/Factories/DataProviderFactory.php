<?php

namespace Modules\Core\Factories;

use Modules\Core\Contracts\DataProviderInterface;
use Modules\Core\Providers\Data\DatabaseProvider;
use Modules\Core\Providers\Data\ElasticsearchProvider;
use Illuminate\Database\Eloquent\Model;

class DataProviderFactory
{
    /**
     * Create appropriate data provider based on configuration
     *
     * @param string $index - Elasticsearch index name (e.g., 'posts')
     * @param Model $model - Eloquent model for database fallback
     * @return DataProviderInterface
     */
    public static function make(string $index, Model $model): DataProviderInterface
    {
        // Check if Elasticsearch is enabled and available
        $useElasticsearch = config('elasticsearch.enabled', false);

        if ($useElasticsearch && app()->bound('elasticsearch')) {
            try {
                return new ElasticsearchProvider(
                    app('elasticsearch'),
                    config("elasticsearch.indices.{$index}", $index)
                );
            } catch (\Exception $e) {
                // If Elasticsearch fails, fall back to database
                \Log::warning("Elasticsearch provider failed, falling back to database: " . $e->getMessage());
            }
        }

        // Fallback to database provider
        return new DatabaseProvider($model);
    }
}
