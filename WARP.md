# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

**Plataforma PIDE** is a full-stack service platform consisting of a Laravel 12 backend API and Vue.js 3 frontend with Ant Design Vue components. The system manages a 3-phase service lifecycle: Publisher requests → Admin approval → Service publication for consumers.

## Architecture

### Backend (Laravel 12)
- **Location**: `backend/`
- **Framework**: Laravel 12 with PHP 8.2+
- **Architecture**: MVC with clear role-based controllers:
  - `Api/` - Core API endpoints
  - `Admin/` - Administrator-specific operations  
  - `Publicador/` - Publisher-specific operations
  - `Consumidor/` - Consumer-specific operations

### Frontend (Vue.js 3)
- **Location**: `frontend/`
- **Framework**: Vue 3 with Vite, Vue Router, Ant Design Vue
- **Architecture**: Role-based page structure:
  - `Pages/Admin/` - Administrative interfaces
  - `Pages/Publicador/` - Publisher interfaces  
  - `Pages/Consumidor/` - Consumer interfaces
  - `Layouts/` - Shared layout components

### Core Models
- `ServiceRequest` - Phase 1: Service proposals from publishers
- `EnhancedService` - Phase 3: Approved and published services
- `Service` - Base service model
- `ServiceUsage`, `ServicePlan` - Usage tracking and subscriptions
- `User` - Multi-role user system

## Development Commands

### Backend (Laravel)
```bash
# Development server
cd backend && php artisan serve

# Database operations
php artisan migrate
php artisan migrate:fresh --seed

# Testing
php artisan test
vendor/bin/phpunit

# Code quality
php artisan route:list
php artisan config:cache
```

### Frontend (Vue.js)
```bash
# Development server
cd frontend && npm run dev

# Build for production  
npm run build

# Preview production build
npm run preview
```

### Docker Development
```bash
# Start all services
docker-compose up -d

# View logs
docker-compose logs -f backend
docker-compose logs -f frontend
```

### Lando Development (Alternative)
```bash
# Start Lando environment
lando start

# Access Laravel commands
lando php artisan [command]

# Frontend development
lando node npm run dev
```

## Service Workflow (3-Phase System)

### Phase 1: Service Request
Publishers create service requests via `ServiceRequestController`. Requests include technical specifications, documentation, and business justification with status `pending_review`.

### Phase 2: Admin Approval  
Administrators review requests via `ServiceApprovalController` and can:
- Approve → Creates `EnhancedService` with status `ready_to_publish`
- Reject → With detailed feedback
- Request modifications → For corrections

### Phase 3: Publication
Publishers configure operational parameters (schedules, limits, access control) and publish services to consumers via `ServiceRegistrationController`.

## API Structure

### Key Endpoints
- `GET /api/dashboard/*` - Analytics and KPIs
- `GET /api/catalog/services` - Consumer service catalog
- `POST /api/service-requests` - Phase 1: Publisher submissions
- `POST /api/admin/services/{slug}/approve` - Phase 2: Admin approval
- `POST /api/publicador/services` - Service management
- `PUT /api/services/{id}/publish` - Phase 3: Publication

### Response Format
APIs return standardized format for frontend compatibility:
```json
{
  "id": 1,
  "slug": "service-name-123", 
  "name": "Service Name",
  "status": "revision|aprobado|rechazado",
  "type": "api-rest|soap|graphql",
  "authType": "OAuth 2.0|API Key",
  "schedule": "24x7|office",
  "monthlyLimit": 30000
}
```

## Database

- **Development**: SQLite (default)
- **Docker**: MySQL 8.0 on port 3307
- **Lando**: MariaDB 11.4
- **Migration Files**: Located in `backend/database/migrations/`

Key tables: `services`, `service_requests`, `enhanced_services`, `service_usages`, `service_plans`

## Development Environment

### Prerequisites
- PHP 8.2+
- Composer
- Node.js 22+
- npm
- Docker (optional)
- Lando (optional)

### Quick Start
1. Backend: `cd backend && composer install && php artisan migrate && php artisan serve`
2. Frontend: `cd frontend && npm install && npm run dev`
3. Access: Backend at http://localhost:8000, Frontend at http://localhost:5173

### Testing
- **Backend**: PHPUnit configured with Unit and Feature test suites
- **Frontend**: No test framework currently configured
- Run tests: `cd backend && php artisan test`

## Role-Based Architecture

The codebase is organized around three user roles:
- **Admin**: Service approval workflow, system analytics
- **Publicador (Publisher)**: Service request creation and management  
- **Consumidor (Consumer)**: Service catalog browsing and consumption

Each role has dedicated controllers, routes, and frontend pages following consistent naming conventions.