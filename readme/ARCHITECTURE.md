# Portus System Architecture

Complete system architecture documentation covering all components, their interactions, and data flows.

---

## 🏛️ System Overview

Portus is a comprehensive transportation management platform consisting of three integrated applications:

```
┌────────────────────────────────────────────────────────────┐
│           PORTUS TRANSPORTATION MANAGEMENT SYSTEM           │
├────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────────────┐  ┌──────────────────┐                 │
│  │   WEB DASHBOARD  │  │  MOBILE APP      │                 │
│  │   (React 19)     │  │  (React Native)  │                 │
│  │  - Admin/Ops     │  │  - Drivers       │                 │
│  │  - Monitoring    │  │  - Tasks         │                 │
│  │  - Reports       │  │  - Notifications │                 │
│  └────────┬─────────┘  └────────┬─────────┘                 │
│           │                      │                           │
│           └──────────┬───────────┘                           │
│                      │                                       │
│              ┌───────▼───────┐                               │
│              │  BACKEND API  │                               │
│              │   (PHP 8.1)   │                               │
│              │               │                               │
│              │ • REST Routes │                               │
│              │ • WebSocket   │                               │
│              │ • Auth/Auth   │                               │
│              │ • Business    │                               │
│              │   Logic       │                               │
│              └───────┬───────┘                               │
│                      │                                       │
│    ┌─────────────────┼─────────────────┐                    │
│    │                 │                 │                    │
│    ▼                 ▼                 ▼                     │
  │  DATABASE         REDIS            WEBSOCKET               │
  │  (MySQL)          (Event Queue)    (Ratchet)               │
│                                                              │
└────────────────────────────────────────────────────────────┘
```

---

## 📦 Component Architecture

### 1. Frontend Layer

#### Web Dashboard (`New-Portus-Dasboard`)

*Actual structure located on developer desktop — not in this repository.*

Typical structure:
```
src/
├── App.tsx                      # Root component, routing
├── pages/                       # Page-level components
├── components/                 # Reusable UI components
├── store/
│   ├── index.ts               # Store configuration
│   ├── hooks.ts               # useAppDispatch, useAppSelector
│   └── slices/                # Redux slices (fahrer, chassi, task, etc.)
├── services/
│   ├── api.ts                 # API client setup
│   ├── websocket.ts           # WebSocket client
│   └── storage.ts             # LocalStorage helpers
├── types/                     # TypeScript interfaces
├── css/                       # Styles
└── index.tsx                  # Entry point
```

**Technology Stack:**
- React 19 with hooks
- Redux Toolkit for state management
- React Router v7 for navigation
- TypeScript for type safety
- Leaflet for map visualization
- React Icons for UI icons

---

#### Mobile App (`DriverPortal`)

*Actual structure located on developer desktop — not in this repository.*

Typical structure:
```
src/
├── App.tsx                      # Navigation setup
├── screens/                     # Screen components
├── components/                 # Reusable components
├── store/
│   ├── index.ts               # Store configuration
│   ├── hooks.ts
│   ├── persistConfig.ts        # Redux Persist setup
│   └── slices/                # Redux slices
├── services/
│   ├── api.ts
│   ├── websocket.ts
│   ├── notifications.ts        # Expo notifications
│   ├── location.ts            # Geolocation service
│   └── storage.ts
├── types/
│   └── index.ts
├── img/                       # Images & assets
└── index.ts                   # Entry point
```

**Technology Stack:**
- React Native 0.81
- Expo 54 for managed React Native
- Redux Toolkit with Redux Persist
- React Navigation v7
- TypeScript
- Expo Notifications for push
- Expo Location for GPS

---

### 2. Backend Layer

