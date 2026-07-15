# Portus Integration Guide — Data Types & Contracts

Complete reference for data structures, API contracts, and WebSocket message formats across all three projects.

---

## 📊 Core Data Types

### Fahrer (Driver)
```typescript
interface Fahrer {
  id: number;
  name: string;                 // First name
  lastname: string;             // Last name
  driver_code: string;          // Unique driver code (replaced email as identifier)
  password: string;             // Hashed password
  lkw: number | null;           // Assigned LKW ID
  chassi: number | null;        // Assigned Chassi ID
  phone: string;
  push_token: string | null;    // Expo push notification token
  terms: number;                // Terms agreement flag
  created_at: string;
  updated_at: string;
}
```

**Usage:**
- Dashboard: Display driver list, assign vehicles
- Mobile: Driver profile, push notification registration

---

### LKW (Truck)
```typescript
interface Lkw {
  id_lkw: number;
  lkw_nummer: string;           // License plate / truck number
  tuf: string;                  // TÜV date
  esp: string;                  // SP date
  adr: string;                  // ADR date
  a_schild: string;             // A-Schild
  achse_1_links: string;        // Axle 1 left status
  achse_1_rechts: string;       // Axle 1 right status
  achse_2_links: string;        // Axle 2 left status
  achse_2_rechts: string;       // Axle 2 right status
  feuerloescher_1: string;      // Fire extinguisher 1
  feuerloescher_2: string;      // Fire extinguisher 2
  warndreieck: string;          // Warning triangle
  verbandkasten: string;        // First aid kit
  kranken_trage: string;        // Stretcher
  notizen: string | null;       // Notes
  status: string;
  created_at: string;
  updated_at: string;
}
```

### Chassi (Trailer)
```typescript
interface Chassi {
  id_chassi: number;
  chassi_nummer: string;        // Trailer number
  tuf: string;                  // TÜV date
  esp: string;                  // SP date
  adr: string;                  // ADR date
  a_schild: string;             // A-Schild
  achse_1_links: string;        // Axle 1 left status
  achse_1_rechts: string;       // Axle 1 right status
  achse_2_links: string;        // Axle 2 left status
  achse_2_rechts: string;       // Axle 2 right status
  feuerloescher_1: string;      // Fire extinguisher 1
  feuerloescher_2: string;      // Fire extinguisher 2
  notizen: string | null;       // Notes
  created_at: string;
  updated_at: string;
}
```

### Vehicle History
```typescript
interface VehicleHistory {
  id: number;
  lkw_id: number | null;        // LKW ID
  chassi_id: number | null;     // Chassi ID
  driver_id: number;            // Fahrer ID
  driver_name: string;
  event: string;                // e.g. 'assigned', 'unassigned'
  notes?: string;
  timestamp: string;
}
```

**Usage:**
- Dashboard: Vehicle management, TÜV/SP tracking, axle status
- Mobile: Driver sees assigned vehicle technical info

---

### Task/Assignment
```typescript
interface Task {
  id: number;
  title: string;
  description: string;
  chauffeur: string;            // Assigned driver name
  lkw: string;                  // Assigned LKW number
  chassi: string;               // Assigned Chassi number
  priority: string;             // Priority level
  deadline: string;             // ISO date
  status: 'new' | 'in_progress' | 'clarification' | 'done';
  file: string | null;          // Attached file
  created_at: string;
  updated_at: string;
}

interface TaskComment {
  id: number;
  task_id: number;
  user_id: number;
  comment: string;
  created_at: string;
}
```

**Usage:**
- Dashboard: Task creation, monitoring, status management
- Mobile: Driver views assigned tasks, updates status

---

### Notification
```typescript
interface Notification {
  id: number;
  user_id: number | null;       // Admin user ID (or null if for fahrer)
  fahrer_id: number | null;     // Driver ID (or null if for user)
  type: string;                 // Notification type
  title: string;
  body: string;
  related_type: string | null;  // Related entity type (task, vacation, etc.)
  related_id: number | null;    // Related entity ID
  read: boolean;                // 0 or 1
  read_at: string | null;
  created_at: string;
  updated_at: string;
}
```

