# Production Deployment Checklist - Notizen Feature

Полный список всех изменений для функции "Notizen" перед развертыванием на продакшене.

## 🗄️ Database Changes (обязательно применить!)

### SQL Migration File
**Location**: `database/add_notizen_column.sql`

**SQL Commands to Execute**:
```sql
ALTER TABLE lkw
    ADD COLUMN notizen TEXT DEFAULT NULL COMMENT 'Заметки про забронированные сервисные термины';

ALTER TABLE chassi
    ADD COLUMN notizen TEXT DEFAULT NULL COMMENT 'Заметки про забронированные сервисные термины';
```

**How to Apply**:
1. Via phpMyAdmin: Copy & paste SQL, click Execute
2. Via MySQL command line:
   ```bash
   mysql -u <user> -p <database> < database/add_notizen_column.sql
   ```
3. Via MySQL Workbench: Open file and execute

---

## 🔧 Backend Changes (PHP)

### File 1: `api/classes/classLkw.php`
**Changes**: Added `'notizen'` to ALLOWED array (line ~25)

**Before**:
```php
private const ALLOWED = [
    'tuf',
    'esp',
    'lkw_nummer',
    // ...
];
```

**After**:
```php
private const ALLOWED = [
    'tuf',
    'esp',
    'lkw_nummer',
    'notizen',  // ← ADDED
    // ...
];
```

**Status**: ✅ MODIFIED

---

### File 2: `api/classes/classChassi.php`
**Changes**: Added `'notizen'` to ALLOWED array (line ~23)

**Before**:
```php
private const ALLOWED = [
    'chassi_nummer',
    'tuf',
    'esp',
    // ...
];
```

**After**:
```php
private const ALLOWED = [
    'chassi_nummer',
    'tuf',
    'esp',
    'notizen',  // ← ADDED
    // ...
];
```

**Status**: ✅ MODIFIED

---

## 🎨 Frontend Changes (React/TypeScript)

### New Files Created

#### 1. Component: `src/components/NotizModal.tsx`
- Modal dialog for editing notizen
- Uses i18n for translations
- Features: Save, Cancel buttons
- Status: ✅ NEW

#### 2. Styles: `src/css/notizModal.css`
- Modal styling (gradient header, animations)
- Cell styling for inline edit trigger
- Responsive design for mobile
- Status: ✅ NEW

---

### Modified Files

#### 1. `src/store/api/lkwApi.ts`
**Changes**: Added `notizen?: string` to Lkw interface

```typescript
export interface Lkw {
    id_lkw: number
    tuf: string
    esp: string
    lkw_nummer: string
    notizen?: string        // ← ADDED
    // ...
}
```

**Status**: ✅ MODIFIED

---

#### 2. `src/store/api/chassiApi.ts`
**Changes**: Added `notizen?: string` to Chassi interface

```typescript
export interface Chassi {
    id_chassi: number
    chassi_nummer: string
    tuf: string
    esp: string
    notizen?: string        // ← ADDED
    // ...
}
```

**Status**: ✅ MODIFIED

---

#### 3. `src/pages/lkwList.tsx`
**Changes**: 
- Added NotizModal component
- Added notizen column to table
- Added inline edit functionality
- Removed notizen from VehicleModal

**Key Changes**:
```typescript
// Added imports
import NotizModal from '../components/NotizModal';
import { useUpdateLkwInfoMutation } from '../store/api/lkwApi';

// Added state
const [editingLkw, setEditingLkw] = useState<Lkw | null>(null);
const [showNotizModal, setShowNotizModal] = useState(false);

// Added column to table
{
    key: 'notizen',
    label: 'Notizen',
    render: (_value, row) => (...)
}
```

**Status**: ✅ MODIFIED

---

#### 4. `src/pages/chassiListe.tsx`
**Changes**: 
- Added NotizModal component
- Added notizen column to table
- Added inline edit functionality
- Removed notizen from VehicleModal

**Key Changes**:
```typescript
// Added imports
import NotizModal from '../components/NotizModal';
import { useUpdateChassiInfoMutation } from '../store/api/chassiApi';

// Added state
const [editingChassi, setEditingChassi] = useState<Chassi | null>(null);
const [showNotizModal, setShowNotizModal] = useState(false);

// Added column to table
{
    key: 'notizen',
    label: 'Notizen',
    render: (_value, row) => (...)
}
```

