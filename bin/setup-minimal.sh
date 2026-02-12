#!/bin/bash

echo "============================================"
echo "CmsQDJr v2.0 - Minimal Setup"
echo "Services: Nginx + PHP 8.4 + MySQL + Redis"
echo "============================================"
echo ""

# Check if .env exists
if [ ! -f .env ]; then
    echo "📋 Creating .env from .env.example..."
    cp .env.example .env

    # Set minimal configuration
    sed -i 's/ELASTICSEARCH_ENABLED=true/ELASTICSEARCH_ENABLED=false/g' .env
    sed -i 's/QUEUE_CONNECTION=sync/QUEUE_CONNECTION=redis/g' .env
    sed -i 's/CACHE_DRIVER=file/CACHE_DRIVER=redis/g' .env
    echo "✅ .env created with minimal configuration"
else
    echo "ℹ️  .env already exists, skipping..."
fi

# Start minimal services
echo ""
echo "🐳 Starting Docker services (Nginx, PHP 8.4, MySQL, Redis)..."
docker-compose up -d lar_nginx lar_php84 lar_mysql lar_redis

# Wait for MySQL to be ready
echo ""
echo "⏳ Waiting for MySQL to be ready..."
sleep 10

# Install PHP dependencies
echo ""
echo "📦 Installing Composer dependencies..."
docker exec lar_php84 composer install --no-interaction --optimize-autoloader

# Generate application key if not set
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

# Clear caches
echo ""
echo "🧹 Clearing application caches..."
docker exec lar_php84 php artisan config:clear
docker exec lar_php84 php artisan cache:clear
docker exec lar_php84 php artisan route:clear
docker exec lar_php84 php artisan view:clear

# Set permissions (if needed)
echo ""
echo "🔐 Setting storage permissions..."
docker exec lar_php84 chmod -R 775 storage bootstrap/cache

echo ""
echo "============================================"
echo "✅ Minimal setup complete!"
echo "============================================"
echo ""
echo "🌐 Access the application at: http://localhost"
echo ""
echo "📊 Services running:"
echo "   - Nginx (web server)"
echo "   - PHP 8.4 FPM"
echo "   - MySQL 8.0"
echo "   - Redis 7.2 (cache + queues)"
echo ""
echo "💡 To enable Elasticsearch (optional):"
echo "   1. Set ELASTICSEARCH_ENABLED=true in .env"
echo "   2. Uncomment lar_elasticsearch8 in docker-compose.yml"
echo "   3. Run: docker-compose up -d lar_elasticsearch8"
echo "   4. Run: docker exec lar_php84 php artisan es:build:posts"
echo ""
echo "💡 To enable RabbitMQ (optional):"
echo "   1. Set QUEUE_CONNECTION=rabbitmq in .env"
echo "   2. Uncomment lar_rabbitmq in docker-compose.yml"
echo "   3. Run: docker-compose up -d lar_rabbitmq"
echo ""