#### API Server (`portusApp1`)
```
api/
├── index.php                         # Single-entry router (312 lines)
├── config/
│   ├── db.php                        # PDO singleton
│   ├── env.php                       # .env loader
│   └── mail.php                      # Email config (disabled)
├── classes/                          # Business logic (22 files)
│   ├── Auth.php                      # Token-based auth
│   ├── classChassi.php               # Trailer CRUD
│   ├── classDepartments.php          # Department CRUD
│   ├── classDetachLocations.php      # Map detach locations
│   ├── classFahrer.php               # Driver CRUD + login
│   ├── classFaultReports.php         # Driver fault reports
│   ├── classInspections.php          # Pre-trip inspections
│   ├── classInventory.php            # Warehouse/inventory
│   ├── classLkw.php                  # Truck CRUD
│   ├── classMessageChassi.php        # Chassi chat messages
│   ├── classMessageLkw.php           # LKW chat messages
│   ├── classNotifications.php        # Notifications
│   ├── classTasks.php                # Task management
│   ├── classUsers.php                # User CRUD + login
│   ├── classVacations.php            # Vacation management
│   ├── classVehicleDocuments.php     # Vehicle document uploads
│   ├── classVehicleHistory.php       # Vehicle assignment history
│   ├── EmailNotifier.php             # Email helper (disabled)
│   ├── ExpoPush.php                  # Expo push notifications
│   ├── files_chassi.php              # File uploads (chassi chat)
│   ├── files_lkw.php                 # File uploads (lkw chat)
│   └── Realtime.php                  # Redis event publisher
├── uploads/                          # User uploaded files
│   ├── chassi/
│   ├── documents/
│   └── lkw/

realtime/
├── ws-server.php                     # Ratchet entry point (103 lines)
└── src/
    └── Hub.php                       # Portus\Realtime\Hub

database/                             # SQL migration scripts (13 files)
├── add_notizen_column.sql
├── auth_tokens_schema.sql
├── driver_features.sql
├── fahrer_driver_code_history.sql
├── fahrer_push_token.sql
├── inventory_schema.sql
├── lkw_add_truck_info.sql
├── rate_limiting_schema.sql
├── tasks_status_4_values.sql
├── users_email_to_username.sql
├── vacations_schema.sql
└── widen_axle_columns.sql

cron/                                 # Scheduled scripts
├── check_deadlines.php               # Deadline reminders
├── cleanup_old_vacations.php         # Purge old vacations
├── cleanup_tokens.php                # Purge expired tokens
└── tuv_reminders.php                 # TÜV/SP reminders

docker/                               # Docker configs
├── apache.conf                       # Apache vhost
└── crontab                           # Cron schedule

.env                                  # Environment configuration
composer.json                         # PHP dependencies
docker-compose.yml                    # Docker services
Dockerfile                            # PHP 8.2-apache image
```

**Technology Stack:**
- PHP 8.1+
- Ratchet WebSocket library (cboden/ratchet ^0.4.4)
- Predis for Redis operations (predis/predis ^2.2)
- Custom token-based authentication (no JWT library)
- Direct PDO queries (no ORM/framework)

---

### 3. Data Layer

#### Database Schema
```sql
-- Authentication tokens (custom token-based auth, no JWT)
auth_tokens (
  id, token, subject_type ENUM('user','fahrer'),
  subject_id, expires_at, created_at
)
fahrer_reg_attempts (id, ip_address, attempted_at)

-- Users (administrative employees)
users (
  id, username, name, password, role, department,
  email, phone, terms, created_at, updated_at
)

-- Departments
departments (id, name, color, sort_order, created_at, updated_at)

-- Drivers
fahrer (
  id, name, lastname, driver_code, password,
  lkw NUMBER, chassi NUMBER, phone, push_token,
  terms, created_at, updated_at
)

-- Trucks
lkw (
  id_lkw, lkw_nummer, tuf, esp, adr, a_schild,
  achse_1_links, achse_1_rechts, achse_2_links, achse_2_rechts,
  feuerloescher_1, feuerloescher_2, warndreieck, verbandkasten,
  kranken_trage, notizen,
  status, created_at, updated_at
)

-- Trailers
chassi (
  id_chassi, chassi_nummer, tuf, esp, adr, a_schild,
  achse_1_links, achse_1_rechts, achse_2_links, achse_2_rechts,
  feuerloescher_1, feuerloescher_2,
  notizen,
  created_at, updated_at
)

-- Vehicle history (driver-vehicle assignments)
vehicle_history (
  id, lkw_id, chassi_id, driver_id,
  driver_name, event, notes, timestamp
)

-- Vehicle documents (uploaded files)
vehicle_documents (
  id, vehicle_type ENUM('lkw','chassi'), vehicle_id,
  file_name, file_path, file_type, uploaded_by, created_at
)

-- Tasks
tasks (
  id, title, description, chauffeur, lkw, chassi,
  priority, deadline, status ENUM('new','in_progress','clarification','done'),
  file, created_at, updated_at
)
task_comments (id, task_id, user_id, comment, created_at)

-- Notifications
notifications (
  id, user_id, fahrer_id, type, title, body,
  related_type, related_id, read, read_at,
  created_at, updated_at
)

-- Fault reports (driver-reported)
fault_reports (
  id, fahrer_id, lkw_id, chassi_id, description,
  report_date, status, created_at, updated_at
)

-- Pre-trip inspections
inspections (
  id, fahrer_id, lkw_id, chassi_id,
  checklist JSON, notes, status, created_at
)

-- Chat messages
message_lkw (id, lkw_id, user_id, message, created_at)
message_chassi (id, chassi_id, user_id, message, created_at)
files_lkw (id, lkw_id, user_id, filename, filepath, created_at)
files_chassi (id, chassi_id, user_id, filename, filepath, created_at)

-- Vacations
vacations (
  id, user_id, fahrer_id, start_date, end_date,
  reason, status, created_at, updated_at
)

-- Warehouse / Inventory
inventory_items (id, name, category_id, unit_id, quantity, min_stock, ...)
inventory_categories (id, name)
inventory_units (id, name)
inventory_transactions (id, item_id, type, quantity, note, created_at)
```

