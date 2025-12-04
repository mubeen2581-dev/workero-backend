# Workero Backend Build Script (PowerShell)
# This script optimizes the Laravel application for production

Write-Host "🚀 Starting Workero Backend Build Process..." -ForegroundColor Green

# Check if we're in the backend directory
if (-not (Test-Path "artisan")) {
    Write-Host "Error: This script must be run from the backend directory" -ForegroundColor Red
    exit 1
}

# Check if .env exists
if (-not (Test-Path ".env")) {
    Write-Host "Warning: .env file not found. Creating from .env.example..." -ForegroundColor Yellow
    if (Test-Path ".env.example") {
        Copy-Item ".env.example" ".env"
        Write-Host "Please update .env file with your configuration" -ForegroundColor Yellow
    } else {
        Write-Host "Error: .env.example not found" -ForegroundColor Red
        exit 1
    }
}

# Step 1: Install/Update Composer Dependencies
Write-Host "`nStep 1: Installing Composer dependencies..." -ForegroundColor Green
composer install --no-dev --optimize-autoloader --no-interaction

if ($LASTEXITCODE -ne 0) {
    Write-Host "Error: Composer install failed" -ForegroundColor Red
    exit 1
}

# Step 2: Clear all caches
Write-Host "`nStep 2: Clearing all caches..." -ForegroundColor Green
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Step 3: Generate application key if not set
Write-Host "`nStep 3: Checking application key..." -ForegroundColor Green
$envContent = Get-Content ".env" -Raw
if ($envContent -notmatch "APP_KEY=base64:") {
    Write-Host "Application key not found. Generating..." -ForegroundColor Yellow
    php artisan key:generate --force
} else {
    Write-Host "Application key already exists" -ForegroundColor Green
}

# Step 4: Generate JWT secret if not set
Write-Host "`nStep 4: Checking JWT secret..." -ForegroundColor Green
if ($envContent -notmatch "JWT_SECRET=" -or $envContent -match "JWT_SECRET=`$") {
    Write-Host "JWT secret not found. Generating..." -ForegroundColor Yellow
    php artisan jwt:secret --force
} else {
    Write-Host "JWT secret already exists" -ForegroundColor Green
}

# Step 5: Run migrations (optional - comment out if you don't want to run migrations during build)
# Write-Host "`nStep 5: Running database migrations..." -ForegroundColor Green
# php artisan migrate --force

# Step 6: Optimize Laravel
Write-Host "`nStep 6: Optimizing Laravel application..." -ForegroundColor Green
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Step 7: Optimize Composer autoloader
Write-Host "`nStep 7: Optimizing Composer autoloader..." -ForegroundColor Green
composer dump-autoload --optimize --classmap-authoritative

# Step 8: Create storage link if it doesn't exist
Write-Host "`nStep 8: Creating storage symlink..." -ForegroundColor Green
php artisan storage:link 2>$null
if ($LASTEXITCODE -ne 0) {
    Write-Host "Storage link already exists or could not be created" -ForegroundColor Yellow
}

# Build complete
Write-Host "`n✅ Build completed successfully!" -ForegroundColor Green
Write-Host "`nNext steps:" -ForegroundColor Green
Write-Host "1. Review your .env file configuration"
Write-Host "2. Run migrations: php artisan migrate" -ForegroundColor Yellow
Write-Host "3. Seed database (optional): php artisan db:seed" -ForegroundColor Yellow
Write-Host "4. Start server: php artisan serve" -ForegroundColor Yellow
Write-Host "`nFor production, use a proper web server (Nginx/Apache) with PHP-FPM" -ForegroundColor Green

