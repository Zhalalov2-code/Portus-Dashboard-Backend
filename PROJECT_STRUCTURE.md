# Структура проекта PortusApp1

## Организация файлов

```
portusApp1/
│
├── api/                           # REST API
│   ├── classes/                   # PHP классы для API
│   │   ├── classUsers.php         # Управление пользователями (+ department_id)
│   │   ├── classFahrer.php        # Управление водителями
│   │   ├── classChassi.php        # Управление шасси
│   │   ├── classLkw.php           # Управление грузовиками
│   │   ├── classMessageChassi.php # Сообщения для шасси
│   │   ├── classMessageLkw.php    # Сообщения для грузовиков
│   │   ├── files_chassi.php       # Файлы для шасси
│   │   ├── files_lkw.php          # Файлы для грузовиков
│   │   ├── classDepartments.php   # Модуль "Задачи по отделам": CRUD отделов
│   │   ├── classTasks.php         # Модуль "Задачи по отделам": задачи, фильтры, комментарии
│   │   ├── classNotifications.php # Модуль "Задачи по отделам": уведомления
│   │   └── EmailNotifier.php      # Отправка email-уведомлений (best-effort)
│   │
│   ├── config/                    # Конфигурация
│   │   ├── db.php                 # Настройки подключения к БД
│   │   └── mail.php                # Настройки email-уведомлений (MAIL_ENABLED, MAIL_FROM)
│   │
│   ├── uploads/                   # Загруженные файлы
│   │   ├── chassi/                # Файлы шасси
│   │   └── lkw/                   # Файлы грузовиков
│   │
│   └── index.php                  # Точка входа API (маршрутизатор)
│
├── cron/
│   └── check_deadlines.php        # Скрипт проверки дедлайнов задач (запускать по расписанию)
│
├── database/
│   └── tasks_module_schema.sql    # SQL-схема модуля "Задачи по отделам"
│
├── .htaccess                      # Apache конфигурация (перенаправление на api/index.php)
├── PROJECT_STRUCTURE.md           # Этот файл
└── .vscode/                       # Конфигурация VS Code (опционально)
```

## API Endpoints (HTTP REST)

> ВАЖНО: `.htaccess` перенаправляет всё на `api/index.php`, а сам `index.php`
> обрезает только префикс `/portusApp1` (НЕ `/portusApp1/api`). Поэтому
> реальные пути — без `/api/`, например `http://localhost/portusApp1/lkw`,
> а не `http://localhost/portusApp1/api/lkw`. Ниже исправленные примеры.

### Пользователи
```
GET    /users              # Получить всех пользователей
POST   /users              # Создать пользователя
POST   /users/login        # Логин пользователя
PUT    /users              # Обновить пользователя
DELETE /users/{id}         # Удалить пользователя
```

### Водители (Fahrer)
```
GET    /fahrer             # Получить всех водителей
POST   /fahrer             # Создать водителя
POST   /fahrer/login       # Логин водителя
PUT    /fahrer             # Обновить водителя
DELETE /fahrer/{id}        # Удалить водителя
```

### Шасси (Chassi)
```
GET    /chassi             # Получить все шасси
POST   /chassi             # Создать шасси
PUT    /chassi             # Обновить шасси
DELETE /chassi/{id}        # Удалить шасси
```

### Грузовики (LKW)
```
GET    /lkw                # Получить все грузовики
POST   /lkw                # Создать грузовик
PUT    /lkw                # Обновить грузовик
DELETE /lkw/{id}           # Удалить грузовик
```

### Сообщения для Шасси
```
GET    /message_chassi     # Получить все сообщения
POST   /message_chassi     # Создать сообщение
DELETE /message_chassi/{id} # Удалить сообщение
```

### Сообщения для Грузовиков
```
GET    /message_lkw        # Получить все сообщения
POST   /message_lkw        # Создать сообщение
DELETE /message_lkw/{id}   # Удалить сообщение
```

### Файлы Шасси
```
GET    /files_chassi       # Получить все файлы
POST   /files_chassi       # Загрузить файл
DELETE /files_chassi/{id}  # Удалить файл
```

### Файлы Грузовиков
```
GET    /files_lkw          # Получить все файлы
POST   /files_lkw          # Загрузить файл
DELETE /files_lkw/{id}     # Удалить файл
```

## Модуль "Задачи по отделам"

> Во всех запросах ниже `requester_id` обязателен (кроме `GET /departments`,
> который публичный) — это ID текущего пользователя, по которому сервер
> сам определяет роль (`role`) и отдел (`department_id`) и проверяет права
> доступа. В этом приложении нет токенов/сессий, поэтому requester_id
> передаётся явным параметром (query-string или тело запроса).

### Отделы (Departments)
```
GET    /departments              # Список отделов (публично)
POST   /departments              # Создать отдел (только admin)
PUT    /departments               # Обновить отдел (только admin), id в теле/route
DELETE /departments/{id}          # Удалить отдел (только admin)
```

