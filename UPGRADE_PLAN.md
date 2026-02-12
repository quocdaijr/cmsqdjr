# CmsQDJr Upgrade Plan - Version 2.0

**Date:** 2026-02-12
**Current Version:** 1.0 (Laravel 8.40, PHP 8.0)
**Target Version:** 2.0 (Laravel 11.x, PHP 8.4)
**Goal:** Modernize stack and minimize infrastructure dependencies

---

## Table of Contents
1. [Executive Summary](#executive-summary)
2. [Current State Analysis](#current-state-analysis)
3. [Target State](#target-state)
4. [Upgrade Phases](#upgrade-phases)
5. [Implementation Details](#implementation-details)
6. [Testing Strategy](#testing-strategy)
7. [Risk Assessment](#risk-assessment)
8. [Timeline](#timeline)

---

## Executive Summary

### Objectives
1. **Modernize Stack:** Upgrade to latest stable versions (Jan 2026)
2. **Reduce Dependencies:** Make Elasticsearch optional, remove RabbitMQ
3. **Simplify Infrastructure:** Run with minimal services (PHP, MySQL, Redis)
4. **Flexible Storage:** Maintain local storage with easy S3 switching

### Key Changes
- PHP 8.0 → 8.4
- Laravel 8.40 → 11.x
- Elasticsearch: Required → Optional
- Storage: Already using local (keep with Provider pattern)
- Queues: RabbitMQ → Redis
- Minimum Services: PHP + MySQL + Redis (Elasticsearch optional)

---

## Current State Analysis

### Technology Stack (v1.0)
```
Runtime:
- PHP: 8.0
- Laravel Framework: 8.40
- MySQL: 8.0
- Redis: Latest (optional, not in docker-compose)
- Elasticsearch: 7.14 (required for API)
- RabbitMQ: 3.8.19 (configured, commented out)

Key Packages:
- nwidart/laravel-modules: 8.2
- elasticsearch/elasticsearch: 7.14
- spatie/laravel-permission: 4.2
- laravel/horizon: 5.7
- laravel/telescope: 4.6
- intervention/image: 2.6
- league/flysystem-aws-s3-v3: 1.0
- vladimir-yuldashev/laravel-queue-rabbitmq: 11.3
```

### Architecture Overview
```
7 Modules:
├── Core              - Base functionality, traits, jobs
├── Administration    - Users, roles, permissions (Spatie)
├── Post              - Blog posts (ES-dependent API)
├── Category          - Post categories (ES-dependent API)
├── Tag               - Post tags (ES-dependent API)
├── File              - File management, image processing
└── Dashboard         - Analytics dashboard
```

### Critical Dependencies
1. **Elasticsearch (HARD DEPENDENCY):**
   - All API endpoints (Post, Category, Tag) query ONLY Elasticsearch
   - Data flow: MySQL → Queue Jobs → Elasticsearch → API
   - Cannot run API without ES currently

2. **Storage (FLEXIBLE):**
   - Uses Laravel Filesystem abstraction
   - Currently configured for 'public' disk (local)
   - S3 credentials in .env but empty (not actively used)
   - FileService is disk-agnostic ✓

3. **Queue (OPTIONAL):**
   - RabbitMQ configured but commented out in docker-compose
   - Queue jobs: ResizeImage, IndexPost/Category/TagElasticsearch
   - Can use: sync, database, redis, or rabbitmq

### Pain Points
- ❌ Cannot run without Elasticsearch (API fails)
- ❌ Extra infrastructure complexity (RabbitMQ, Elasticsearch)
- ❌ Outdated packages with security vulnerabilities
- ❌ Deprecated Laravel 8 patterns (middleware, routes, providers)

---

## Target State

### Technology Stack (v2.0 - Jan 2026)
```
Runtime:
- PHP: 8.4 (latest stable)
- Laravel Framework: 11.x (11.38+)
- MySQL: 8.0 (keep current)
- Redis: 7.2+ (for cache + queues)
- Elasticsearch: 8.x (OPTIONAL)

Minimum Setup:
✓ PHP 8.4 + MySQL 8.0 + Redis 7.2
✓ API works without Elasticsearch (uses MySQL)
✓ Can enable Elasticsearch for performance (optional)
✓ No RabbitMQ dependency
```

### Updated Packages
```json
Core Framework:
- laravel/framework: ^11.0 (from ^8.40)
- laravel/horizon: ^5.28 (from ^5.7)
- laravel/telescope: ^5.2 (from ^4.6)
- laravel/tinker: ^2.10 (from ^2.5)

Infrastructure:
- nwidart/laravel-modules: ^11.0 (from ^8.2)
- spatie/laravel-permission: ^6.0 (from ^4.2)
- elasticsearch/elasticsearch: ^8.0 (from ^7.14) - OPTIONAL
- predis/predis: ^2.2 (new - for Redis)

Storage & Media:
- intervention/image: ^3.0 (from ^2.6) - API changes!
- league/flysystem-aws-s3-v3: ^3.0 (from ^1.0)

Localization:
- mcamara/laravel-localization: ^2.0 (from ^1.6)

Development:
- barryvdh/laravel-ide-helper: ^3.0 (from ^2.10)
- barryvdh/laravel-debugbar: ^3.14 (from ^3.6)
- spatie/laravel-ignition: ^2.0 (replaces facade/ignition)
- phpunit/phpunit: ^11.0 (from ^9.3)
- orchestra/testbench: ^9.0 (from ^6.22)

Remove:
- vladimir-yuldashev/laravel-queue-rabbitmq (migrate to Redis)
- maatwebsite/laravel-sidebar (abandoned - manual replacement)
```

### Architecture Changes

#### 1. Data Provider Pattern (NEW)
```
Before (v1):
API Controller → ElasticsearchRepository → Elasticsearch ONLY

After (v2):
API Controller → DataProviderInterface → [DatabaseProvider | ElasticsearchProvider]
                                          ↓                  ↓
                                        MySQL          Elasticsearch (optional)
```

#### 2. Storage Provider Pattern (EXISTING - Keep)
```
FileService → Laravel Filesystem → [local | s3 | ...]
✓ Already abstracted via Storage facade
✓ Just configure disk in .env
```

#### 3. Queue System
```
Before: RabbitMQ (configured but unused)
After:  Redis (simpler, fewer dependencies)
```

---

## Upgrade Phases

### Phase 1: Foundation Upgrade (Week 1)
**Goal:** Update core framework and runtime

1.1. **Upgrade PHP 8.0 → 8.4**
   - Update docker/php80 → docker/php84
   - Update Dockerfile base image
   - Update docker-compose.yml service name
   - Test PHP 8.4 compatibility

1.2. **Upgrade Laravel 8.40 → 11.x**
   - Update composer.json: `"laravel/framework": "^11.0"`
   - Run `composer update laravel/framework`
   - Follow Laravel upgrade guides (8→9, 9→10, 10→11)

1.3. **Refactor Laravel 11 Breaking Changes**
   - **Middleware:** Migrate `app/Http/Kernel.php` → `bootstrap/app.php`
   - **Route Namespaces:** Remove deprecated `namespace()` binding
   - **Service Providers:** Update boot() pattern for L11
   - **Config Publishing:** Update `publishes()` calls
   - **Blade Components:** Update registration pattern

1.4. **Update Docker Configuration**
   ```yaml
   # docker-compose.yml changes:
   lar_php84:  # rename from lar_php80
     image: quocdaijr/php-fpm:8.4
     volumes:
       - './docker/php84/...'  # update paths

   lar_redis:  # ENABLE THIS
     image: redis:7.2-alpine
     expose: [6379]
   ```

**Deliverables:**
- ✓ Laravel 11 running on PHP 8.4
- ✓ Redis container enabled
- ✓ All tests passing (basic framework)

---

### Phase 2: Dependency Updates (Week 2)

2.1. **Update Critical Packages**
   ```bash
   # Core infrastructure
   composer require nwidart/laravel-modules:^11.0
   composer require spatie/laravel-permission:^6.0
   composer require laravel/horizon:^5.28
   composer require laravel/telescope:^5.2

   # Image processing (BREAKING CHANGES)
   composer require intervention/image:^3.0

   # Testing
   composer require --dev phpunit/phpunit:^11.0
   composer require --dev orchestra/testbench:^9.0
   ```

2.2. **Fix Intervention/Image v3 Breaking Changes**
   - v2: `Image::make()` → v3: `ImageManager::read()`
   - Update `Modules/File/Services/FileService.php`
   - Update `Modules/File/Jobs/ResizeImage.php`

   **Example Migration:**
   ```php
   // OLD (v2):
   use Intervention\Image\Facades\Image;
   $image = Image::make($path)->resize(800, 600);

   // NEW (v3):
   use Intervention\Image\ImageManager;
   use Intervention\Image\Drivers\Gd\Driver;
   $manager = new ImageManager(new Driver());
   $image = $manager->read($path)->scale(width: 800);
   ```

2.3. **Remove Abandoned Packages**
   - Remove: `maatwebsite/laravel-sidebar`
   - Implement simple sidebar builder in `Modules/Core/Services/SidebarService.php`
   - Update sidebar rendering in layouts

2.4. **Migrate RabbitMQ → Redis Queues**
   - Remove: `vladimir-yuldashev/laravel-queue-rabbitmq`
   - Add: `predis/predis` for Redis client
   - Update `config/queue.php`: set default to 'redis'
   - Update `.env.example`: `QUEUE_CONNECTION=redis`
   - Remove RabbitMQ env vars from .env.example

**Deliverables:**
- ✓ All packages updated to Laravel 11 compatible versions
- ✓ Image processing updated to Intervention v3
- ✓ Redis queues working
- ✓ RabbitMQ dependency removed

---

### Phase 3: Data Provider Abstraction (Week 3)

**Goal:** Make Elasticsearch optional by creating DB fallback

3.1. **Create Provider Interface**
   ```php
   // Modules/Core/Contracts/DataProviderInterface.php
   namespace Modules\Core\Contracts;

   interface DataProviderInterface
   {
       public function search(array $params): array;
       public function find(int $id): ?array;
       public function paginate(array $filters, int $perPage): array;
   }
   ```

3.2. **Create Database Provider**
   ```php
   // Modules/Core/Providers/DatabaseProvider.php
   namespace Modules\Core\Providers;

   use Modules\Core\Contracts\DataProviderInterface;

   class DatabaseProvider implements DataProviderInterface
   {
       public function __construct(protected $model) {}

       public function search(array $params): array
       {
           // Use Eloquent ORM with MySQL full-text search
           // or LIKE queries for basic search
           $query = $this->model->query();

           // Apply filters, sorting, pagination
           if (isset($params['keyword'])) {
               $query->where('title', 'like', "%{$params['keyword']}%")
                     ->orWhere('content', 'like', "%{$params['keyword']}%");
           }

           return $this->formatResponse($query->get());
       }

       private function formatResponse($results): array
       {
           // Format to match Elasticsearch response structure
           return [
               'total' => $results->count(),
               'hits' => $results->map(fn($item) => [
                   '_id' => $item->id,
                   '_source' => $item->toArray()
               ])->toArray()
           ];
       }
   }
   ```

3.3. **Create Elasticsearch Provider**
   ```php
   // Modules/Core/Providers/ElasticsearchProvider.php
   namespace Modules\Core\Providers;

   use Modules\Core\Contracts\DataProviderInterface;
   use Elasticsearch\Client;

   class ElasticsearchProvider implements DataProviderInterface
   {
       public function __construct(
           protected Client $client,
           protected string $index
       ) {}

       public function search(array $params): array
       {
           // Existing Elasticsearch logic (keep as-is)
           return $this->client->search([
               'index' => $this->index,
               'body' => $params
           ]);
       }
   }
   ```

3.4. **Implement Provider Factory**
   ```php
   // Modules/Core/Factories/DataProviderFactory.php
   namespace Modules\Core\Factories;

   use Modules\Core\Contracts\DataProviderInterface;
   use Modules\Core\Providers\{DatabaseProvider, ElasticsearchProvider};

   class DataProviderFactory
   {
       public static function make(string $type, $model): DataProviderInterface
       {
           $useElasticsearch = config('elasticsearch.enabled', false);

           if ($useElasticsearch && app()->bound('elasticsearch')) {
               return new ElasticsearchProvider(
                   app('elasticsearch'),
                   $type
               );
           }

           return new DatabaseProvider($model);
       }
   }
   ```

3.5. **Update Module Repositories**

   **Post Repository:**
   ```php
   // Modules/Post/Repositories/PostRepository.php
   use Modules\Core\Factories\DataProviderFactory;
   use Modules\Core\Contracts\DataProviderInterface;

   class PostRepository
   {
       protected DataProviderInterface $provider;

       public function __construct(Post $model)
       {
           $this->model = $model;
           $this->provider = DataProviderFactory::make('posts', $model);
       }

       public function search(array $filters)
       {
           return $this->provider->search($filters);
       }
   }
   ```

   Repeat for:
   - `Modules/Category/Repositories/CategoryRepository.php`
   - `Modules/Tag/Repositories/TagRepository.php`

3.6. **Add Configuration**
   ```php
   // config/elasticsearch.php
   return [
       'enabled' => env('ELASTICSEARCH_ENABLED', false),
       'hosts' => [
           env('ELASTICSEARCH_HOST', 'localhost') . ':' . env('ELASTICSEARCH_PORT', 9200)
       ],
       'indices' => [
           'posts' => env('ELASTICSEARCH_INDEX_POSTS', 'posts'),
           'categories' => env('ELASTICSEARCH_INDEX_CATEGORIES', 'categories'),
           'tags' => env('ELASTICSEARCH_INDEX_TAGS', 'tags'),
       ]
   ];
   ```

3.7. **Update Environment Configuration**
   ```bash
   # .env.example

   # Elasticsearch (OPTIONAL - set to false for minimum setup)
   ELASTICSEARCH_ENABLED=false
   ELASTICSEARCH_HOST=127.0.0.1
   ELASTICSEARCH_PORT=9200
   ELASTICSEARCH_INDEX_POSTS=posts
   ELASTICSEARCH_INDEX_CATEGORIES=categories
   ELASTICSEARCH_INDEX_TAGS=tags
   ```

3.8. **Make Elasticsearch Jobs Conditional**
   ```php
   // Modules/Post/Observers/PostObserver.php
   public function saved(Post $post)
   {
       if (config('elasticsearch.enabled')) {
           dispatch(new IndexPostElasticsearch($post));
       }
   }
   ```

**Deliverables:**
- ✓ DataProviderInterface implemented
- ✓ DatabaseProvider working (MySQL fallback)
- ✓ ElasticsearchProvider working (optional)
- ✓ All API endpoints work with ELASTICSEARCH_ENABLED=false
- ✓ All API endpoints work with ELASTICSEARCH_ENABLED=true
- ✓ Configuration properly documented

---

### Phase 4: Storage Configuration (Week 3)

**Goal:** Ensure storage works with local (default) and S3 (switchable)

4.1. **Verify Current Implementation**
   - FileService already uses `Storage::disk($disk)`
   - Config already in `config/filesystems.php`
   - No code changes needed ✓

4.2. **Update Documentation**
   ```bash
   # .env.example

   # Storage Configuration
   # Options: local, public, s3
   FILESYSTEM_DISK=public

   # AWS S3 (only if FILESYSTEM_DISK=s3)
   AWS_ACCESS_KEY_ID=
   AWS_SECRET_ACCESS_KEY=
   AWS_DEFAULT_REGION=us-east-1
   AWS_BUCKET=
   AWS_URL=
   ```

4.3. **Update Flysystem S3 Package**
   ```bash
   composer require league/flysystem-aws-s3-v3:^3.0
   ```

4.4. **Test Storage Switching**
   - Upload file with `FILESYSTEM_DISK=public` → verify local storage
   - Upload file with `FILESYSTEM_DISK=s3` → verify S3 storage (if credentials provided)
   - Verify `SyncFilesCommand` works for migration

**Deliverables:**
- ✓ Local storage working (default)
- ✓ S3 storage configurable via .env
- ✓ Storage provider pattern validated

---

### Phase 5: Service Minimization (Week 4)

**Goal:** Validate minimal service setup works

5.1. **Update Docker Compose for Minimal Setup**
   ```yaml
   # docker-compose.yml
   version: '3.8'
   services:
     lar_nginx:
       image: nginx:alpine
       # ... (keep existing)

     lar_php84:
       image: quocdaijr/php-fpm:8.4
       container_name: lar_php84
       environment:
         - ELASTICSEARCH_ENABLED=false  # Disable ES
       # ... (keep existing)

     lar_mysql:
       image: mysql:8.0
       # ... (keep existing)

     lar_redis:
       image: redis:7.2-alpine
       container_name: lar_redis
       volumes:
         - './docker/redis/data:/data'
       expose:
         - 6379
       network_mode: "host"

     # OPTIONAL: Elasticsearch (commented out by default)
     # lar_elasticsearch8:
     #   image: docker.elastic.co/elasticsearch/elasticsearch:8.12.0
     #   container_name: lar_elasticsearch8
     #   environment:
     #     - discovery.type=single-node
     #     - xpack.security.enabled=false
     #   volumes:
     #     - ./docker/elasticsearch8/data:/usr/share/elasticsearch/data
     #   expose:
     #     - 9200
     #   network_mode: "host"
   ```

5.2. **Create Setup Scripts**

   **Minimal Setup:**
   ```bash
   # bin/setup-minimal.sh
   #!/bin/bash

   echo "Setting up CmsQDJr v2 - Minimal Mode"
   echo "Services: PHP 8.4 + MySQL 8.0 + Redis 7.2"

   # Copy env
   cp .env.example .env

   # Set minimal configuration
   sed -i 's/ELASTICSEARCH_ENABLED=true/ELASTICSEARCH_ENABLED=false/' .env
   sed -i 's/QUEUE_CONNECTION=sync/QUEUE_CONNECTION=redis/' .env
   sed -i 's/CACHE_DRIVER=file/CACHE_DRIVER=redis/' .env

   # Start services
   docker-compose up -d lar_nginx lar_php84 lar_mysql lar_redis

   # Install dependencies
   docker exec lar_php84 composer install

   # Generate key
   docker exec lar_php84 php artisan key:generate

   # Run migrations
   docker exec lar_php84 php artisan migrate --seed

   echo "✓ Setup complete! Access at http://localhost"
   ```

   **Full Setup (with Elasticsearch):**
   ```bash
   # bin/setup-full.sh
   #!/bin/bash

   echo "Setting up CmsQDJr v2 - Full Mode"
   echo "Services: PHP 8.4 + MySQL + Redis + Elasticsearch 8.x"

   # ... similar to minimal but enable ES
   sed -i 's/ELASTICSEARCH_ENABLED=false/ELASTICSEARCH_ENABLED=true/' .env

   # Uncomment ES in docker-compose
   # Start all services including ES
   docker-compose up -d

   # Build ES indices
   docker exec lar_php84 php artisan es:build:posts
   docker exec lar_php84 php artisan es:build:categories
   docker exec lar_php84 php artisan es:build:tags

   echo "✓ Setup complete with Elasticsearch!"
   ```

5.3. **Update README.md**
   - Document minimal setup process
   - Document full setup with Elasticsearch
   - Document environment variable options
   - Add troubleshooting section

**Deliverables:**
- ✓ Minimal setup works: PHP + MySQL + Redis
- ✓ Full setup works: PHP + MySQL + Redis + Elasticsearch
- ✓ Setup scripts created
- ✓ Documentation updated

---

### Phase 6: Testing & Validation (Week 4)

6.1. **Update Test Suite**
   - Update PHPUnit configuration for v11
   - Update testbench to v9
   - Fix deprecated assertions
   - Add provider tests (Database vs Elasticsearch)

6.2. **Integration Testing**
   - Test all modules with ELASTICSEARCH_ENABLED=false
   - Test all modules with ELASTICSEARCH_ENABLED=true
   - Test storage with local disk
   - Test queue jobs with Redis
   - Test image processing with Intervention v3

6.3. **Performance Testing**
   - Benchmark API with Database provider
   - Benchmark API with Elasticsearch provider
   - Document performance differences
   - Add recommendations

6.4. **Manual Testing Checklist**
   ```
   Minimal Setup (PHP + MySQL + Redis):
   ☐ Install fresh with bin/setup-minimal.sh
   ☐ Create user via Administration
   ☐ Create post with categories/tags
   ☐ Upload images (local storage)
   ☐ Search posts via API
   ☐ Check queue jobs processed
   ☐ Verify Redis cache working

   Full Setup (+ Elasticsearch):
   ☐ Install fresh with bin/setup-full.sh
   ☐ Verify ES indices created
   ☐ Create post → verify ES sync
   ☐ Search via API → verify ES results
   ☐ Compare search performance

   Storage Switching:
   ☐ Upload file with FILESYSTEM_DISK=public
   ☐ Switch to FILESYSTEM_DISK=s3 (with creds)
   ☐ Run php artisan storage:sync
   ☐ Verify files accessible
   ```

**Deliverables:**
- ✓ All tests passing
- ✓ Both minimal and full setups validated
- ✓ Performance benchmarks documented

---

### Phase 7: Migration & Deployment (Week 5)

7.1. **Create Migration Guide**
   - Document v1 → v2 upgrade process
   - Database migration requirements
   - Configuration changes
   - Breaking changes list

7.2. **Data Migration Script**
   ```bash
   # bin/migrate-v1-to-v2.sh
   #!/bin/bash

   echo "Migrating CmsQDJr v1 → v2"

   # Backup database
   docker exec lar_mysql mysqldump -u root -p12345@ cmsqdjr > backup_v1.sql

   # Run new migrations
   php artisan migrate

   # If keeping Elasticsearch, rebuild indices
   if [ "$ELASTICSEARCH_ENABLED" = "true" ]; then
       php artisan es:build:posts
       php artisan es:build:categories
       php artisan es:build:tags
   fi

   echo "✓ Migration complete!"
   ```

7.3. **Rollback Plan**
   - Document rollback steps
   - Keep v1 docker images available
   - Database backup strategy

**Deliverables:**
- ✓ Migration guide written
- ✓ Migration scripts tested
- ✓ Rollback plan documented

---

## Implementation Details

### Critical Code Changes

#### 1. Laravel 11 Middleware Registration

**OLD (app/Http/Kernel.php):**
```php
protected $routeMiddleware = [
    'auth' => \App\Http\Middleware\Authenticate::class,
    'role' => \Modules\Administration\Http\Middleware\RoleMiddleware::class,
    // ...
];
```

**NEW (bootstrap/app.php):**
```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'role' => \Modules\Administration\Http\Middleware\RoleMiddleware::class,
            'permission' => \Modules\Administration\Http\Middleware\PermissionMiddleware::class,
            'localize' => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRoutes::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

#### 2. Route Registration (Remove Namespace)

**OLD:**
```php
Route::middleware('web')
    ->namespace($this->moduleNamespace)
    ->group(module_path('Post', '/Routes/web.php'));
```

**NEW:**
```php
Route::middleware('web')
    ->group(module_path('Post', '/Routes/web.php'));
```

#### 3. Service Provider Pattern

**OLD (Laravel 8):**
```php
public function boot()
{
    $this->registerTranslations();
    $this->registerConfig();
    $this->registerViews();
    $this->loadMigrationsFrom(module_path('Post', 'Database/Migrations'));
}
```

**NEW (Laravel 11 - same but verify paths):**
```php
public function boot(): void
{
    $this->loadTranslationsFrom(module_path('Post', 'Resources/lang'), 'post');
    $this->loadViewsFrom(module_path('Post', 'Resources/views'), 'post');
    $this->loadMigrationsFrom(module_path('Post', 'Database/Migrations'));

    // Publish config
    $this->publishes([
        module_path('Post', 'Config/config.php') => config_path('post.php'),
    ], 'config');
}
```

#### 4. Intervention Image v2 → v3

**Files to Update:**
- `Modules/File/Services/FileService.php`
- `Modules/File/Jobs/ResizeImage.php`
- Any controllers using Image facade

**OLD (v2):**
```php
use Intervention\Image\Facades\Image;

public function resize($path, $width, $height)
{
    $image = Image::make($path);
    $image->resize($width, $height, function ($constraint) {
        $constraint->aspectRatio();
        $constraint->upsize();
    });
    $image->save($path);
}
```

**NEW (v3):**
```php
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

public function resize($path, $width, $height)
{
    $manager = new ImageManager(new Driver());
    $image = $manager->read($path);

    $image->scale(width: $width, height: $height);
    $image->save($path);
}
```

---

## Testing Strategy

### Unit Tests
```php
// tests/Unit/DataProviderTest.php
class DataProviderTest extends TestCase
{
    public function test_database_provider_search()
    {
        $provider = new DatabaseProvider(new Post);
        $results = $provider->search(['keyword' => 'test']);

        $this->assertArrayHasKey('total', $results);
        $this->assertArrayHasKey('hits', $results);
    }

    public function test_elasticsearch_provider_search()
    {
        if (!config('elasticsearch.enabled')) {
            $this->markTestSkipped('Elasticsearch disabled');
        }

        $provider = new ElasticsearchProvider(app('elasticsearch'), 'posts');
        $results = $provider->search(['query' => ['match_all' => []]]);

        $this->assertArrayHasKey('hits', $results);
    }
}
```

### Feature Tests
```php
// tests/Feature/PostApiTest.php
class PostApiTest extends TestCase
{
    /** @dataProvider providerModes */
    public function test_post_search_api($elasticsearchEnabled)
    {
        config(['elasticsearch.enabled' => $elasticsearchEnabled]);

        $response = $this->getJson('/api/posts?keyword=test');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => ['id', 'title', 'content', 'slug']
                     ]
                 ]);
    }

    public function providerModes()
    {
        return [
            'with_database' => [false],
            'with_elasticsearch' => [true],
        ];
    }
}
```

### Performance Benchmarks
```bash
# Compare search performance
php artisan benchmark:search --provider=database --queries=1000
php artisan benchmark:search --provider=elasticsearch --queries=1000
```

Expected results:
- Database: ~50-200ms per complex query
- Elasticsearch: ~5-20ms per complex query

---

## Risk Assessment

### High Risk

1. **Intervention/Image v3 Breaking Changes**
   - **Risk:** Image processing fails after upgrade
   - **Mitigation:**
     - Update all Image calls before deploying
     - Test with sample uploads
     - Keep v2 compatibility layer temporarily

2. **Laravel 11 Middleware Changes**
   - **Risk:** Middleware not registered, routes fail
   - **Mitigation:**
     - Carefully migrate Kernel.php → bootstrap/app.php
     - Test all protected routes
     - Keep detailed migration checklist

3. **Database Provider Performance**
   - **Risk:** API becomes too slow without Elasticsearch
   - **Mitigation:**
     - Add MySQL full-text indices
     - Benchmark before/after
     - Document when ES is recommended

### Medium Risk

4. **Module Compatibility (nwidart/laravel-modules)**
   - **Risk:** Module loading fails with Laravel 11
   - **Mitigation:**
     - Test each module individually
     - Update to latest stable version
     - Check nwidart/laravel-modules Laravel 11 support

5. **Spatie Permission v6 Changes**
   - **Risk:** Permission checks fail
   - **Mitigation:**
     - Review v4→v6 upgrade guide
     - Test all role/permission checks
     - Verify middleware still works

### Low Risk

6. **Queue Migration (RabbitMQ → Redis)**
   - **Risk:** Jobs fail to process
   - **Mitigation:**
     - Redis is simpler than RabbitMQ
     - Test each job type
     - Monitor queue dashboard (Horizon)

7. **Storage Configuration**
   - **Risk:** File uploads break
   - **Mitigation:**
     - Already using Laravel abstraction
     - Minimal code changes needed
     - Test both local and S3

---

## Timeline

### Week 1: Foundation
- Days 1-2: PHP 8.4 + Laravel 11 upgrade
- Days 3-4: Refactor breaking changes (middleware, routes, providers)
- Day 5: Testing + fixes

### Week 2: Dependencies
- Days 1-2: Update core packages (modules, permission, horizon, telescope)
- Days 3-4: Update Intervention/Image v3 + fix usages
- Day 5: Remove RabbitMQ, enable Redis queues

### Week 3: Data Providers
- Days 1-2: Create DataProvider interface + implementations
- Days 3-4: Update repositories (Post, Category, Tag)
- Day 5: Testing both modes (DB + ES)

### Week 4: Finalization
- Days 1-2: Service minimization + setup scripts
- Days 3-4: Integration testing + performance benchmarks
- Day 5: Documentation

### Week 5: Migration & Deploy
- Days 1-2: Migration guide + scripts
- Day 3: Staging deployment
- Day 4: Production deployment
- Day 5: Monitoring + fixes

**Total Estimated Time:** 5 weeks (single developer)
**With Team (2-3 devs):** 3-4 weeks

---

## Success Criteria

### Must Have ✓
- [x] Laravel 11 + PHP 8.4 running
- [x] All 7 modules functioning
- [x] API works WITHOUT Elasticsearch
- [x] API works WITH Elasticsearch (optional)
- [x] Local storage working
- [x] Redis queues working
- [x] All tests passing
- [x] Docker setup simplified (minimal services)

### Nice to Have
- [ ] MySQL full-text search optimization
- [ ] S3 storage tested and documented
- [ ] Performance benchmarks published
- [ ] Upgrade automation script

### Validation Checklist
```
☐ Fresh install with minimal setup (PHP+MySQL+Redis)
☐ Create content (posts, categories, tags, files)
☐ API search works (using database provider)
☐ Queue jobs process (image resize, etc)
☐ Fresh install with full setup (+ Elasticsearch)
☐ ES indices sync correctly
☐ API search works (using ES provider)
☐ Performance acceptable in both modes
☐ All module features working
☐ Tests passing (unit + feature)
☐ Documentation complete
```

---

## Post-Upgrade Recommendations

### Performance Optimization
1. **Add MySQL Full-Text Indices** (if not using ES)
   ```sql
   ALTER TABLE posts ADD FULLTEXT INDEX ft_posts (title, content);
   ALTER TABLE categories ADD FULLTEXT INDEX ft_categories (name, description);
   ALTER TABLE tags ADD FULLTEXT INDEX ft_tags (name);
   ```

2. **Enable Opcache** (production)
   ```ini
   # php.ini
   opcache.enable=1
   opcache.memory_consumption=256
   opcache.interned_strings_buffer=16
   opcache.max_accelerated_files=10000
   ```

3. **Redis Configuration** (production)
   ```
   # redis.conf
   maxmemory 256mb
   maxmemory-policy allkeys-lru
   ```

### Monitoring
- Enable Laravel Telescope (development)
- Enable Laravel Horizon (queue monitoring)
- Set up error tracking (Sentry/Bugsnag)
- Monitor slow queries (MySQL)

### Security
- Run `php artisan security:check` (check vulnerabilities)
- Update all NPM packages: `npm audit fix`
- Enable HTTPS in production
- Review .env file (no secrets committed)

---

## Appendix

### A. Package Version Reference

| Package | Current (v1) | Target (v2) | Breaking? |
|---------|--------------|-------------|-----------|
| php | 8.0 | 8.4 | No |
| laravel/framework | 8.40 | 11.x | Yes |
| nwidart/laravel-modules | 8.2 | 11.0 | Minor |
| spatie/laravel-permission | 4.2 | 6.0 | Yes |
| elasticsearch/elasticsearch | 7.14 | 8.x | Minor |
| intervention/image | 2.6 | 3.0 | Yes |
| laravel/horizon | 5.7 | 5.28 | No |
| laravel/telescope | 4.6 | 5.2 | Minor |
| league/flysystem-aws-s3-v3 | 1.0 | 3.0 | Minor |

### B. Environment Variables (v2)

```bash
# Core
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cmsqdjr
DB_USERNAME=root
DB_PASSWORD=12345@

# Cache & Queue
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

# Storage (local | public | s3)
FILESYSTEM_DISK=public

# AWS S3 (if FILESYSTEM_DISK=s3)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_URL=

# Elasticsearch (OPTIONAL)
ELASTICSEARCH_ENABLED=false
ELASTICSEARCH_HOST=127.0.0.1
ELASTICSEARCH_PORT=9200

# Telescope & Horizon
TELESCOPE_ENABLED=true
HORIZON_PREFIX=horizon:
```

### C. Docker Services Summary

**Minimal Setup (3 services):**
- lar_nginx (Nginx Alpine)
- lar_php84 (PHP 8.4 FPM)
- lar_mysql (MySQL 8.0)
- lar_redis (Redis 7.2)

**Full Setup (4 services):**
- lar_nginx
- lar_php84
- lar_mysql
- lar_redis
- lar_elasticsearch8 (optional)

### D. Command Reference

```bash
# Setup
composer install
php artisan key:generate
php artisan migrate --seed

# Elasticsearch (if enabled)
php artisan es:build:posts
php artisan es:build:categories
php artisan es:build:tags

# Queue
php artisan queue:work
php artisan horizon  # with dashboard

# Testing
php artisan test
php artisan test --filter=DataProviderTest

# Maintenance
php artisan cache:clear
php artisan config:clear
php artisan route:cache
php artisan view:cache
```

---

**Document Version:** 1.0
**Last Updated:** 2026-02-12
**Author:** CmsQDJr Upgrade Team
**Status:** Ready for Implementation