**Usage:**
- Mobile: Push notifications via Expo (ExpoPush.php)
- Dashboard: Notification center display

---

### User (Authentication)
```typescript
interface User {
  id: number;
  username: string;             // Login username (replaced email)
  name: string;                 // Display name
  password: string;             // Hashed password
  role: string;                 // User role
  department: number | null;    // Department ID
  email: string;
  phone: string;
  terms: number;                // Terms agreement flag
  created_at: string;
  updated_at: string;
}
```

**Usage:**
- Dashboard: Admin user management
- Auth: Token-based authentication (no JWT)

---

## 🔌 REST API Contracts

### Authentication Endpoints

**POST `/users/login`** (User/Admin login)
```json
{
  "request": {
    "username": "admin",
    "password": "secure_password"
  },
  "response": {
    "token": "a1b2c3d4e5f6...",   // 64-char hex token (not JWT)
    "user": { /* User object */ }
  },
  "errors": {
    "401": "Invalid credentials"
  }
}
```

**POST `/users/logout`**
```json
{
  "request": { /* empty */ },
  "response": {
    "message": "Logged out successfully"
  },
  "headers": {
    "Authorization": "Bearer {token}"
  }
}
```

**POST `/fahrer/login`** (Driver login)
```json
{
  "request": {
    "driver_code": "DRV001",
    "password": "secure_password"
  },
  "response": {
    "token": "a1b2c3d4e5f6...",   // 64-char hex token
    "fahrer": { /* Fahrer object */ }
  },
  "errors": {
    "401": "Driver not found or wrong credentials"
  }
}
```

**GET `/fahrer/me`**
```json
{
  "response": { /* Current Fahrer object (by token) */ },
  "headers": {
    "Authorization": "Bearer {token}"
  }
}
```

---

### Fahrer (Driver) Endpoints

**GET `/fahrer`**
```json
{
  "response": [ /* Fahrer[] */ ]
}
```

**GET `/fahrer/{id}`**
```json
{
  "response": { /* Fahrer object */ }
}
```

**POST `/fahrer`**
```json
{
  "request": {
    "name": "John",
    "lastname": "Doe",
    "driver_code": "DRV001",
    "phone": "+1234567890"
  },
  "response": { /* Created Fahrer object */ },
  "status": 201
}
```

**PUT `/fahrer/{id}`**
```json
{
  "request": { /* Any Fahrer fields to update */ },
  "response": { /* Updated Fahrer object */ }
}
```

**DELETE `/fahrer/{id}`**
```json
{
  "response": { "message": "Deleted successfully" }
}
```

**POST `/fahrer/push_token`** (Save Expo push token)
```json
{
  "request": {
    "push_token": "ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]"
  },
  "response": {
    "message": "Push token saved"
  }
}
```

---

### LKW (Truck) and Chassi (Trailer) Endpoints

**GET `/lkw`** — List all trucks
```json
{
  "response": [ /* Lkw[] */ ]
}
```

**GET `/chassi`** — List all trailers
```json
{
  "response": [ /* Chassi[] */ ]
}
```

**GET `/lkw/{id}`** — Get truck details
**GET `/chassi/{id}`** — Get trailer details
```json
{
  "response": { /* Lkw or Chassi object */ }
}
```

**POST `/lkw`** — Create truck
**POST `/chassi`** — Create trailer
```json
{
  "request": {
    "lkw_nummer": "ABC-123",
    "chassi_nummer": "TR-001"
  },
  "response": { /* Created object */ },
  "status": 201
}
```

**PUT `/lkw/{id}`** — Update truck
**PUT `/chassi/{id}`** — Update trailer
```json
{
  "request": {
    "tuf": "2026-12-31",
    "esp": "2026-12-31",
    "notizen": "TÜV: 15.03.2024, SP: 20.04.2024"
  },
  "response": { /* Updated object */ }
}
```

### Fault Reports

