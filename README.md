# 📦 Portus Transportation Management System

Complete documentation and context management for the Portus multi-project ecosystem.

---

## 🎯 Quick Start

### For New Team Members
1. Read: **[CONTEXT.md](CONTEXT.md)** — System overview & architecture
2. Read: **[PROJECT_NAVIGATOR.md](PROJECT_NAVIGATOR.md)** — Quick navigation guide
3. Setup: Follow development setup instructions
4. Reference: Use **[CODE_REFERENCE.md](CODE_REFERENCE.md)** for code locations

### For Developers Working on Features
1. Check: **[ARCHITECTURE.md](ARCHITECTURE.md)** — System design
2. Reference: **[INTEGRATION_GUIDE.md](INTEGRATION_GUIDE.md)** — API contracts & data types
3. Navigate: **[CODE_REFERENCE.md](CODE_REFERENCE.md)** — File locations

### For Quick Lookups
- **"Where is X?"** → [CODE_REFERENCE.md](CODE_REFERENCE.md)
- **"How does X work?"** → [ARCHITECTURE.md](ARCHITECTURE.md)
- **"What's the API for X?"** → [INTEGRATION_GUIDE.md](INTEGRATION_GUIDE.md)
- **"How do I setup X?"** → [PROJECT_NAVIGATOR.md](PROJECT_NAVIGATOR.md)

---

## 📚 Documentation Structure

### 1. **CONTEXT.md** — System Context Management
Complete overview of all three projects as a single integrated system.

**Contains:**
- Project overview & descriptions
- Architecture diagrams
- Component responsibilities
- Technology stack & versions
- Development setup instructions
- API contracts overview
- Deployment strategy
- State management approach

**Read this when:** You need to understand the big picture

---

### 2. **PROJECT_NAVIGATOR.md** — Quick Navigation Guide
Fast reference for switching between projects and finding key files.

**Contains:**
- Project locations & quick start commands
- Key files by project
- Data flow & integration points
- Development workflow scenarios
- Troubleshooting guide
- Deployment checklist

**Read this when:** You need to quickly jump between projects

---

### 3. **INTEGRATION_GUIDE.md** — Data Types & API Contracts
Comprehensive reference for all data structures and API interactions.

**Contains:**
- Core data type definitions (TypeScript interfaces)
- REST API endpoint documentation
- WebSocket protocol & events
- Authentication flow
- Status & enum values
- Data validation rules
- Testing examples with cURL

**Read this when:** You need to understand data formats or API contracts

---

### 4. **ARCHITECTURE.md** — System Architecture
Detailed technical architecture and design decisions.

**Contains:**
- System overview & component diagram
- Component architecture (code structure)
- Data layer & database schema
- Communication patterns
- Security architecture
- Performance considerations
- Deployment architecture
- Monitoring & observability

**Read this when:** You need deep technical understanding

---

### 5. **CODE_REFERENCE.md** — Code Search Guide
Quick lookup for finding specific functionality across all projects.

**Contains:**
- Feature-by-feature code locations
- Technology/layer-specific files
- Common task instructions
- File search patterns
- Development shortcuts
- Code quality checklist

**Read this when:** You need to find where something is implemented

---

## 🏗️ Project Structure at a Glance

```
PORTUS ECOSYSTEM
│
├── 📁 Backend (portusApp1)
│   ├── PHP 8.1 REST API
│   ├── WebSocket server (Ratchet)
│   ├── Redis integration
│   └── Real-time broadcasting
│
├── 📁 Dashboard (New-Portus-Dasboard)
│   ├── React 19 web application
│   ├── Redux state management
│   ├── TypeScript type safety
│   └── Leaflet maps integration
│
└── 📁 Mobile App (DriverPortal)
    ├── React Native (Expo)
    ├── Android, iOS, Web support
    ├── Push notifications (Expo)
    └── Offline-first architecture
```

---

## 🚀 Getting Started

