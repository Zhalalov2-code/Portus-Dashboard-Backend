# Portus Dashboard — Context Management

## 📋 Project Overview

**Portus** — integrated transportation management system с тремя основными компонентами:

- **Backend (portusApp1)**: PHP 8.1+ REST API + WebSocket server
- **Web Dashboard (New-Portus-Dashboard)**: React 19 веб-приложение для управления
- **Mobile App (DriverPortal)**: React Native (Expo) мобильное приложение для водителей

---

## 🏗️ Architecture

### System Diagram
```
┌─────────────────────────────────────────────────────────────┐
│                      PORTUS SYSTEM                           │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌──────────────────┐    ┌──────────────────┐                │
│  │ Web Dashboard    │    │  Driver Portal   │                │
│  │  (React 19)      │    │  (React Native)  │                │
│  └────────┬─────────┘    └────────┬─────────┘                │
│           │ HTTP REST             │ HTTP REST                │
│           │ WebSocket             │ WebSocket                │
│           └────────────┬──────────┘                           │
│                        │                                      │
│              ┌─────────▼──────────┐                           │
│              │  Backend API       │                           │
│              │  (PHP 8.1)         │                           │
│              │                    │                           │
│              │ - REST endpoints   │                           │
│              │ - WebSocket server │                           │
│              │ - Redis event queue│                           │
│              └─────────┬──────────┘                           │
│                        │                                      │
│              ┌─────────▼──────────┐                           │
│              │   Database         │                           │
│              │   + Redis          │                           │
│              └────────────────────┘                           │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

#### 1. **Backend (portusApp1)**
- **Location**: `C:\xampp\htdocs\portusApp1`
- **Stack**: PHP 8.1+, Ratchet WebSockets, Redis (Predis)
- **Architecture**: Flat PHP (no framework) — single router `api/index.php`, 22 classes in `api/classes/`
- **Key Features**:
  - REST API endpoints для веб и мобильных клиентов
  - Real-time WebSocket server для push-уведомлений
  - Redis event queue для WebSocket broadcasting
  - Custom token-based authentication (no JWT)
  - Data persistence via PDO (MySQL)

#### 2. **Web Dashboard (New-Portus-Dashboard)**
- **Location**: `C:\Users\info\Desktop\New-Portus-Dasboard`
- **Stack**: React 19, TypeScript, Redux Toolkit
- **Key Features**:
  - Управление автопарком (vehicles, drivers, tasks)
  - Интерактивные карты (Leaflet)
  - Real-time обновления через WebSocket
  - Redux для глобального состояния
  - Responsive UI (React Router для навигации)

#### 3. **Mobile App (DriverPortal)**
- **Location**: `C:\Users\info\Desktop\Chassi App\DriverPortal`
- **Stack**: React Native (Expo), TypeScript, Redux Toolkit
- **Platforms**: Android, iOS, Web
- **Key Features**:
  - Водительский портал для просмотра заданий
  - Push notifications (Expo Notifications)
  - Offline-first (AsyncStorage)
  - Redux для состояния
  - Navigation (React Navigation)

---

## 🔄 Data Flow

### Request-Response Cycle
```
Client (Web/Mobile)
    │
    ├─► HTTP REST Request → Backend API
    │                          │
    │                     (Process request)
    │                          │
    └─ HTTP Response ◄─────────┘

Real-time Updates
    │
    ├─► WebSocket Connection (established)
    │                          │
    │                    (Listen for events)
    │                          │
    └─ Event Data ◄───────────┘ (Redis broadcast)
