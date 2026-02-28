#!/bin/bash
# Post-Deployment Fixes for CmsQDJr v2.0
# Run this AFTER deployment to fix minor configuration issues

set -e

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "CmsQDJr v2.0 - Post-Deployment Fixes"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Fix 1: Republish nwidart/laravel-modules config
echo "✓ Fix 1: Updating nwidart/laravel-modules configuration..."
php artisan vendor:publish --provider="Nwidart\Modules\LaravelModulesServiceProvider" --force
echo "  → Module configuration updated"
echo ""

# Fix 2: Clear all caches
echo "✓ Fix 2: Clearing all caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
echo "  → All caches cleared"
echo ""

# Fix 3: Verify composer audit (security check)
echo "✓ Fix 3: Running security audit..."
composer audit
echo "  → Security audit complete"
echo ""

# Fix 4: Optimize for production (optional)
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Optional: Production Optimizations"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
read -p "Run production optimizations? (y/n) " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "✓ Optimizing for production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    composer dump-autoload --optimize
    echo "  → Production optimizations complete"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ All post-deployment fixes applied!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
