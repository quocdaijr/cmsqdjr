#!/bin/bash

echo "============================================"
echo "CmsQDJr v2.0 - Full Setup"
echo "Services: PHP + MySQL + Redis + Elasticsearch"
echo "============================================"
echo ""

# Check if .env exists
if [ ! -f .env ]; then
    echo "📋 Creating .env from .env.example..."
    cp .env.example .env

    # Set full configuration
    sed -i 's/ELASTICSEARCH_ENABLED=false/ELASTICSEARCH_ENABLED=true/g' .env
    sed -i 's/QUEUE_CONNECTION=sync/QUEUE_CONNECTION=redis/g' .env
    sed -i 's/CACHE_DRIVER=file/CACHE_DRIVER=redis/g' .env
    echo "✅ .env created with full configuration (Elasticsearch enabled)"
else
    echo "ℹ️  .env already exists, updating Elasticsearch config..."
    sed -i 's/ELASTICSEARCH_ENABLED=false/ELASTICSEARCH_ENABLED=true/g' .env
fi

# Check if Elasticsearch service is uncommented in docker-compose.yml
if grep -q "^#.*lar_elasticsearch8:" docker-compose.yml; then
    echo ""
    echo "⚠️  WARNING: Elasticsearch service is commented in docker-compose.yml"
    echo "Please uncomment the lar_elasticsearch8 service before running this script."
    echo ""
    read -p "Do you want to continue anyway? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "❌ Setup cancelled"
        exit 1
    fi
fi

# Start all services
echo ""
echo "🐳 Starting all Docker services..."
docker-compose up -d

# Wait for services to be ready
echo ""
echo "⏳ Waiting for services to be ready..."
sleep 20

# Install PHP dependencies
echo ""
echo "📦 Installing Composer dependencies..."
docker exec lar_php84 composer install --no-interaction --optimize-autoloader

# Generate application key
echo ""
echo "🔑 Generating application key..."
docker exec lar_php84 php artisan key:generate --force

# Run migrations
echo ""
echo "🗄️  Running database migrations..."
docker exec lar_php84 php artisan migrate --force

# Seed database (optional)
echo ""
read -p "❓ Do you want to seed the database with sample data? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "🌱 Seeding database..."
    docker exec lar_php84 php artisan db:seed --force
fi

# Build Elasticsearch indices
echo ""
echo "🔍 Building Elasticsearch indices..."
docker exec lar_php84 php artisan es:build:posts || echo "⚠️  Warning: es:build:posts command not found or failed"
docker exec lar_php84 php artisan es:build:categories || echo "⚠️  Warning: es:build:categories command not found or failed"
docker exec lar_php84 php artisan es:build:tags || echo "⚠️  Warning: es:build:tags command not found or failed"

# Clear caches
echo ""
echo "🧹 Clearing application caches..."
docker exec lar_php84 php artisan config:clear
docker exec lar_php84 php artisan cache:clear
docker exec lar_php84 php artisan route:clear
docker exec lar_php84 php artisan view:clear

# Set permissions
echo ""
echo "🔐 Setting storage permissions..."
docker exec lar_php84 chmod -R 775 storage bootstrap/cache

echo ""
echo "============================================"
echo "✅ Full setup complete!"
echo "============================================"
echo ""
echo "🌐 Access the application at: http://localhost"
echo ""
echo "📊 Services running:"
echo "   - Nginx (web server)"
echo "   - PHP 8.4 FPM"
echo "   - MySQL 8.0"
echo "   - Redis 7.2 (cache + queues)"
echo "   - Elasticsearch 8.x (search engine)"
echo ""
echo "📝 Elasticsearch indices:"
echo "   - posts"
echo "   - categories"
echo "   - tags"
echo ""
echo "💡 To add RabbitMQ (optional):"
echo "   1. Set QUEUE_CONNECTION=rabbitmq in .env"
echo "   2. Uncomment lar_rabbitmq in docker-compose.yml"
echo "   3. Run: docker-compose up -d lar_rabbitmq"
echo ""