```

### Shared State Management
- **Redux Slices** (в обоих клиентах):
  - `fahrerSlice`: водители и их состояние
  - `chassiSlice`: автомобили и парк

---

## 📁 Project Structure

### Backend
```
portusApp1/
├── composer.json              # Dependencies (Ratchet, Predis)
├── api/
│   ├── index.php              # Single-entry router (all routes)
│   ├── classes/               # Business logic (22 classes)
│   └── config/                # DB, env, mail config
├── realtime/
│   ├── ws-server.php          # WebSocket entry point
│   └── src/
│       └── Hub.php            # Portus\Realtime\Hub
├── database/                  # SQL migration scripts
├── cron/                      # Scheduled maintenance scripts
├── docker/                    # Apache vhost, crontab
├── .env                       # Environment configuration
└── vendor/                    # Composer dependencies
```

### Web Dashboard
```
New-Portus-Dasboard/
├── package.json
├── src/
│   ├── App.tsx               # Root component + routing
│   ├── components/           # React components
│   ├── pages/                # Page components
│   ├── store/
│   │   ├── slices/          # Redux slices
│   │   └── hooks.ts         # Redux hooks
│   ├── css/                 # Styles
│   └── index.tsx            # Entry point
└── public/                  # Static assets
```

### Mobile App
```
DriverPortal/
├── package.json
├── src/
│   ├── App.tsx              # Navigation setup
│   ├── screens/             # Navigation screens
│   ├── components/          # Reusable components
│   ├── store/
│   │   ├── slices/         # Redux slices
│   │   └── hooks.ts
│   └── img/                # Images
├── android/                # Android native config
├── assets/                 # App assets (icons, splash)
└── index.ts                # Expo entry point
```

---

## 🔌 API Contracts

### WebSocket Server
- **URL**: `ws://host:8090?token={auth_token}` (configurable via WS_PORT env)
- **Protocol**: Ratchet WebSocket + Redis list polling (every 250ms)
- **Events** (pushed as JSON via Redis `portus:events` list):
  - `entity_changed` — broadcast on any POST/PUT/DELETE to users/fahrer/chassi/lkw/tasks/etc.
  - Target types: `all` (broadcast), `user` (by user_id), `room` (by chat room)

### REST Endpoints (примеры)
```
GET    /fahrer              # Список водителей
GET    /fahrer/me           # Текущий водитель (по токену)
POST   /fahrer/login        # Вход водителя
POST   /fahrer              # Создание водителя
PUT    /fahrer/{id}         # Обновление водителя

GET    /chassi              # Список прицепов
GET    /chassi/{id}         # Детали прицепа
POST   /chassi              # Создание прицепа
PUT    /chassi/{id}         # Обновление прицепа

GET    /lkw                 # Список грузовиков
PUT    /lkw/{id}            # Обновление грузовика

GET    /tasks               # Список задач
POST   /tasks               # Создание задачи
PUT    /tasks/{id}          # Обновление задачи

GET    /users               # Список пользователей
POST   /users/login         # Вход пользователя

GET    /notifications       # Список уведомлений
PUT    /notifications/{id}/read  # Отметить прочитанным

GET    /vacations           # Список отпусков
POST   /vacations           # Создать отпуск
```

---

## 🛠️ Development Setup

### Prerequisites
- PHP 8.1+
- Node.js 18+
- Composer
- npm/yarn
- Redis (для production)

### Backend Setup
```bash
cd C:\xampp\htdocs\portusApp1
composer install
php -S localhost:8000
# WebSocket server: runs on configured port
```

### Web Dashboard Setup
```bash
cd C:\Users\info\Desktop\New-Portus-Dasboard
npm install
npm start
# Runs on http://localhost:3000
```

### Mobile App Setup
```bash
cd "C:\Users\info\Desktop\Chassi App\DriverPortal"
npm install
npm start              # Expo development server
# или
npm run android        # Android build
npm run ios           # iOS build
npm run web           # Web version
```

---

## 📦 Dependencies & Versions

### Backend
| Package | Version | Purpose |
|---------|---------|---------|
| cboden/ratchet | ^0.4.4 | WebSocket server |
| predis/predis | ^2.2 | Redis client |

*(No framework — flat PHP with PDO for database)*

### Web Dashboard
| Package | Version | Purpose |
|---------|---------|---------|
| react | ^19.2.0 | UI library |
| react-redux | ^9.2.0 | State management |
| @reduxjs/toolkit | ^2.10.1 | Redux utilities |
| leaflet | ^1.9.4 | Maps |
| react-router-dom | ^7.9.5 | Routing |
| react-icons | ^5.5.0 | Icons |
| typescript | ^4.9.5 | Type checking |

### Mobile App
| Package | Version | Purpose |
|---------|---------|---------|
| react-native | 0.81.5 | Mobile framework |
| expo | ~54.0.35 | Managed framework |
| react-navigation | ^7.x | Navigation |
| redux | + redux-persist | State management |
| expo-notifications | ~0.32.17 | Push notifications |
| typescript | ~5.9.2 | Type checking |

---

## 🔐 Authentication & Authorization

### Authentication Flow
1. User logs in (credentials → Backend via `/users/login` or `/fahrer/login`)
2. Backend verifies credentials, generates 64-char hex token (`bin2hex(random_bytes(32))`)
3. Token stored in `auth_tokens` table with `subject_type` ENUM('user','fahrer')
4. Client stores token (localStorage for web, AsyncStorage for mobile)
5. Token sent in `Authorization: Bearer {token}` header for API requests
6. WebSocket connection uses `?token=` query parameter for authentication

