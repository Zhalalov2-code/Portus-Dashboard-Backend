# Portus Code Reference Guide

Quick lookup guide for finding specific functionality across all three projects.

---

## 🔍 How to Find Things

### By Feature Name

#### Authentication & Login
- **Backend**: `api/classes/classUsers.php` (user login), `api/classes/classFahrer.php` (fahrer login), `api/classes/Auth.php` (token resolution)
- **Dashboard**: `src/store/slices/authSlice.ts`, login page component
- **Mobile**: `src/store/slices/authSlice.ts`, `src/screens/LoginScreen.tsx`

**What to search for:**
- Backend: `userLogin()`, `fahrerLogin()`, `Auth::resolve()`, `Auth::currentUser()`
- Dashboard: `useAppDispatch`, `authSlice.actions.setUser`
- Mobile: Same as dashboard

---

#### Driver (Fahrer) Management
- **Backend**: 
  - Class: `api/classes/classFahrer.php`
  - Auth: `api/classes/Auth.php`

- **Dashboard**:
  - Redux: `src/store/slices/fahrerSlice.ts`
  - Component/Page: Fahrer-related components in `src/pages/` and `src/components/`

- **Mobile**:
  - Redux: `src/store/slices/fahrerSlice.ts`
  - Screen: `src/screens/TasksScreen.tsx`
  - Component: `src/components/TaskCard.tsx`

**Key search terms:**
- `class Fahrer` (backend class)
- `Fahrer::verifyMethod()` (route handler)
- `fahrerSlice.fetchFahrer` (async thunk)
- `useAppSelector(state => state.fahrer)` (access state)

---

#### LKW (Truck) Management
- **Backend**:
  - Class: `api/classes/classLkw.php`

- **Dashboard**:
  - Redux: `src/store/slices/lkwSlice.ts` (or in chassi/lkw combined)
  - Page: `src/pages/lkwList.tsx` (LKW table view)

---

#### Chassi (Trailer) Management
- **Backend**:
  - Class: `api/classes/classChassi.php`

- **Dashboard**:
  - Redux: `src/store/slices/chassiSlice.ts`
  - Page: `src/pages/chassiListe.tsx`
  - Map: `src/components/VehicleMap.tsx` (Leaflet)

- **Mobile**:
  - Redux: `src/store/slices/chassiSlice.ts`
  - Screen: `src/screens/VehicleScreen.tsx`

---

#### Task/Assignment Management
- **Backend**:
  - Class: `api/classes/classTasks.php`

- **Dashboard**:
  - Redux: `src/store/slices/taskSlice.ts`
  - Page/Component: Task-related components

- **Mobile**:
  - Redux: `src/store/slices/taskSlice.ts`
  - Screen: `src/screens/TasksScreen.tsx`
  - Component: `src/components/TaskCard.tsx`

---

#### Real-time Location Tracking
- **Backend**:
  - (No dedicated location tracking service — location is managed within classFahrer.php)
  - Realtime: `api/classes/Realtime.php` (Redis event publisher)
  - WebSocket: `realtime/src/Hub.php`

- **Dashboard**:
  - Component: `src/components/VehicleMap.tsx`
  - Redux listener: `src/store/slices/chassiSlice.ts`

- **Mobile**:
  - Service: `src/services/location.ts`
  - Redux: `src/store/slices/locationSlice.ts`

---

#### Push Notifications
- **Backend**:
  - Class: `api/classes/classNotifications.php`
  - Push sender: `api/classes/ExpoPush.php`
  - Realtime: `api/classes/Realtime.php`

- **Dashboard**:
  - Component: Notification-related components
  - Redux: notification slice

- **Mobile**:
  - Service: `src/services/notifications.ts` (Expo Notifications)
  - Redux: notification slice
  - Screen: `src/screens/NotificationsScreen.tsx`

---

#### WebSocket Real-time Updates
- **Backend**:
  - Main server: `realtime/ws-server.php`
  - Hub handler: `realtime/src/Hub.php`
  - Event publisher: `api/classes/Realtime.php`
  - Redis event queue: `portus:events` list (polled every 250ms)

