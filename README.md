# Workero Backend API

Laravel-based REST API for the Workero field service management platform.

## 🏗️ Architecture

- **Framework:** Laravel 10
- **Database:** MySQL (Multi-tenant)
- **Authentication:** JWT (tymon/jwt-auth)
- **API:** RESTful JSON API

## 📋 Prerequisites

- PHP 8.1+
- Composer 2.0+
- MySQL 8.0+

## 🚀 Quick Start

### 1. Install Dependencies

```bash
cd backend
composer install
```

### 2. Environment Setup

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Generate JWT secret
php artisan jwt:secret
```

### 3. Configure Database

Edit `.env` file with your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=workero
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 4. Run Migrations

```bash
php artisan migrate
```

### 5. Start Development Server

```bash
# Option 1: Using npm (from backend directory)
npm run dev

# Option 2: Using PHP Artisan directly
php artisan serve --host=0.0.0.0 --port=3001
```

The API will be available at: `http://localhost:3001/api`

## 📝 Available Scripts

From the `backend` directory:

```bash
npm run dev          # Start Laravel development server
npm run start        # Alias for dev
npm run build        # Optimize Laravel for production
npm run test         # Run PHPUnit tests
npm run migrate      # Run database migrations
npm run migrate:fresh # Fresh migration with seeding
npm run seed         # Seed database
npm run key:generate # Generate application key
npm run jwt:secret   # Generate JWT secret
```

## 🔧 Configuration

### Environment Variables

All configuration is done through the `.env` file. See `.env.example` for all available options with detailed comments.

Key sections:
- **Application:** App name, environment, debug mode
- **Database:** MySQL connection settings
- **JWT:** Authentication secret and TTL
- **XEPOS Cloud:** XE Pay, XE AI, XE Accounts integrations
- **WhatsHub:** WhatsApp Business API integration
- **Google Services:** Maps and Calendar APIs

### Service Configuration

External service configurations are in `config/services.php`. Update your `.env` file to configure:

- XE Pay API (Payment processing)
- XE AI API (AI features)
- WhatsHub API (WhatsApp messaging)
- Google Maps API
- Google Calendar API

## 📚 API Endpoints

### Authentication
- `POST /api/auth/login` - Login
- `POST /api/auth/logout` - Logout
- `POST /api/auth/refresh` - Refresh token
- `GET /api/auth/me` - Get current user
- `POST /api/auth/forgot-password` - Request password reset
- `POST /api/auth/reset-password` - Reset password

### Resources
See `INSTALLATION.md` for complete API documentation.

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter AuthTest
```

## 📦 Deployment

1. Set `APP_ENV=production` in `.env`
2. Set `APP_DEBUG=false` in `.env`
3. Run `php artisan config:cache`
4. Run `php artisan route:cache`
5. Run `php artisan view:cache`
6. Optimize: `php artisan optimize`

## 📖 Documentation

- [Installation Guide](./INSTALLATION.md)
- [Backend Tasks Completed](./BACKEND_TASKS_COMPLETED.md)
- [Laravel Documentation](https://laravel.com/docs/10.x)

## 🔐 Security

- All passwords are hashed using bcrypt
- JWT tokens for API authentication
- Multi-tenant isolation via middleware
- CSRF protection enabled
- SQL injection prevention via Eloquent ORM

## 🐛 Troubleshooting

### Common Issues

**Issue:** `Unable to load the "app" configuration file`
- **Solution:** Ensure all config files exist in `config/` directory

**Issue:** `JWT secret not set`
- **Solution:** Run `php artisan jwt:secret`

**Issue:** Database connection failed
- **Solution:** Check database credentials in `.env` and ensure MySQL is running

For more help, see `INSTALLATION.md`.
