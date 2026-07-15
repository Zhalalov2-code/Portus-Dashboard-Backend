# Portus Multi-Project Navigator

Quick reference guide для переключения между тремя проектами и поиска ключевых файлов.

## 🗺️ Project Locations

| Project | Path | Type | Status |
|---------|------|------|--------|
| **Backend** | `C:\xampp\htdocs\portusApp1` | PHP 8.1 | Active |
| **Dashboard** | `C:\Users\info\Desktop\New-Portus-Dasboard` | React 19 | Active |
| **DriverPortal** | `C:\Users\info\Desktop\Chassi App\DriverPortal` | React Native | Active |

---

## 🎯 Quick Start Commands

### Backend
```bash
# Terminal 1: Apache (via XAMPP or Docker)
# XAMPP: Apache runs on port 80 — API accessible at http://localhost/
# Docker: docker-compose up -d app

# Terminal 2: Start XAMPP MySQL
# XAMPP Control Panel → Start MySQL

# Terminal 3: WebSocket Server (manual, if not using Docker)
php realtime/ws-server.php
# Or via Docker: docker-compose up -d ws
```

### Web Dashboard
```bash
# Terminal 3: React Dev Server
cd "C:\Users\info\Desktop\New-Portus-Dasboard"
npm install  # if needed
npm start    # Runs on http://localhost:3000
```

### Mobile App
```bash
# Terminal 4: Expo Dev Server
cd "C:\Users\info\Desktop\Chassi App\DriverPortal"
npm install  # if needed
npm start    # Expo CLI

# For Android emulator
npm run android

# For Web version
npm run web
```

---

## 📂 Key Files by Project

### Backend (portusApp1)
```
composer.json                  # Dependencies (Ratchet, Predis)
├── api/
│   ├── index.php             # Single-entry router (all routes)
│   ├── classes/              # Business logic (22 classes)
│   └── config/               # DB, env, mail config
├── realtime/
│   ├── ws-server.php         # WebSocket entry point
│   └── src/Hub.php           # Portus\Realtime\Hub
├── database/                 # SQL migration scripts
├── cron/                     # Scheduled scripts
├── docker/                   # Apache config, crontab
└── vendor/                   # Composer dependencies
```

**Important Files to Check First:**
- `composer.json` — Dependencies & versions
- `api/index.php` — Route table (switch statement)
- `api/classes/` — All business logic
- `api/config/db.php` — Database configuration
- `realtime/ws-server.php` — WebSocket server
- `database/` — SQL schema files

---

### Web Dashboard (New-Portus-Dasboard)
*Located on developer desktop — not in this repository.*
```
package.json                 # Dependencies
├── src/
│   ├── App.tsx             # Main component + routing
│   ├── index.tsx           # Entry point
│   ├── components/         # Reusable UI
│   ├── pages/              # Page routes
│   ├── store/
│   │   ├── slices/         # Redux slices
│   │   └── hooks.ts
│   └── css/                # Styles
├── public/                 # Static files
└── tsconfig.json          # TypeScript config
```

**Important Files to Check First:**
- `package.json` — Dependencies
- `src/App.tsx` — Main routing
- `src/store/slices/` — Redux state
- `src/pages/` — Page components (lkwList.tsx, chassiListe.tsx, etc.)

---

### Mobile App (DriverPortal)
*Located on developer desktop — not in this repository.*
```
package.json               # Dependencies
├── src/
│   ├── App.tsx           # Main component + navigation
│   ├── screens/          # Navigation screens
│   ├── components/       # Reusable UI
│   ├── store/
│   │   ├── slices/       # Redux slices
│   │   └── hooks.ts
│   └── img/              # Assets
├── android/              # Android native config
├── assets/               # Icons, splash
├── app.json             # Expo configuration
└── index.ts             # Expo entry point
```

**Important Files to Check First:**
- `package.json` — Dependencies
- `app.json` — Expo app config
- `src/App.tsx` — Navigation setup
- `src/screens/` — Screen components
- `src/store/slices/` — Redux state

---

## 🔄 Data Flow & Integration Points

### API Communication
```
Web Dashboard / Mobile App
        ↓
    HTTP Request
        ↓
    Backend API
        ↓
    Database / Redis
```

### Shared Redux Slices
Both web and mobile use:
- **fahrerSlice.ts** — Driver data & status
- **chassiSlice.ts** — Vehicle data & status

**Location**: 
- Dashboard: `src/store/slices/`
- Mobile: `src/store/slices/`