**Status**: ✅ MODIFIED

---

#### 5. `src/css/status.css`
**Changes**: Removed old inline-edit CSS rules (no longer needed)

**Status**: ✅ MODIFIED

---

#### 6. `src/components/VehicleModal.tsx`
**Changes**: 
- Removed notizen section
- Removed related imports & functions
- Cleaned up CSS wrapper

**Removed**:
- `notizen` and `isSavingNotizen` state
- `handleSaveNotizen()` function
- Imports: `MdSave`, `useUpdateLkwInfoMutation`, `useUpdateChassiInfoMutation`
- `.notizen-section` JSX block

**Status**: ✅ MODIFIED

---

## 📱 Mobile App Changes (React Native)

### Affected by Project-Wide Changes Only
The notizen feature is currently only in web dashboard.
Mobile app has other unrelated changes from i18n/localization work.

**Status**: ℹ️ No notizen-specific changes needed for mobile app

---

## ✅ Pre-Production Verification

### 1. Database
- [ ] Migration file exists: `database/add_notizen_column.sql`
- [ ] Migration applied to production database
- [ ] Verify columns exist:
  ```sql
  DESCRIBE lkw;      -- should show 'notizen' column
  DESCRIBE chassi;   -- should show 'notizen' column
  ```

### 2. Backend
- [ ] `classLkw.php` has 'notizen' in ALLOWED array
- [ ] `classChassi.php` has 'notizen' in ALLOWED array
- [ ] Files deployed to production server

### 3. Frontend
- [ ] `NotizModal.tsx` deployed
- [ ] `notizModal.css` deployed
- [ ] API interface updated in both lkwApi.ts and chassiApi.ts
- [ ] Pages updated: lkwList.tsx, chassiListe.tsx
- [ ] Build passes without errors: `npm run build`

### 4. Testing
- [ ] Open LKW Liste or Chassi Liste
- [ ] Click notizen cell → Modal opens
- [ ] Type text in modal → Save works
- [ ] Refresh page → Notizen persists
- [ ] Check Network tab → API calls include notizen

---

## 🚨 Important Notes for Production

1. **Database Migration is Critical**
   - Must be applied BEFORE deploying code changes
   - Without it, notizen field won't exist in database
   - API will fail to save notizen

2. **File Deployment Order**
   1. Database migration first
   2. Backend PHP files (classLkw.php, classChassi.php)
   3. Frontend files (all React/TypeScript files)

3. **Cache Invalidation**
   - Clear browser cache after deployment
   - Clear any API response caching
   - Restart backend services

4. **Rollback Plan**
   If issues occur:
   ```sql
   ALTER TABLE lkw DROP COLUMN notizen;
   ALTER TABLE chassi DROP COLUMN notizen;
   ```

---

## 📊 Files Summary

### Backend
- Modified: 2 files (classLkw.php, classChassi.php)
- New: 1 file (database/add_notizen_column.sql)

### Frontend
- New: 2 files (NotizModal.tsx, notizModal.css)
- Modified: 6 files (chassiApi.ts, lkwApi.ts, lkwList.tsx, chassiListe.tsx, VehicleModal.tsx, status.css)

### Total Changes
- **Database**: 1 migration file
- **Backend**: 2 modified files
- **Frontend**: 8 files changed (2 new, 6 modified)

---

## 🔄 Git Commands for Production

```bash
# Backend
cd /path/to/portusApp1
git add api/classes/classLkw.php
git add api/classes/classChassi.php
git add database/add_notizen_column.sql
git commit -m "feat: add notizen field for LKW and Chassi - backend"
git push origin main

# Frontend Dashboard
cd /path/to/New-Portus-Dasboard
git add src/components/NotizModal.tsx
git add src/css/notizModal.css
git add src/store/api/chassiApi.ts
git add src/store/api/lkwApi.ts
git add src/pages/lkwList.tsx
git add src/pages/chassiListe.tsx
git add src/components/VehicleModal.tsx
git add src/css/status.css
git commit -m "feat: add notizen feature with modal editing"
git push origin main
```

---

**Last Updated**: 2026-07-13
**Status**: Ready for Production Deployment
**Tested**: ✅ Yes
**Breaking Changes**: ❌ No