### Token-based Access
- **users** (`subject_type = 'user'`): Administrative employees — full dashboard access
- **fahrer** (`subject_type = 'fahrer'`): Drivers — mobile app access, own data only

*(No JWT library used — custom token generation and validation)*

---

## 🚀 Deployment Strategy

### Production Checklist
- [ ] Environment variables configured (.env files)
- [ ] Database migrations applied
- [ ] Redis server running
- [ ] SSL/TLS certificates installed
- [ ] WebSocket server configured with proper ports
- [ ] CORS policies configured
- [ ] Log rotation setup

### Deployment Pipeline
1. Backend: PHP deployment (via XAMPP or production server)
2. Web Dashboard: npm build → static hosting
3. Mobile App: Build APK/IPA → app stores or internal distribution

---

## 📊 State Management

### Redux Structure (Shared across Web & Mobile)

```typescript
// Typical State Tree (approximate)
{
  fahrer: { list: Driver[], current: Driver | null, loading: boolean, error: string | null },
  chassi: { list: Vehicle[], current: Vehicle | null, loading: boolean, error: string | null },
  lkw: { list: Lkw[], ... },
  tasks: { list: Task[], ... },
  realtime: { connected: boolean, lastUpdate: timestamp }
}
```

### WebSocket Event Subscriptions
- Client connects to `ws://host:8090?token=...`
- Receives `entity_changed` events from Redis-polled queue
- Events dispatched to Redux actions to update state

---

## 🐛 Debugging & Monitoring

### Backend Logging
```php
// Log WebSocket events
Log::channel('websocket')->info('Client connected', ['connectionId' => $id]);

// Log API requests
Log::channel('api')->info('Request', ['method' => $request->method(), 'path' => $request->path()]);
```

### Client Logging
- Browser DevTools Console (Web)
- Expo DevTools (Mobile)
- Redux DevTools for state inspection

### Monitoring Points
1. WebSocket connection status
2. API response times
3. Redux state changes
4. Error boundaries (React)
5. Network requests (browser DevTools)

---

## 🔄 Common Development Workflows

### Adding a New Feature
1. **Backend**: Implement endpoint + WebSocket event
2. **Web Dashboard**: Create component + Redux slice + connect to API
3. **Mobile App**: Create screen + Redux slice + handle notifications

### Fixing a Bug
1. Identify affected component (backend/web/mobile)
2. Reproduce locally
3. Debug using appropriate tools
4. Test in all affected clients
5. Commit with descriptive message

### Updating Dependencies
1. Update package.json
2. Run npm install
3. Test functionality
4. Commit lock files

---

## 📝 Commit Convention

Use clear, descriptive commit messages:

```
[scope]: description

scope: backend | dashboard | mobile | all
example:
  backend: add websocket event for task assignment
  dashboard: update vehicle status UI
  mobile: implement push notification handler
  all: update shared types/interfaces
```

---

## 🤝 Communication & Shared Resources

### Shared Redux Slices
- `fahrerSlice.ts`: Used by both web and mobile
- `chassiSlice.ts`: Used by both web and mobile

### Shared API Endpoints
- All clients communicate with same backend
- Same authentication/authorization rules apply

### WebSocket Event Targets
```
target "all"    — Broadcast to all connected clients (data changes)
target "user"   — Send to specific user_id (notifications)
target "room"   — Send to room subscribers (chat messages)
```

### Entity Change Events Broadcast
After any POST/PUT/DELETE, the API pushes via `Realtime::entityChanged()`:
```
{ entity: "fahrer", action: "created|updated|deleted", data: {...} }
{ entity: "chassi", action: "created|updated|deleted", data: {...} }
{ entity: "lkw",    action: "created|updated|deleted", data: {...} }
{ entity: "tasks",  action: "created|updated|deleted", data: {...} }
... and all other entities
```

---

## 📚 Resources & References

- **Backend Documentation**: See `realtime/` directory
- **Frontend Docs**: React & React Native official docs
- **Redux**: https://redux.js.org/
- **Ratchet WebSockets**: http://socketo.me/
- **Expo Documentation**: https://docs.expo.dev/

---

## ✅ Maintenance Checklist

- [ ] Dependencies up to date
- [ ] Type safety maintained (TypeScript)
- [ ] Tests pass
- [ ] No console errors/warnings
- [ ] Performance acceptable
- [ ] Security best practices followed
- [ ] Documentation updated

---

**Last Updated**: 2026-07-15
**Maintained By**: Development Team
**Status**: Updated (corrected to reflect actual architecture)