- **Dashboard**:
  - Service: `src/services/websocket.ts`
  - Redux listener: Connect slice to WebSocket events

- **Mobile**:
  - Service: `src/services/websocket.ts`
  - Redux listener: Similar to dashboard

---

#### Fault Reports (Driver-reported)
- **Backend**:
  - Class: `api/classes/classFaultReports.php`

- **Dashboard**:
  - Related to vehicle detail views

- **Mobile**:
  - Screen: VehicleScreen — report fault modal

---

#### Inspections (Pre-trip)
- **Backend**:
  - Class: `api/classes/classInspections.php`

- **Dashboard**:
  - Related to vehicle detail views

- **Mobile**:
  - Screen: VehicleScreen — inspection form

---

#### Inventory / Warehouse
- **Backend**:
  - Class: `api/classes/classInventory.php` (942 lines)

- **Dashboard**:
  - Pages: Inventory-related pages

---

### By Technology/Layer

#### Redux State Management

**File locations:**
- Dashboard: `src/store/slices/`
- Mobile: `src/store/slices/`

**Common slices:**
- `authSlice.ts` — User authentication
- `fahrerSlice.ts` — Driver data
- `chassiSlice.ts` — Vehicle data
- `taskSlice.ts` — Tasks
- `uiSlice.ts` — UI state (modals, filters)
- `notificationSlice.ts` — Notifications

**Pattern to follow:**
```typescript
// Create slice
const slice = createSlice({
  name: 'feature',
  initialState: { /* ... */ },
  reducers: { /* ... */ },
  extraReducers: builder => {
    builder.addCase(asyncThunk.fulfilled, (state, action) => {
      // Update state
    })
  }
});

// Async thunk for API calls
const fetchData = createAsyncThunk(
  'feature/fetchData',
  async (params, { rejectWithValue }) => {
    try {
      const response = await api.get('/endpoint');
      return response.data;
    } catch (error) {
      return rejectWithValue(error.response.data);
    }
  }
);
```

---

#### REST API Endpoints

**Router file:** `api/index.php` (single PHP file, no route config — route matching via switch/case on URI segments)

**URL pattern:** `GET /{resource}/{id?}/{action?}` (no `/api/` prefix)

**Common endpoints:**
```
Auth (Users):
  POST   /users/login             # Login (returns token)
  POST   /users/logout            # Logout (invalidates token)
  GET    /users                   # List users
  POST   /users                   # Create user
  PUT    /users/{id}              # Update user

Auth (Fahrer):
  POST   /fahrer/login            # Driver login
  POST   /fahrer/logout           # Driver logout
  GET    /fahrer/me               # Current driver info (by token)
  POST   /fahrer/push_token       # Save Expo push token

Fahrer (Drivers):
  GET    /fahrer                  # List drivers
  POST   /fahrer                  # Create driver
  PUT    /fahrer/{id}             # Update driver
  DELETE /fahrer/{id}             # Delete driver

LKW (Trucks):
  GET    /lkw[?search=&tuf_status=&sp_status=&notizen=]  # List trucks with filters
  POST   /lkw                     # Create truck
  PUT    /lkw/{id}                # Update truck
  DELETE /lkw/{id}                # Delete truck

Chassi (Trailers):
  GET    /chassi[?search=&tuf_status=&sp_status=&notizen=]  # List trailers with filters
  POST   /chassi                  # Create trailer
  PUT    /chassi/{id}             # Update trailer
  DELETE /chassi/{id}             # Delete trailer

Tasks:
  GET    /tasks                   # List tasks
  POST   /tasks                   # Create task
  GET    /tasks/{id}              # Get task details
  PUT    /tasks/{id}              # Update task
  DELETE /tasks/{id}              # Delete task
  GET    /tasks/{id}/comments     # Get task comments
  POST   /tasks/{id}/comments     # Add comment

Notifications:
  GET    /notifications           # List notifications
  PUT    /notifications/{id}/read # Mark as read
  POST   /notifications/read-all  # Mark all as read

Vacations:
  GET    /vacations               # List vacations
  POST   /vacations               # Create vacation
  PUT    /vacations/{id}          # Update vacation
  GET    /vacations/summary       # Vacation summary
  GET    /vacations/summary-all   # All vacation summaries

Departments:
  GET    /departments             # List departments

Inspections:
  GET    /inspections             # List pre-trip inspections
  POST   /inspections             # Create inspection

Fault Reports:
  GET    /fault_reports           # List fault reports
  POST   /fault_reports           # Report fault
  PUT    /fault_reports/{id}      # Update fault report

Inventory (Warehouse):
  GET    /inventory/items              # List items
  POST   /inventory/items              # Create item
  PUT    /inventory/items/{id}         # Update item
  DELETE /inventory/items/{id}         # Delete item (only if no transactions)
  POST   /inventory/consume            # Consume stock
  POST   /inventory/receive            # Receive stock
  POST   /inventory/adjust             # Adjust stock
  GET    /inventory/transactions       # Stock movements
  DELETE /inventory/transactions/{id}  # Delete + reverse stock (not INITIAL_BALANCE)
  GET    /inventory/categories         # Item categories
  GET    /inventory/units              # Measurement units

Chat:
  GET    /message_lkw             # LKW chat messages
  POST   /message_lkw             # Send LKW message
  GET    /message_chassi          # Chassi chat messages
  POST   /message_chassi          # Send chassi message
```