#### Redis Usage
```
# Event queue (polled by WebSocket server, not Pub/Sub)
portus:events                # JSON event list — API pushes, WS pops (250ms polling)

# Typical event payloads:
#   { "type": "entity_changed", "target": "all|user|room",
#     "entity": "lkw|chassi|fahrer|tasks|notifications|...",
#     "data": {...} }
```

---

## 🔄 Communication Patterns

### Request-Response (REST)
```
Client (Web/Mobile)
  │
  ├─► HTTP Request to /{endpoint} (no /api/ prefix)
  │   - Headers: Authorization: Bearer {token}, Content-Type
  │   - Method: GET, POST, PUT, DELETE
  │   - Body: JSON payload (if needed)
  │
  └─ HTTP Response (JSON)
      - Status code: 200, 201, 400, 401, 500, etc.
      - Body: { "error": false, "data": {...} } or { "error": true, "message": "..." }
      - Headers: Content-Type: application/json
      - Auth: Token-based (64-char hex via bin2hex(random_bytes(32)))
```

### WebSocket (Real-time)
```
Client connects → ws://host:8090?token={auth_token}
  │
  ├─► On connect: Hub validates token against auth_tokens table
  │
  ├─► Subscribe to rooms
  │   { type: "subscribe", room: "entity:123" }
  │
  ├─ Listen for events (polled from Redis list portus:events every 250ms)
  │  ◄─ { type: "event", entity: "...", data: {...} }
  │
  └─ Heartbeat (keepalive via TCP)
     (No ping/pong — relies on Ratchet's connection management)
```

### Event Broadcast Flow
```
API performs POST/PUT/DELETE
  │
  ├─► Realtime::entityChanged() pushes JSON to Redis list (rpush portus:events)
  │
  ├─► WebSocket Hub polls Redis list (lpop, up to 100 per tick)
  │
  ├─► Hub dispatches to matched connections
  │    target "all" → broadcast to everyone
  │    target "user" → send to specific user_id
  │    target "room" → send to room subscribers
  │
  └─ Clients receive event, update Redux state
```

---

## 🔐 Security Architecture

### Authentication Flow
```
1. Client sends credentials (username/email + password)
   ↓
2. Backend verifies credentials via Users or Fahrer class
   ↓
3. Backend generates 64-char hex token (bin2hex(random_bytes(32)))
   - Stored in auth_tokens table with subject_type + subject_id
   - No JWT library used
   ↓
4. Token sent to client
   ↓
5. Client stores token
   - Web: localStorage.getItem('auth_token')
   - Mobile: AsyncStorage.getItem('auth_token')
   ↓
6. Client includes token in requests
   - Header: Authorization: Bearer {token}
   ↓
7. Backend resolves token via Auth::resolve() — looks up auth_tokens table
   ↓
8. Request processed with authorized user context (Auth::currentUser() / Auth::currentFahrer())
```

### Authorization Rules
```
Simple role-based access:
- Users (employees): Full admin access to dashboard
- Fahrer (drivers): Limited access — login via /fahrer/login, own data only

Auth token subject types:
- ENUM 'user': Administrative employee (dashboard access)
- ENUM 'fahrer': Driver (mobile app access)
```

### Data Security
```
1. HTTPS/TLS for all communication
2. Passwords hashed (password_hash/password_verify)
3. CORS configured in .htaccess or Apache config
4. Rate limiting on driver registration endpoint (IP-based)
5. Input validation & prepared statements (PDO)
6. Apache rewrite rules (.htaccess → api/index.php)
```

---

## 📈 Performance Considerations

### Caching Strategy
```
Server-side: No Redis caching implemented (Redis used only as event queue)
  └─ Future optimization opportunity

Client-side:
  ├─ Redux store (session duration)
  ├─ LocalStorage (web, persistent)
  ├─ AsyncStorage (mobile, persistent)
  └─ Browser cache (static assets)

Cache invalidation:
  └─ Manual refresh (force re-fetch from API)
```

### Database Optimization
```
Indexing:
- Primary keys on all tables
- Foreign key columns where applicable

Query pattern:
- Direct PDO queries (no ORM overhead)
- Pagination for list endpoints (LIMIT/OFFSET)
- No connection pooling (single connection per request)
```

### API Rate Limiting
```
No built-in rate limiting for most endpoints.
Driver registration has IP-based rate limiting (fahrer_reg_attempts table).
```

