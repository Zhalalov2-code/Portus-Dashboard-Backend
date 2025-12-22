# Структура проекта PortusApp1

## Организация файлов

```
portusApp1/
│
├── api/                           # REST API
│   ├── classes/                   # PHP классы для API
│   │   ├── classUsers.php         # Управление пользователями
│   │   ├── classFahrer.php        # Управление водителями
│   │   ├── classChassi.php        # Управление шасси
│   │   ├── classLkw.php           # Управление грузовиками
│   │   ├── classMessageChassi.php # Сообщения для шасси
│   │   ├── classMessageLkw.php    # Сообщения для грузовиков
│   │   ├── files_chassi.php       # Файлы для шасси
│   │   └── files_lkw.php          # Файлы для грузовиков
│   │
│   ├── config/                    # Конфигурация
│   │   └── db.php                 # Настройки подключения к БД
│   │
│   ├── uploads/                   # Загруженные файлы
│   │   ├── chassi/                # Файлы шасси
│   │   └── lkw/                   # Файлы грузовиков
│   │
│   └── index.php                  # Точка входа API (маршрутизатор)
│
├── .htaccess                      # Apache конфигурация (перенаправление на api/index.php)
├── PROJECT_STRUCTURE.md           # Этот файл
└── .vscode/                       # Конфигурация VS Code (опционально)
```

## API Endpoints (HTTP REST)

### Пользователи
```
GET    /api/users              # Получить всех пользователей
POST   /api/users              # Создать пользователя
POST   /api/users/login        # Логин пользователя
PUT    /api/users              # Обновить пользователя
DELETE /api/users/{id}         # Удалить пользователя
```

### Водители (Fahrer)
```
GET    /api/fahrer             # Получить всех водителей
POST   /api/fahrer             # Создать водителя
POST   /api/fahrer/login       # Логин водителя
PUT    /api/fahrer             # Обновить водителя
DELETE /api/fahrer/{id}        # Удалить водителя
```

### Шасси (Chassi)
```
GET    /api/chassi             # Получить все шасси
POST   /api/chassi             # Создать шасси
PUT    /api/chassi             # Обновить шасси
DELETE /api/chassi/{id}        # Удалить шасси
```

### Грузовики (LKW)
```
GET    /api/lkw                # Получить все грузовики
POST   /api/lkw                # Создать грузовик
PUT    /api/lkw                # Обновить грузовик
DELETE /api/lkw/{id}           # Удалить грузовик
```

### Сообщения для Шасси
```
GET    /api/message_chassi     # Получить все сообщения
POST   /api/message_chassi     # Создать сообщение
DELETE /api/message_chassi/{id} # Удалить сообщение
```

### Сообщения для Грузовиков
```
GET    /api/message_lkw        # Получить все сообщения
POST   /api/message_lkw        # Создать сообщение
DELETE /api/message_lkw/{id}   # Удалить сообщение
```

### Файлы Шасси
```
GET    /api/files_chassi       # Получить все файлы
POST   /api/files_chassi       # Загрузить файл
DELETE /api/files_chassi/{id}  # Удалить файл
```

### Файлы Грузовиков
```
GET    /api/files_lkw          # Получить все файлы
POST   /api/files_lkw          # Загрузить файл
DELETE /api/files_lkw/{id}     # Удалить файл
```

## Примеры запросов

### Создание LKW (грузовик)
```bash
curl -X POST http://localhost/portusApp1/api/lkw \
  -H "Content-Type: application/json" \
  -d '{
    "tuf": "2025-12-01",
    "esp": "2026-01-01",
    "lkw_nummer": "LKW-001"
  }'
```

### Обновление LKW
```bash
curl -X PUT http://localhost/portusApp1/api/lkw \
  -H "Content-Type: application/json" \
  -d '{
    "id_lkw": 1,
    "tuf": "2025-12-05",
    "esp": "2026-01-05",
    "lkw_nummer": "LKW-001"
  }'
```

### Удаление LKW
```bash
curl -X DELETE http://localhost/portusApp1/api/lkw/1
```

### Получить список
```bash
curl -X GET http://localhost/portusApp1/api/lkw
```

## Технологии

- **PHP**: 8.2.12
- **Сервер**: Apache (XAMPP)
- **База данных**: MySQL
- **Архитектура**: REST API (HTTP)
- **Зависимости**: нет (чистый PHP)

## Установка и запуск

1. **Убедитесь, что XAMPP запущен**
   ```bash
   # Apache и MySQL должны быть запущены
   ```

2. **Клонируйте или поместите проект в**
   ```
   C:\xampp\htdocs\portusApp1\
   ```

3. **Создайте БД (если не создана)**
   ```sql
   CREATE DATABASE portusapp1;
   ```

4. **Импортируйте схему БД**
   - Откройте phpMyAdmin: http://localhost/phpmyadmin
   - Импортируйте SQL файл

5. **Протестируйте API**
   ```bash
   curl http://localhost/portusApp1/api/lkw
   ```

## Файловая система

### Права доступа
Убедитесь, что папка `api/uploads/` имеет права на запись:
```bash
chmod 755 api/uploads/
chmod 755 api/uploads/chassi/
chmod 755 api/uploads/lkw/
```

## Отладка

### Логи PHP
```
C:\xampp\apache\logs\error.log
C:\xampp\php\logs\php_errors.log
```

### Логи MySQL
```
C:\xampp\mysql\data\
```

### Проверка подключения к БД
```bash
curl -X GET http://localhost/portusApp1/api/users
```

Если получите JSON ответ - всё работает! ✅