---

#### WebSocket Events

**Backend implementation:** 
- Event publisher: `api/classes/Realtime.php` → `Realtime::entityChanged($entity, $action, $data, $target)`
- WS server: `realtime/ws-server.php`
- Hub: `realtime/src/Hub.php`

**How events flow:**
```
1. API class (e.g. classFahrer.php) calls Realtime::entityChanged() after POST/PUT/DELETE
2. Realtime.php pushes JSON to Redis list "portus:events"
3. Portus\Realtime\Hub polls Redis list every 250ms (up to 100 events per tick)
4. Hub dispatches to matched connections based on target:
   - "all" → broadcast to all connected clients
   - "user" → send to specific user_id connections
   - "room" → send to room subscribers
```

**Events pushed to Redis:**
```
// Entity changed (on every create/update/delete)
{ "type": "entity_changed", "target": "all", "entity": "fahrer", "action": "created|updated|deleted", "data": {...} }
{ "type": "entity_changed", "target": "all", "entity": "chassi", "action": "...", "data": {...} }
{ "type": "entity_changed", "target": "all", "entity": "lkw",    "action": "...", "data": {...} }
{ "type": "entity_changed", "target": "all", "entity": "tasks",  "action": "...", "data": {...} }
// ... all entities: notifications, vacations, departments, inspections, fault_reports, etc.
```

---

### By File Type

#### TypeScript/React Components
- Located in: `src/components/` (Dashboard & Mobile)
- Naming: `FeatureName.tsx`
- Pattern: Functional component with hooks

**Common hooks:**
```typescript
import { useAppDispatch, useAppSelector } from '../store/hooks';
import { useNavigate } from 'react-router-dom';     // Dashboard only
import { useNavigation } from '@react-navigation/native';  // Mobile only

const dispatch = useAppDispatch();
const data = useAppSelector(state => state.feature.data);
```

---

#### Redux Slices
- Located in: `src/store/slices/`
- Files: `{featureName}Slice.ts`
- Pattern: createSlice + createAsyncThunk

---

#### Services/API Layer
- Located in: `src/services/`
- Key files:
  - `api.ts` — Axios instance with interceptors
  - `websocket.ts` — WebSocket client
  - `storage.ts` — LocalStorage/AsyncStorage helpers
  - `location.ts` — Geolocation service (Mobile)
  - `notifications.ts` — Push notification service (Mobile)

---

