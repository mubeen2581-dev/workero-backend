#!/bin/bash

# Workero Backend Server Deployment Script
# Run this script on your Laravel Forge server to set up and deploy the backend

set -e  # Exit on error

echo "🚀 Starting Workero Backend Server Deployment..."
echo ""

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Get the current directory
CURRENT_DIR=$(pwd)
echo -e "${BLUE}Current directory: ${CURRENT_DIR}${NC}"

# Check if we're in the backend directory or need to navigate
if [ ! -f "artisan" ]; then
    if [ -d "backend" ]; then
        echo -e "${YELLOW}Navigating to backend directory...${NC}"
        cd backend
    else
        echo -e "${RED}Error: artisan file not found. Please run this script from the backend directory or project root.${NC}"
        exit 1
    fi
fi

BACKEND_DIR=$(pwd)
echo -e "${GREEN}Backend directory: ${BACKEND_DIR}${NC}"
echo ""

# Step 1: Check if .env exists
echo -e "${BLUE}Step 1: Checking environment file...${NC}"
if [ ! -f ".env" ]; then
    echo -e "${YELLOW}Warning: .env file not found.${NC}"
    if [ -f "env.example.commented" ]; then
        echo -e "${YELLOW}Creating .env from env.example.commented...${NC}"
        cp env.example.commented .env
        echo -e "${YELLOW}⚠️  IMPORTANT: Please edit .env file with your production settings!${NC}"
        echo -e "${YELLOW}   Run: nano .env${NC}"
        echo -e "${YELLOW}   Then run this script again.${NC}"
        exit 1
    else
        echo -e "${RED}Error: No .env or env.example.commented file found.${NC}"
        exit 1
    fi
else
    echo -e "${GREEN}✓ .env file exists${NC}"
fi
echo ""

# Step 2: Install/Update Composer Dependencies
echo -e "${BLUE}Step 2: Installing Composer dependencies...${NC}"
composer install --no-dev --optimize-autoloader --no-interaction
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Dependencies installed${NC}"
else
    echo -e "${RED}✗ Failed to install dependencies${NC}"
    exit 1
fi
echo ""

# Step 3: Generate application key if not set
echo -e "${BLUE}Step 3: Checking application key...${NC}"
if ! grep -q "APP_KEY=base64:" .env 2>/dev/null; then
    echo -e "${YELLOW}Application key not found. Generating...${NC}"
    php artisan key:generate --force
    echo -e "${GREEN}✓ Application key generated${NC}"
else
    echo -e "${GREEN}✓ Application key already exists${NC}"
fi
echo ""

# Step 4: Generate JWT secret if not set
echo -e "${BLUE}Step 4: Checking JWT secret...${NC}"
if ! grep -q "JWT_SECRET=" .env 2>/dev/null || grep -q "JWT_SECRET=$" .env 2>/dev/null; then
    echo -e "${YELLOW}JWT secret not found. Generating...${NC}"
    php artisan jwt:secret --force
    echo -e "${GREEN}✓ JWT secret generated${NC}"
else
    echo -e "${GREEN}✓ JWT secret already exists${NC}"
fi
echo ""

# Step 5: Create required directories
echo -e "${BLUE}Step 5: Creating required directories...${NC}"
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache
echo -e "${GREEN}✓ Directories created${NC}"
echo ""

# Step 6: Set permissions
echo -e "${BLUE}Step 6: Setting permissions...${NC}"
chmod -R 775 storage bootstrap/cache
chown -R forge:forge storage bootstrap/cache 2>/dev/null || echo -e "${YELLOW}Note: Could not set ownership (may require sudo)${NC}"
echo -e "${GREEN}✓ Permissions set${NC}"
echo ""

# Step 7: Test database connection
echo -e "${BLUE}Step 7: Testing database connection...${NC}"
php artisan migrate:status > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Database connection successful${NC}"
else
    echo -e "${RED}✗ Database connection failed${NC}"
    echo -e "${YELLOW}Please check your database credentials in .env file${NC}"
    echo -e "${YELLOW}DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD${NC}"
    exit 1
fi
echo ""

# Step 8: Run migrations
echo -e "${BLUE}Step 8: Running database migrations...${NC}"
read -p "Do you want to run migrations? (y/n): " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    php artisan migrate --force
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Migrations completed${NC}"
    else
        echo -e "${RED}✗ Migrations failed${NC}"
        exit 1
    fi
else
    echo -e "${YELLOW}Skipping migrations. Run manually: php artisan migrate --force${NC}"
fi
echo ""

# Step 9: Build/optimize
echo -e "${BLUE}Step 9: Building and optimizing application...${NC}"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
composer dump-autoload --optimize --classmap-authoritative
echo -e "${GREEN}✓ Application optimized${NC}"
echo ""

# Step 10: Create storage link
echo -e "${BLUE}Step 10: Creating storage symlink...${NC}"
php artisan storage:link 2>/dev/null || echo -e "${YELLOW}Storage link already exists${NC}"
echo -e "${GREEN}✓ Storage link created${NC}"
echo ""

# Step 11: Verify environment settings
echo -e "${BLUE}Step 11: Verifying environment settings...${NC}"
APP_ENV=$(grep "^APP_ENV=" .env | cut -d '=' -f2)
APP_DEBUG=$(grep "^APP_DEBUG=" .env | cut -d '=' -f2)
APP_URL=$(grep "^APP_URL=" .env | cut -d '=' -f2)

if [ "$APP_ENV" != "production" ]; then
    echo -e "${YELLOW}⚠️  Warning: APP_ENV is set to '${APP_ENV}' (should be 'production')${NC}"
fi

if [ "$APP_DEBUG" != "false" ]; then
    echo -e "${RED}⚠️  CRITICAL: APP_DEBUG is set to '${APP_DEBUG}' (should be 'false' for production)${NC}"
fi

if [ -z "$APP_URL" ] || [[ "$APP_URL" == *"localhost"* ]]; then
    echo -e "${YELLOW}⚠️  Warning: APP_URL may not be set correctly: ${APP_URL}${NC}"
fi

echo -e "${GREEN}✓ Environment check completed${NC}"
echo ""

# Deployment complete
echo -e "${GREEN}════════════════════════════════════════${NC}"
echo -e "${GREEN}✅ Deployment completed successfully!${NC}"
echo -e "${GREEN}════════════════════════════════════════${NC}"
echo ""
echo -e "${BLUE}Next steps:${NC}"
echo -e "1. Verify your .env file has correct production settings"
echo -e "2. Test the API: curl https://api-workero.xepos.co.uk/api/"
echo -e "3. Check Laravel Forge site settings:"
echo -e "   - Web Directory: /public"
echo -e "   - App Root: ${BACKEND_DIR}"
echo -e "4. Monitor logs: tail -f storage/logs/laravel.log"
echo ""
echo -e "${GREEN}Your backend should now be running via Nginx + PHP-FPM!${NC}"
echo ""