### Prerequisites
- PHP 8.1+
- Node.js 18+
- npm/yarn
- Composer
- XAMPP (for MySQL)
- Git

### Quick Setup (5 minutes)

**Terminal 1 — Backend**
```bash
cd C:\xampp\htdocs\portusApp1
composer install
php -S localhost:8000
```

**Terminal 2 — MySQL**
```
XAMPP Control Panel → Start MySQL
# or
mysql -u root -p
```

**Terminal 3 — Dashboard**
```bash
cd "C:\Users\info\Desktop\New-Portus-Dasboard"
npm install
npm start
# Opens http://localhost:3000
```

**Terminal 4 — Mobile (Optional)**
```bash
cd "C:\Users\info\Desktop\Chassi App\DriverPortal"
npm install
npm start
# Expo development server ready
```

---

## 🎓 Understanding the System

### Data Flow Example: Create a Task

```
1. User fills form in Dashboard
   ↓
2. Component dispatches Redux action
   dispatch(createTask({ driver_id, vehicle_id, source, destination }))
   ↓
3. Redux async thunk calls API
   POST /api/tasks → Backend
   ↓
4. Backend creates Task in database
   TaskController@store → TaskService→ Task model
   ↓
5. Backend broadcasts WebSocket event
   event(new TaskAssigned(...)) → Redis Pub/Sub
   ↓
6. WebSocket server sends to subscribed clients
   - Dashboard receives → updates list
   - Mobile driver receives → shows in tasks
   ↓
7. All UIs update in real-time via Redux
```

---

## 🔑 Key Concepts

### Real-time Updates
- **WebSocket channel subscriptions** ensure clients stay synchronized
- **Redis Pub/Sub** broadcasts events to all connected servers
- **Redux listeners** update client state when events arrive
- **Automatic UI re-renders** when state changes

### State Management
- **Dashboard & Mobile** use identical Redux structure
- **Shared Redux slices** (fahrerSlice, chassiSlice)
- **Local state** for UI (modals, forms)
- **Redux Persist** (mobile) for offline capability

### Authentication
- **JWT tokens** issued by backend on login
- **Token stored locally** (web: localStorage, mobile: AsyncStorage)
- **Included in all API requests** (Authorization header)
- **WebSocket connections** authenticated via initial message

---

## 📋 Common Development Tasks

### Adding a New Feature
1. **Backend**: Create API endpoint + WebSocket event
2. **Dashboard**: Create Redux slice + components
3. **Mobile**: Replicate dashboard implementation
4. **Test**: Verify across all clients