**GET `/fault_reports`** — List fault reports
**POST `/fault_reports`** — Report a fault
```json
{
  "request": {
    "fahrer_id": 5,
    "lkw_id": 1,
    "chassi_id": null,
    "description": "Engine noise detected",
    "status": "open"
  },
  "response": { /* Created fault report */ },
  "status": 201
}
```

### Inspections (Pre-trip)

**GET `/inspections`** — List inspections
**POST `/inspections`** — Create inspection
```json
{
  "request": {
    "fahrer_id": 5,
    "lkw_id": 1,
    "chassi_id": null,
    "checklist": { /* inspection data */ },
    "status": "completed"
  },
  "response": { /* Created inspection */ },
  "status": 201
}
```

---

### Task Endpoints

**GET `/tasks`**
```json
{
  "response": [ /* Task[] */ ]
}
```

**GET `/tasks/{id}`**
```json
{
  "response": { /* Task object */ }
}
```

**POST `/tasks`** (Create task)
```json
{
  "request": {
    "title": "Transport Aufgabe",
    "description": "Warehouse A to Client B",
    "chauffeur": "John Doe",
    "lkw": "ABC-123",
    "chassi": "TR-001",
    "priority": "high",
    "deadline": "2026-07-20",
    "status": "new"
  },
  "response": { /* Created Task object */ },
  "status": 201
}
```

**PUT `/tasks/{id}`** (Update task)
```json
{
  "request": {
    "status": "in_progress"
  },
  "response": { /* Updated Task object */ }
}
```

**DELETE `/tasks/{id}`**
```json
{
  "response": { "message": "Deleted successfully" }
}
```

**GET `/tasks/{id}/comments`** — Get task comments
**POST `/tasks/{id}/comments`** — Add comment
```json
{
  "request": {
    "comment": "Task is in progress, ETA 2 hours"
  },
  "response": { /* Created comment */ },
  "status": 201
}
```

---

### Notification Endpoints

**GET `/notifications`**
```json
{
  "response": [ /* Notification[] */ ]
}
```

**PUT `/notifications/{id}/read`** — Mark as read
```json
{
  "response": { /* Updated Notification */ }
}
```

**POST `/notifications/read-all`** — Mark all as read
```json
{
  "response": { "message": "All notifications marked as read" }
}
```

---

## 📡 WebSocket Protocol

### Connection
```
URL: ws://host:8090?token={auth_token}
Authentication: Token passed as query parameter (validated against auth_tokens table)
Reconnection: Handled by client implementation
```

### Message Format (Client → Server)
```typescript
interface WSClientMessage {
  type: 'subscribe' | 'unsubscribe';
  room?: string;                // Room name to subscribe/unsubscribe
}
```

### Message Format (Server → Client)
```typescript
interface WSServerMessage {
  type: 'event' | 'error';
  entity?: string;              // Entity type (fahrer, chassi, lkw, tasks, etc.)
  action?: string;              // created, updated, deleted
  data?: any;                   // Event payload
  message?: string;             // Error message
}
```

### Subscribe to Rooms
```json
{
  "type": "subscribe",
  "room": "entity:123"
}
```

### Incoming Events (from Redis list portus:events, polled every 250ms)

**Entity Changed (generic — all POST/PUT/DELETE operations)**
```json
{
  "type": "event",
  "entity": "fahrer",
  "action": "updated",
  "data": { "id": 123, "name": "John", "lastname": "Doe" }
}
```

**Example Flow:**
1. API updates a fahrer record → calls `Realtime::entityChanged('fahrer', 'updated', $data, 'all')`
2. Realtime.php pushes to Redis: `RPUSH portus:events '{"type":"entity_changed","entity":"fahrer","action":"updated","data":{...},"target":"all"}'`
3. WebSocket Hub polls Redis every 250ms: `LPOP portus:events`
4. Hub broadcasts to all connected clients
5. Clients receive event, update Redux store

*(No heartbeat ping/pong — relies on TCP keepalive and Ratchet connection management)*

---