---

## 🚀 Deployment Architecture

### Development Environment
```
Local Machine (XAMPP)
├── Apache server (port 80) — PHP API via .htaccess rewrite
├── React dev server (port 3000)
├── Expo dev server
├── Local MySQL database
└── Local Redis instance (optional, for WebSocket events)
```

### Docker Environment (docker-compose.yml)
```
6 services:
├── app  (Apache + PHP 8.2)      port 8888
├── cron                          scheduled tasks
├── db   (MySQL 8.0)             port 3306
├── phpmyadmin                    port 8889
├── redis (Redis 7)              port 6379
└── ws   (Ratchet WebSocket)     port 8090
```

### Production Environment
```
Single Server or VPS
├── Apache/Nginx serving PHP API
├── React static build served by web server
├── MySQL database
├── Redis server (event queue)
├── Ratchet WebSocket server (port 8090)
└── SSL/TLS certificates

App Distribution
├── Web dashboard (Static hosting)
├── Android (APK distribution)
└── iOS (Enterprise distribution)
```

---

## 📊 Monitoring & Observability

### Logging
```
Backend logs:
- PHP error_log (Apache error log)
- error_log() calls throughout API classes

WebSocket logs:
- ws-server.php outputs to stdout/stderr (visible in Docker logs)

Client logs:
- Browser console (Dev Tools)
- Expo logs (Expo CLI)
- Redux actions/state changes
- Network requests
```

### Metrics to Monitor
```
Backend:
- PHP error rates (Apache error log)
- API response times (browser Network tab)
- Active WebSocket connections (Hub connection count)
- Redis event queue size
- CPU & memory usage

Frontend:
- Page load time
- Console errors/warnings
- Redux state size
- Network waterfall
```

---

## 🔄 Deployment Pipeline

### Deployment (Docker)
```bash
# Start all services
docker-compose up -d

# Apply database migrations
docker-compose exec db mysql -u root -p portusapp1 < database/add_notizen_column.sql

# Rebuild PHP app after changes
docker-compose up -d --build app

# Check logs
docker-compose logs -f ws    # WebSocket server
docker-compose logs -f app   # PHP API
```

### Deployment Steps (Manual / XAMPP)
```
Backend:
1. Pull latest code (git pull)
2. Run composer install (if deps changed)
3. Apply any new SQL migrations
4. Restart Apache
5. Restart WebSocket server

Dashboard:
1. npm run build
2. Upload build/ to web root
3. Clear browser cache

Mobile:
1. Update API endpoint in config
2. Build APK via Expo
3. Distribute to drivers
```

---

## 🛠️ Troubleshooting Guide

### Common Issues & Solutions

**API Connection Issues**
```
Symptom: Client can't reach backend
Debug:
1. Check backend is running: curl http://localhost/api - or - http://localhost:8888 (Docker)
2. Check .htaccess rewrite rules (requests → api/index.php)
3. Check CORS configuration in Apache vhost
4. Check token validity: Verify token exists in auth_tokens table
Solution: Restart Apache, check .htaccess, verify network
```

**WebSocket Connection Issues**
```
Symptom: Real-time updates not working
Debug:
1. Check WebSocket server: ws://host:8090
2. Check Redis is running: redis-cli ping
3. Check Redis list: LLEN portus:events
4. Check WS server logs: docker logs ws (or stdout)
Solution: Restart WS server, restart Redis, check port 8090
```

**State Synchronization Issues**
```
Symptom: Stale data in UI
Debug:
1. Check Redux state: Redux DevTools
2. Check API response: Network tab
3. Check Redis events: redis-cli LRANGE portus:events 0 -1
Solution: Force refresh, clear local storage, re-login
```

---

## 📚 Architecture Decisions

### Why WebSocket + REST?
- **REST** for CRUD operations (unchanged data)
- **WebSocket** for real-time event push (notifications, data changes)
- Combination provides best of both worlds

### Why Redis as Event Queue (not Pub/Sub)?
- Simple list-based approach (LPUSH/BRPOP)
- No need for Pub/Sub subscriber management
- API writes events, WS server polls periodically (250ms)
- Sufficient for event volumes in this application

### Why No ORM/Framework?
- Direct PDO queries are simpler for this scale
- No Laravel overhead
- Full control over SQL
- 22 classes with clear separation of concerns
- Single-file router (api/index.php) handles all routing

### Why Expo for mobile?
- Managed React Native
- Quick development & iteration
- Push notifications built-in (ExpoPush service)
- Cross-platform (Android, iOS, Web)

---

**Last Updated**: 2026-07-15  
**Architecture Version**: 2.0 (corrected to reflect actual flat PHP structure)  
**Status**: Updated