#### PHP Classes (Combined Controller + Service + Repository)
- Located in: `api/classes/`
- Files: `class{Feature}.php`
- Pattern: Each class handles its own routing (`verifyMethod()`), CRUD logic, DB queries, and auth checks

```php
class Fahrer
{
    public static function verifyMethod($method, $route)
    {
        switch ($route[1] ?? '') {
            case 'login':      return self::fahrerLogin();
            case 'me':         return self::fahrerMe();
            case 'push_token': return self::savePushToken();
            default:
                if (!isset($route[1])) {
                    if ($method === 'GET')  return self::getAll();
                    if ($method === 'POST') return self::create();
                } else {
                    if ($method === 'GET')    return self::getById((int)$route[1]);
                    if ($method === 'PUT')    return self::update((int)$route[1]);
                    if ($method === 'DELETE') return self::delete((int)$route[1]);
                }
        }
    }
}
```

---

#### Router (Single Entry Point)
- Located in: `api/index.php`
- Pattern: Switch on URI segment → `Class::verifyMethod($method, $route)`
```php
$route = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
$method = $_SERVER['REQUEST_METHOD'];

switch ($route[0]) {
    case 'fahrer':      require 'classes/classFahrer.php'; return Fahrer::verifyMethod($method, $route);
    case 'chassi':      require 'classes/classChassi.php'; return Chassi::verifyMethod($method, $route);
    case 'lkw':         require 'classes/classLkw.php';    return Lkw::verifyMethod($method, $route);
    case 'tasks':       require 'classes/classTasks.php';  return Tasks::verifyMethod($method, $route);
    // ... etc
}
```

---

#### Database Migrations
- Located in: `database/`
- Files: `{feature_name}.sql`
- Pattern: Plain SQL files (no migration framework)

---

## 🎯 Common Tasks & Where to Make Changes

### Adding a New API Endpoint

1. **Backend**
   ```
   1. Create new class in api/classes/class{Feature}.php
      Or add method to existing class
   
   2. Add route case in api/index.php:
      case 'feature': require 'classes/classFeature.php'; return Feature::verifyMethod($method, $route);
   
   3. Implement verifyMethod() to route HTTP methods to handlers
   
   4. (Optional) Add Realtime::entityChanged() call to broadcast changes
   ```

2. **Dashboard**
   ```
   1. Add async thunk in Redux slice:
      src/store/slices/{feature}Slice.ts
   
   2. Create component or use existing:
      src/components/{Feature}...tsx
   
   3. Dispatch action from component:
      dispatch(fetchData())
   ```

3. **Mobile**
   ```
   Same as dashboard
   ```

---

### Adding a WebSocket Event

1. **Backend**
   ```
   1. After POST/PUT/DELETE in any class, call:
      Realtime::entityChanged('feature_name', 'created|updated|deleted', $data, 'all')
   
   2. Example (in classFahrer.php after update):
      Realtime::entityChanged('fahrer', 'updated', ['id' => $id, 'name' => $name], 'all');
   
   3. The event is automatically pushed to Redis list "portus:events"
      and broadcast by the WebSocket Hub to all connected clients
   ```

2. **Dashboard**
   ```
   1. Listen for entity_changed events in WebSocket service
   2. Dispatch Redux action to update store
   ```

3. **Mobile**
   ```
   Same as dashboard
   ```

---

### Adding a New Page/Screen

1. **Dashboard**
   ```
   1. Create page component:
      src/pages/{FeatureName}.tsx
   
   2. Add route in App.tsx:
      <Route path="/feature" element={<FeatureName />} />
   
   3. Create Redux slice if needed:
      src/store/slices/{feature}Slice.ts
   
   4. Add navigation link:
      src/components/Navigation.tsx
   ```

2. **Mobile**
   ```
   1. Create screen component:
      src/screens/{FeatureName}Screen.tsx
   
   2. Add route in navigation setup:
      src/App.tsx → <Stack.Screen name="Feature" component={...} />
   
   3. Create Redux slice if needed:
      src/store/slices/{feature}Slice.ts
   ```

---