### Задачи (Tasks)
```
GET    /tasks                     # Список задач с фильтрами (department_id, assignee_id,
                                   # status, urgency, importance, overdue, search, created_from/to, due_from/to)
GET    /tasks/{id}                # Карточка задачи (с комментариями)
POST   /tasks                     # Создать задачу
PUT    /tasks/{id}                # Обновить задачу (полное редактирование или только статус)
DELETE /tasks/{id}                # Удалить задачу (только admin)
GET    /tasks/{id}/comments       # Список комментариев
POST   /tasks/{id}/comments       # Добавить комментарий
```

Права доступа (без отдельной таблицы истории изменений — не входит в эту версию):
- Видимость задачи: admin — всё; иначе создатель, ответственный, либо сотрудник того же отдела-исполнителя.
- Полное редактирование: admin, создатель задачи, либо руководитель отдела (department_head) для задач своего отдела.
- Ответственный (assignee), не входящий в перечисленные выше, может менять только статус задачи.
- Удаление — только admin.

Вложения файлов к задачам в этой версии не реализованы (по решению пользователя).

### Уведомления (Notifications)
```
GET    /notifications                  # Список уведомлений пользователя (?user_id=, ?unread=1)
PUT    /notifications/{id}/read        # Отметить одно уведомление прочитанным
POST   /notifications/read-all         # Отметить все уведомления пользователя прочитанными
```

Уведомления создаются автоматически при: создании задачи, изменении ответственного,
изменении статуса, новом комментарии. Email-отправка best-effort через `EmailNotifier`
(отключена по умолчанию, см. `api/config/mail.php`).

### Отпуска / Отсутствия (Vacations)
```
GET    /vacations                   # Список заявок (фильтры: user_id, department_id, status, type, year)
GET    /vacations/{id}              # Одна заявка
GET    /vacations/summary           # Баланс отпуска текущего пользователя (?user_id=, ?year=)
GET    /vacations/summary-all       # Баланс всех сотрудников (только HR/admin, ?department_id=, ?year=)
POST   /vacations                   # Создать запись (только HR/admin)
PUT    /vacations/{id}              # Обновить / изменить статус (только HR/admin)
DELETE /vacations/{id}              # Удалить запись (только HR/admin)
```

HR = admin **или** сотрудник отдела с именем, содержащим «personal» (нечувствительно к регистру).
Чтение списка открыто для всех авторизованных пользователей.

### Проверка дедлайнов (cron)
`cron/check_deadlines.php` нужно запускать по расписанию (например, каждые 15–30 минут
через Планировщик заданий Windows). Скрипт создаёт уведомления "срок приближается"
(в течение 24 часов) и "просрочена", используя флаги `deadline_notified`/`overdue_notified`
в таблице `tasks`, чтобы не дублировать уведомления.

## Примеры запросов

### Создание LKW (грузовик)
```bash
curl -X POST http://localhost/portusApp1/lkw \
  -H "Content-Type: application/json" \
  -d '{
    "tuf": "2025-12-01",
    "esp": "2026-01-01",
    "lkw_nummer": "LKW-001",
    "status": "active"
  }'
```

### Обновление LKW
```bash
curl -X PUT http://localhost/portusApp1/lkw \
  -H "Content-Type: application/json" \
  -d '{
    "id_lkw": 1,
    "tuf": "2025-12-05",
    "esp": "2026-01-05",
    "lkw_nummer": "LKW-001",
    "status": "active"
  }'
```

### Удаление LKW
```bash
curl -X DELETE http://localhost/portusApp1/lkw/1
```

### Получить список
```bash
curl -X GET http://localhost/portusApp1/lkw
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
   - Импортируйте SQL-файлы **строго в следующем порядке** (каждый зависит от предыдущего):
     1. `database/auth_tokens_schema.sql` — таблица токенов авторизации
     2. `database/rate_limiting_schema.sql` — таблица rate limiting для регистрации водителей
     3. SQL-схема основных модулей (users, fahrer, chassi, lkw и т.д.) — экспортируйте из вашей БД
     4. SQL-схема модуля "Задачи по отделам" (departments, tasks, task_comments, notifications) — экспортируйте из вашей БД
     5. `database/vacations_schema.sql` — модуль отпусков (требует таблицу `notifications` из пункта 4)
   - После импорта назначьте роли и отделы существующим пользователям
     вручную (`UPDATE users SET role = 'admin' WHERE id = 1;` и т.п.),
     иначе все пользователи останутся обычными "Сотрудниками" без отдела.
   - Добавьте запуск `cron/check_deadlines.php` в Планировщик заданий Windows
     (каждые 15–30 минут) для уведомлений о дедлайнах.
   - Для автоочистки устаревших данных добавьте ещё две задачи в Планировщик:
     - **Ежечасно**: `DELETE FROM fahrer_reg_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR);`
     - **Ежедневно**: `DELETE FROM auth_tokens WHERE expires_at < NOW();`

5. **HTTPS в продакшне**
   Bearer-токены передаются в заголовке `Authorization`. Без HTTPS они
   видны в открытом виде. В продакшн-окружении обязательно настройте SSL
   (Let's Encrypt / сертификат сервера) и добавьте редирект с HTTP на HTTPS.

6. **Протестируйте API**
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
curl -X GET http://localhost/portusApp1/users
```

Если получите JSON ответ - всё работает! ✅