See: [CODE_REFERENCE.md](CODE_REFERENCE.md#common-tasks--where-to-make-changes)

---

### Fixing a Bug
1. Identify affected layer (backend/frontend)
2. Locate code using [CODE_REFERENCE.md](CODE_REFERENCE.md)
3. Check [INTEGRATION_GUIDE.md](INTEGRATION_GUIDE.md) for contracts
4. Test in all affected clients

---

### Adding a WebSocket Event
1. Define event handler in backend
2. Add broadcast logic to service
3. Add Redux listener in clients
4. Test with browser DevTools

See: [ARCHITECTURE.md](ARCHITECTURE.md#event-broadcast-flow)

---

## 🔍 Finding Information

### "Where is the driver list API endpoint?"
→ [CODE_REFERENCE.md](CODE_REFERENCE.md) → "Driver (Fahrer) Management"

### "What's the Fahrer data structure?"
→ [INTEGRATION_GUIDE.md](INTEGRATION_GUIDE.md) → "Core Data Types" → "Fahrer"

### "How do I add a new Redux slice?"
→ [ARCHITECTURE.md](ARCHITECTURE.md) → "Redux State Management"

### "What are the WebSocket events?"
→ [INTEGRATION_GUIDE.md](INTEGRATION_GUIDE.md) → "WebSocket Protocol"

### "How do I deploy to production?"
→ [PROJECT_NAVIGATOR.md](PROJECT_NAVIGATOR.md) → "Deployment Checklist"

---

## 🛠️ Development Tools

### Debugging Backend
```bash
# Check if running
curl http://localhost:8000/health

# View API response
curl -H "Authorization: Bearer {token}" http://localhost:8000/api/fahrer

# Monitor WebSocket
redis-cli subscribe channel:*
```

### Debugging Dashboard
```javascript
// Redux state
store.getState()

// Redux DevTools (install browser extension)
# Shows all actions and state changes

// Network tab
# Monitor API requests and WebSocket messages
```

### Debugging Mobile
```bash
# Expo dev tools
npm start
# Press 'j' for logs, 'i' for iOS, 'a' for Android

# React Native Debugger
# View Redux state and network requests
```

---

## 📊 Technology Stack Summary

| Layer | Technology | Version | Purpose |
|-------|-----------|---------|---------|
| **Frontend (Web)** | React | 19 | UI framework |
| **Frontend (Web)** | Redux Toolkit | 2.10+ | State management |
| **Frontend (Web)** | TypeScript | 4.9+ | Type safety |
| **Frontend (Web)** | Leaflet | 1.9+ | Maps |
| **Frontend (Web)** | React Router | 7.9+ | Navigation |
| **Frontend (Mobile)** | React Native | 0.81 | Mobile framework |
| **Frontend (Mobile)** | Expo | 54 | Managed RN platform |
| **Frontend (Mobile)** | Redux Toolkit | 2.10+ | State management |
| **Frontend (Mobile)** | React Navigation | 7.x | Navigation |
| **Frontend (Mobile)** | Expo Notifications | 0.32+ | Push notifications |
| **Backend** | PHP | 8.1+ | Server language |
| **Backend** | Ratchet | 0.4.4 | WebSockets |
| **Backend** | Predis | 2.2 | Redis client |
| **Database** | MySQL | 5.7+ | Primary DB |
| **Cache/Pub-Sub** | Redis | 6.0+ | Caching & events |

---

## ✅ Pre-Development Checklist

- [ ] All three projects cloned/pulled
- [ ] Dependencies installed (`npm install`, `composer install`)
- [ ] `.env` files configured with database credentials
- [ ] MySQL/Redis running locally
- [ ] All development servers start without errors
- [ ] Can login to backend
- [ ] WebSocket connections work
- [ ] Read through [CONTEXT.md](CONTEXT.md)

---

## 🤝 Code Conventions

### Naming
- **Backend**: CamelCase for classes, snake_case for methods
- **Frontend**: PascalCase for components, camelCase for functions
- **Database**: snake_case for columns, lowercase for tables
- **Redux**: Slice names match feature names

### File Organization
- **Components**: One per file, PascalCase names
- **Slices**: One slice per file, {feature}Slice.ts pattern
- **Services**: {Feature}Service.php (backend), {feature}.ts (frontend)

### Code Style
- **Backend**: PSR-12 PHP coding standard
- **Frontend**: Prettier formatting + ESLint
- **Comments**: Only for "why", not "what"
- **Types**: Always use TypeScript interfaces

---

## 🚀 Deployment

### Development
- Local machines, no deployment

### Staging
- Update `.env` with staging credentials
- Build and deploy to staging server

### Production
- Full testing on staging first
- Database backups before migrations
- Monitor logs after deployment
- Have rollback plan ready

See: [PROJECT_NAVIGATOR.md](PROJECT_NAVIGATOR.md#deployment-checklist)

---

## 📞 Support & Resources

### Getting Help
1. Check relevant documentation file
2. Search code using [CODE_REFERENCE.md](CODE_REFERENCE.md)
3. Check git commit history for similar changes
4. Consult team leads

### Useful Links
- **API Testing**: Postman, Insomnia, cURL
- **Browser DevTools**: Chrome/Firefox for web debugging
- **Redux DevTools**: Browser extension for state inspection
- **Expo CLI**: `npm start` in mobile directory

---

## 📝 Documentation Status

- ✅ **CONTEXT.md** — Complete system overview
- ✅ **PROJECT_NAVIGATOR.md** — Quick reference & navigation
- ✅ **INTEGRATION_GUIDE.md** — API & data types documentation
- ✅ **ARCHITECTURE.md** — Technical architecture
- ✅ **CODE_REFERENCE.md** — Code location finder
- ✅ **README.md** — This file (index & quick start)

---

## 🎯 Next Steps

**For Immediate Work:**
1. Set up all three projects locally
2. Read [CONTEXT.md](CONTEXT.md) for system overview
3. Test the development setup
4. Pick your first feature to work on
5. Reference [CODE_REFERENCE.md](CODE_REFERENCE.md) to find the code

**For Understanding the System:**
1. Review [ARCHITECTURE.md](ARCHITECTURE.md) architecture diagrams
2. Study [INTEGRATION_GUIDE.md](INTEGRATION_GUIDE.md) data types
3. Explore actual code in each project
4. Run local development servers and interact with the system

---

## 📊 System Capabilities

### Backend
- ✅ REST API for all operations
- ✅ WebSocket real-time updates
- ✅ Redis caching & pub/sub
- ✅ JWT authentication
- ✅ Role-based access control
- ✅ Database persistence

### Web Dashboard
- ✅ Admin interface
- ✅ Real-time vehicle tracking (maps)
- ✅ Task management
- ✅ Driver management
- ✅ Reports & analytics
- ✅ Notification center

### Mobile App
- ✅ Driver portal
- ✅ Task notifications
- ✅ Real-time location tracking
- ✅ Offline capability
- ✅ Push notifications
- ✅ Multi-platform support (Android, iOS, Web)

---

## 🔐 Security Features

- JWT-based authentication
- Role-based access control (RBAC)
- HTTPS/TLS encryption
- Input validation & sanitization
- CORS configuration
- Rate limiting
- SQL injection prevention
- XSS protection

---

## 📈 Performance Optimizations

- Redis caching layer
- Database query optimization
- Pagination for list endpoints
- WebSocket for real-time instead of polling
- Client-side state caching
- Lazy loading of components

---

## 🐛 Troubleshooting

### Can't connect to backend?
- Check: Backend running on localhost:8000
- Check: Firewall/network connectivity
- Check: Database connection configured

### WebSocket not connecting?
- Check: WebSocket server running
- Check: Redis available
- Check: Browser console for errors

### Redux state not updating?
- Check: Redux DevTools for action dispatch
- Check: API response in network tab
- Check: Reducer is handling action

### Mobile app not starting?
- Check: Node modules installed
- Check: Expo CLI updated
- Check: Android SDK/iOS setup

See full troubleshooting: [PROJECT_NAVIGATOR.md](PROJECT_NAVIGATOR.md#troubleshooting-quick-guide)

---

## 📚 Additional Resources

- **Ratchet WebSockets**: http://socketo.me/
- **Redux Documentation**: https://redux.js.org/
- **React Documentation**: https://react.dev/
- **React Native**: https://reactnative.dev/
- **Expo**: https://docs.expo.dev/
- **Leaflet Maps**: https://leafletjs.com/

---

**Last Updated**: 2026-07-13  
**System Version**: 1.0  
**Status**: Stable & Active Development  
**Maintainer**: Development Team

---

## 📑 Document Navigation

```
README.md (you are here)
├── CONTEXT.md ........................ System overview & intro
├── PROJECT_NAVIGATOR.md ............. Quick navigation & setup
├── INTEGRATION_GUIDE.md ............. API & data types
├── ARCHITECTURE.md .................. Technical deep dive
└── CODE_REFERENCE.md ................ Code location finder
```

**Start reading from the top and work your way down, or jump directly to the topic you need.**