### WebSocket Events
Backend pushes events to Redis list `portus:events`:
- `Realtime::entityChanged()` called after every POST/PUT/DELETE
- Ratchet WebSocket Hub polls Redis list every 250ms
- Hub dispatches to connected clients based on target type

**Common Events:**
```
entity_changed → all      — Entity data changes (broadcast to all)
entity_changed → user     — User-specific events (notifications)
entity_changed → room     — Chat room messages
```

---

## 🛠️ Development Workflow

### Scenario 1: Adding New API Endpoint

1. **Backend** (`portusApp1`)
   ```
   api/classes/class{Feature}.php
   → Add verifyMethod() + CRUD methods
   → api/index.php → Add case 'feature' to switch
   ```

2. **Dashboard** (Frontend)
   ```
   src/store/slices/{feature}Slice.ts
   → Add async thunk for API call
   → src/components/ → Consume in component
   ```

3. **Mobile App**
   ```
   src/store/slices/{feature}Slice.ts
   → Add same async thunk
   → src/screens/ → Consume in screen
   ```

---

### Scenario 2: Adding WebSocket Event

1. **Backend**
   ```
   api/classes/class{Feature}.php
   → After POST/PUT/DELETE, call:
   → Realtime::entityChanged('entity', 'action', $data, 'all')
   → Event auto-pushed to Redis list portus:events
   ```

2. **Dashboard**
   ```
   src/store/slices/
   → Listen for entity_changed events from WebSocket
   → Dispatch action to update state
   → Component re-renders automatically
   ```

3. **Mobile App**
   ```
   Same as dashboard
   ```

---

### Scenario 3: Debugging Connection Issues

**Check in order:**

1. **Backend running?**
   ```bash
   curl http://localhost/ -s -o /dev/null -w "%{http_code}"
   # Should return 200 or 404 (API routes return JSON)
   ```

2. **WebSocket connected?**
   ```javascript
   // Browser console (Dashboard)
   new WebSocket('ws://localhost:8090?token=YOUR_TOKEN')
   
   // Mobile Expo console (DriverPortal)
   Check Redux state: realtime.connected
   ```

3. **API responding?**
   ```bash
   curl -H "Authorization: Bearer {token}" http://localhost/fahrer
   ```

4. **Redux state updated?**
   ```javascript
   // Browser: Redux DevTools
   // Mobile: React Native Debugger
   ```

5. **Redis working?**
   ```bash
   docker-compose exec redis redis-cli LLEN portus:events
   # Should show event count (or 0)
   ```

---

## 🔍 Finding Specific Functionality

### Feature: Driver Management
- **Backend**: `api/classes/classFahrer.php`, `api/classes/Auth.php`
- **Dashboard**: `src/store/slices/fahrerSlice.ts`
- **Mobile**: `src/store/slices/fahrerSlice.ts`, `src/screens/TasksScreen.tsx`

### Feature: LKW / Chassi Management
- **Backend**: `api/classes/classLkw.php`, `api/classes/classChassi.php`
- **Dashboard**: `src/pages/lkwList.tsx`, `src/pages/chassiListe.tsx`, `src/store/slices/chassiSlice.ts`
- **Mobile**: `src/screens/VehicleScreen.tsx`

### Feature: Push Notifications
- **Backend**: `api/classes/classNotifications.php`, `api/classes/ExpoPush.php`, `api/classes/Realtime.php`
- **Dashboard**: Notification center UI
- **Mobile**: `expo-notifications`, ExpoPushToken → saved via `POST /fahrer/push_token`

### Feature: Authentication
- **Backend**: `api/classes/classUsers.php`, `api/classes/classFahrer.php`, `api/classes/Auth.php`
- **Token**: Custom 64-char hex token (no JWT), stored in `auth_tokens` table
- **Dashboard**: Login form, token stored in localStorage
- **Mobile**: Login form, token stored in AsyncStorage

---

## 📋 Before You Start Working

### Pre-flight Checklist

- [ ] **Backend ready?**
  ```bash
  cd C:\xampp\htdocs\portusApp1
  composer install
  # API runs via Apache/XAMPP (port 80) or Docker (port 8888)
  ```

- [ ] **Database running?**
  - MySQL via XAMPP Control Panel or Docker (`docker-compose up -d db`)

- [ ] **Environment configured?**
  - `.env` file in backend with DB credentials
  - `.env` file in mobile app (if needed)

- [ ] **Dependencies installed?**
  ```bash
  # Dashboard
  cd C:\Users\info\Desktop\New-Portus-Dasboard && npm install
  
  # Mobile
  cd "C:\Users\info\Desktop\Chassi App\DriverPortal" && npm install
  ```

