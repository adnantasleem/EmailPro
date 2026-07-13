#!/bin/bash

# Exit immediately if a command exits with a non-zero status
set -e

echo "🚀 Starting EmailPro deployment..."

# 1. Turn on maintenance mode (shows a 503 error to users while deploying)
echo "🔒 Entering maintenance mode..."
php artisan down || true

# 2. Reset and Pull the latest code from GitHub
echo "📥 Pulling latest code..."
git fetch --all
git reset --hard origin/main
git pull origin main

# Clear bootstrap cache to prevent missing class errors when removing dev dependencies
echo "🧹 Clearing bootstrap cache..."
rm -f bootstrap/cache/*.php

# 3. Install/Update PHP dependencies
echo "📦 Installing PHP dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# 4. Install/Update Node.js dependencies
echo "📦 Installing Node dependencies..."
npm install

# 5. Build the frontend assets
echo "🏗️ Building frontend assets..."
npm run build

# 6. Run database migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# 7. Clear and rebuild caches
echo "🧹 Clearing and rebuilding caches..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 8. Restart Background Queue Workers
echo "🔄 Restarting EmailPro Queue Worker..."
pm2 restart emailpro-queue || true

# 9. Turn off maintenance mode
echo "🔓 Exiting maintenance mode..."
php artisan up

echo "✅ Deployment finished successfully!"