## 🔐 Authentication & Headers

### API Request Headers
```
GET /fahrer HTTP/1.1
Host: example.com
Authorization: Bearer a1b2c3d4e5f6...  (64-char hex token)
Content-Type: application/json
Accept: application/json
```

### Token Format (Custom, no JWT)
Tokens are random 64-character hex strings generated via `bin2hex(random_bytes(32))`.
They are stored in the `auth_tokens` table with:
- `subject_type`: `'user'` or `'fahrer'`
- `subject_id`: the user/fahrer ID
- `expires_at`: token expiration timestamp

### Token Storage
- **Dashboard**: `localStorage.getItem('auth_token')`
- **Mobile**: `AsyncStorage.getItem('auth_token')`

### Auth Resolution
- `Auth::resolve()` — validates token from Authorization header, sets current user/fahrer
- `Auth::currentUser()` — returns current authenticated User (or null)
- `Auth::currentFahrer()` — returns current authenticated Fahrer (or null)

---

## ⚡ Status & Enum Values

### Task Status
- `new` — Task created, not started
- `in_progress` — Task in progress
- `clarification` — Awaiting clarification
- `done` — Task completed

### Vehicle Technical Status (TÜV/SP/Axle fields)
- Free text entries (dates, notes, status descriptions)
- No fixed enum — each field stores user-entered text

### Auth Subject Types
- `user` — Administrative employee (dashboard access)
- `fahrer` — Driver (mobile app access)

### Notification Read Status
- `0` — Unread
- `1` — Read

---

## 🔄 State Synchronization

### On Application Start
1. Client connects to backend
2. Fetches token from local storage
3. Establishes WebSocket connection to `ws://host:8090?token=...`
4. Loads initial data via REST API
5. Receives real-time updates via WebSocket
6. Ready for user interaction

### On Data Update
1. Frontend dispatches Redux action
2. Async thunk calls API endpoint (POST/PUT/DELETE)
3. Backend processes & returns updated data
4. Frontend updates Redux store
5. Backend pushes event to Redis list via `Realtime::entityChanged()`
6. WebSocket Hub polls Redis, broadcasts to connected clients
7. Other clients receive event, update store
8. UIs re-render with new data

### Conflict Resolution
- **Last-write-wins**: Server response is authoritative
- **Optimistic updates**: Frontend can update immediately, rolls back on error

---

## 📋 Data Validation Rules

### Fahrer
- `name`: Required
- `lastname`: Required
- `driver_code`: Required, unique
- `phone`: Optional

### LKW / Chassi
- `lkw_nummer` / `chassi_nummer`: Required, unique
- Technical fields (tuf, esp, adr, etc.): Free text entries
- `notizen`: Optional text field (added 2026-07-13)

### Tasks
- `title`: Required
- `status`: Must be one of `new`, `in_progress`, `clarification`, `done`

---

## 🧪 Testing API Contracts

### Using cURL
```bash
# Login (user)
curl -X POST http://localhost/users/login \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "password"}'

# Login (fahrer)
curl -X POST http://localhost/fahrer/login \
  -H "Content-Type: application/json" \
  -d '{"driver_code": "DRV001", "password": "password"}'

# Get drivers
curl -X GET http://localhost/fahrer \
  -H "Authorization: Bearer {token}"

# Update LKW notizen
curl -X PUT http://localhost/lkw/1 \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"notizen": "TÜV: 15.03.2024"}'
```

### Using Postman
1. Import API collection
2. Set environment variables (base_url, token)
3. Run requests in order
4. Check response status and data format

---

## 🚨 Error Response Format

### Standard Error Response
```json
{
  "error": true,
  "message": "Error description"
}
```

### Common Status Codes
- `200` — Success
- `201` — Created
- `400` — Bad Request (validation error)
- `401` — Unauthorized (invalid/missing token)
- `404` — Not Found
- `500` — Internal Server Error

---

**Last Updated**: 2026-07-15  
**Version**: 2.0 (data types corrected to match actual DB schema)  
**Status**: Updated
