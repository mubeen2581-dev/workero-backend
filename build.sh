#!/bin/bash

# Workero Backend Build Script
# This script optimizes the Laravel application for production

echo "🚀 Starting Workero Backend Build Process..."

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check if we're in the backend directory
if [ ! -f "artisan" ]; then
    echo -e "${RED}Error: This script must be run from the backend directory${NC}"
    exit 1
fi

# Check if .env exists
if [ ! -f ".env" ]; then
    echo -e "${YELLOW}Warning: .env file not found. Creating from .env.example...${NC}"
    if [ -f ".env.example" ]; then
        cp .env.example .env
        echo -e "${YELLOW}Please update .env file with your configuration${NC}"
    else
        echo -e "${RED}Error: .env.example not found${NC}"
        exit 1
    fi
fi

# Step 1: Install/Update Composer Dependencies
echo -e "\n${GREEN}Step 1: Installing Composer dependencies...${NC}"
composer install --no-dev --optimize-autoloader --no-interaction

# Step 2: Clear all caches
echo -e "\n${GREEN}Step 2: Clearing all caches...${NC}"
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Step 3: Generate application key if not set
echo -e "\n${GREEN}Step 3: Checking application key...${NC}"
if ! grep -q "APP_KEY=base64:" .env 2>/dev/null; then
    echo -e "${YELLOW}Application key not found. Generating...${NC}"
    php artisan key:generate --force
else
    echo -e "${GREEN}Application key already exists${NC}"
fi

# Step 4: Generate JWT secret if not set
echo -e "\n${GREEN}Step 4: Checking JWT secret...${NC}"
if ! grep -q "JWT_SECRET=" .env 2>/dev/null || grep -q "JWT_SECRET=$" .env 2>/dev/null; then
    echo -e "${YELLOW}JWT secret not found. Generating...${NC}"
    php artisan jwt:secret --force
else
    echo -e "${GREEN}JWT secret already exists${NC}"
fi

# Step 5: Run migrations (optional - comment out if you don't want to run migrations during build)
# echo -e "\n${GREEN}Step 5: Running database migrations...${NC}"
# php artisan migrate --force

# Step 6: Optimize Laravel
echo -e "\n${GREEN}Step 6: Optimizing Laravel application...${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Step 7: Optimize Composer autoloader
echo -e "\n${GREEN}Step 7: Optimizing Composer autoloader...${NC}"
composer dump-autoload --optimize --classmap-authoritative

# Step 8: Set proper permissions
echo -e "\n${GREEN}Step 8: Setting storage and cache permissions...${NC}"
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || echo -e "${YELLOW}Note: Could not set ownership (may require sudo)${NC}"

# Step 9: Create storage link if it doesn't exist
echo -e "\n${GREEN}Step 9: Creating storage symlink...${NC}"
php artisan storage:link 2>/dev/null || echo -e "${YELLOW}Storage link already exists${NC}"

# Build complete
echo -e "\n${GREEN}✅ Build completed successfully!${NC}"
echo -e "\n${GREEN}Next steps:${NC}"
echo -e "1. Review your .env file configuration"
echo -e "2. Run migrations: ${YELLOW}php artisan migrate${NC}"
echo -e "3. Seed database (optional): ${YELLOW}php artisan db:seed${NC}"
echo -e "4. Start server: ${YELLOW}php artisan serve${NC}"
echo -e "\n${GREEN}For production, use a proper web server (Nginx/Apache) with PHP-FPM${NC}"