### Fixing a Bug

1. **Identify the layer:**
   - Backend: API endpoint, service, model
   - Frontend: Component, Redux, API call

2. **Find the code:**
   - Search in appropriate file
   - Check recent changes (git log)
   - Check Redux DevTools (state)
   - Check Network tab (API)

3. **Test the fix:**
   - Backend: Test API with cURL
   - Frontend: Manual testing + Redux inspection
   - Cross-client testing (web + mobile)

---

## 📂 File Search Patterns

### By Feature
```bash
# Find all driver-related files
grep -r "fahrer" api/classes/
grep -r "classFahrer" api/classes/

# Find all vehicle-related files
grep -r "chassi" api/classes/
grep -r "classChassi" api/classes/
```

### By Redux Slice
```bash
# Find all uses of fahrerSlice
grep -r "fahrerSlice" src/

# Find all async thunks
grep -r "createAsyncThunk" src/
```

### By Router Case
```bash
# Find all route entries
grep -r "case '" api/index.php

# Find all verifyMethod definitions
grep -r "verifyMethod" api/classes/
```

---

## 🛠️ Development Shortcuts

### Quick Navigation Setup (VS Code)
```json
// .vscode/settings.json
{
  "workbench.colorTheme": "One Dark Pro",
  "editor.formatOnSave": true,
  "editor.defaultFormatter": "esbenp.prettier-vscode",
  "[php]": {
    "editor.defaultFormatter": "bmewburn.vscode-intelephp-pack"
  }
}
```

### Command Cheat Sheet
```bash
# Backend development (Apache/XAMPP)
# API runs at http://localhost/ (via .htaccess rewrite)

# Docker development
docker-compose up -d               # Start all services
docker-compose logs -f ws          # Watch WebSocket logs
docker-compose logs -f app         # Watch PHP API logs

# Dashboard development
cd C:\Users\info\Desktop\New-Portus-Dasboard
npm start                          # Start dev server
npm run build                      # Production build

# Mobile development
cd "C:\Users\info\Desktop\Chassi App\DriverPortal"
npm start                          # Start Expo
npm run android                    # Android build
npm run web                        # Web version
```

### Redux DevTools Inspection
```javascript
// Browser console (Dashboard)
// View current state
store.getState()

// Dispatch action manually
store.dispatch(fahrerSlice.actions.setCurrentDriver(driver))

// Watch state changes
store.subscribe(() => console.log(store.getState()))
```

---

## 📊 Code Statistics

### Files Overview
```
Backend (portusApp1):
├── api/classes/                   22 business logic files
├── api/config/                    3 config files
├── api/index.php                  1 router
├── realtime/                      2 files (ws-server.php + Hub.php)
├── database/                      13 SQL migration scripts
├── cron/                          4 scheduled scripts
├── docker/                        2 config files
Total: ~50+ PHP files

Dashboard (New-Portus-Dashboard):
├── Components: ~15 files
├── Pages: ~8 files
├── Store/Slices: ~6 files
├── Services: ~3 files
└── Types: 1 file
Total: ~50+ TypeScript/React files

Mobile (DriverPortal):
├── Screens: ~8 files
├── Components: ~10 files
├── Store/Slices: ~6 files
├── Services: ~4 files
└── Types: 1 file
Total: ~40+ TypeScript/React Native files
```

---

## ✅ Code Quality Checklist

When adding new code, ensure:
- [ ] Route added to api/index.php switch statement
- [ ] Auth check implemented (Auth::resolve() for protected routes)
- [ ] Input validation on POST/PUT data
- [ ] PDO prepared statements (no raw SQL concatenation)
- [ ] Realtime::entityChanged() called after mutations
- [ ] TypeScript types (frontend) are complete
- [ ] Error handling is implemented
- [ ] No debug output left in production code
- [ ] Code follows project conventions
- [ ] Similar functionality not duplicated elsewhere

---

**Last Updated**: 2026-07-15  
**Version**: 2.0 (paths corrected to match actual structure)  
**Status**: Updated