- [ ] **Ports available?**
  - 80: Backend API (XAMPP Apache)
  - 8888: Backend API (Docker)
  - 3000: Dashboard dev server
  - 8090: WebSocket server
  - 3306: Database (MySQL)
  - 6379: Redis
  - 8889: phpMyAdmin (Docker)

---

## 🚀 Deployment Checklist

### Before Pushing to Production

**Backend:**
- [ ] Database migrations applied (run SQL files from `database/`)
- [ ] Environment variables configured (`.env`)
- [ ] Redis running for WebSocket event queue
- [ ] WebSocket server running (port 8090)
- [ ] SSL certificates installed
- [ ] CORS configured (Apache vhost or .htaccess)

**Dashboard:**
- [ ] `npm run build` completes successfully
- [ ] No console errors/warnings
- [ ] API endpoints point to production
- [ ] Tested in production environment

**Mobile:**
- [ ] `npm run android` builds successfully
- [ ] Tested on physical device
- [ ] Expo push notification service configured
- [ ] API endpoints point to production
- [ ] App signing configured (for distribution)

---

## 📞 Troubleshooting Quick Guide

| Issue | Check | Fix |
|-------|-------|-----|
| API 404 errors | Backend routes | Check `api/index.php` switch statement |
| WebSocket won't connect | Firewall, port 8090 | Open port, `docker-compose up -d ws` |
| Redux state not updating | Action dispatch | Check Redux DevTools |
| Notifications not received | Expo push token | Check `POST /fahrer/push_token` |
| Type errors in TS | tsconfig.json | Run `npm run build` |
| CORS errors | Backend config | Check `.htaccess` or Apache vhost |
| Database connection | .env credentials | Verify DB_HOST, DB_NAME, DB_USER, DB_PASS |
| Redis not responding | Redis running | `docker-compose up -d redis` or check `redis-cli ping` |

---

## 🔗 Cross-Project References

### When Modifying Backend API
→ Update type definitions in both web & mobile
→ Test with API client (Postman, cURL)
→ Communicate breaking changes to frontend teams

### When Modifying Redux Slices
→ Coordinate between web & mobile if sharing code
→ Update TypeScript interfaces
→ Test async thunks in isolation

### When Adding WebSocket Events
→ Test in backend first
→ Verify Redux listeners are wired
→ Test with both web and mobile clients

---

## 📚 Documentation Structure

```
C:\xampp\htdocs\portusApp1\
├── readme/
│   ├── CONTEXT.md              # System context (all 3 projects)
│   ├── PROJECT_NAVIGATOR.md    # ← This file (quick navigation)
│   ├── ARCHITECTURE.md         # System architecture
│   ├── CODE_REFERENCE.md       # Code reference guide
│   ├── INTEGRATION_GUIDE.md    # API contracts & data types
│   ├── CHANGES.txt             # Notizen feature changelog
│   ├── DEPLOY_README.md        # Deployment index
│   ├── DEPLOYMENT_SUMMARY.md   # Deployment summary
│   ├── PRODUCTION_CHECKLIST.md # Production checklist
│   └── QUICK_DEPLOY.md         # Quick deploy guide
├── README.md                   # Backend-specific readme
├── .env.example                # Environment template
└── database/                   # SQL migration scripts
```

---

## 💡 Pro Tips

1. **Use VS Code Multi-Workspace**
   ```
   File → Add Folder to Workspace
   Add all 3 project folders
   Save as `Portus.code-workspace`
   ```

2. **Terminal Tabs Setup**
   ```
   Tab 1: Backend (Terminal)
   Tab 2: Dashboard (Terminal)
   Tab 3: Mobile (Terminal)
   Tab 4: Notes/Reference
   ```

3. **Git Strategy**
   - Each project has own `.git` in its own directory
   - Coordinate commits across projects
   - Use consistent commit messages

4. **Testing Order**
   ```
   Backend → Test API with cURL
   Dashboard → Test UI locally
   Mobile → Test on emulator/device
   ```

5. **Debugging WebSocket**
   ```javascript
   // Browser console
   ws = new WebSocket('ws://localhost:8090?token=YOUR_TOKEN');
   ws.onmessage = (e) => console.log(JSON.parse(e.data));
   
   // Check Redis events
   // docker-compose exec redis redis-cli LRANGE portus:events 0 -1
   ```

---

**Last Updated**: 2026-07-15  
**For Questions**: Check CONTEXT.md or individual project README files
