# CorpNotify

ระบบประกาศภายในองค์กร ประกอบด้วย Laravel Admin/API และ Windows Agent (Phase 2)

## Phase 1: Laravel Backend

โค้ดอยู่ใน `server/` และรองรับ:

- Session authentication สำหรับ Admin
- Dashboard, Notification CRUD และ Device list
- ปิดประกาศด้วย `is_active = false` โดยไม่ Hard Delete
- Device register/heartbeat แบบ upsert ด้วย `device_uuid`
- Pending notification filtering ตามเวลา, สถานะ และเป้าหมาย
- Delivered, opened และ acknowledged tracking ต่อเครื่อง
- URL validation เฉพาะ HTTP/HTTPS

### ติดตั้ง

```bash
cd server
composer install
copy .env.example .env
php artisan key:generate
```

กำหนดฐานข้อมูล MySQL และบัญชี Admin ใน `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=corp_notify
DB_USERNAME=corp_notify
DB_PASSWORD=change-me

ADMIN_NAME="CorpNotify Admin"
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=change-me
```

จากนั้นรัน:

```bash
php artisan migrate --seed
php artisan serve
```

Production ต้อง terminate TLS ที่ Web Server/Reverse Proxy และเปิดให้เรียก API ผ่าน HTTPS เท่านั้น

### ทดสอบ

```bash
php artisan test
vendor/bin/pint --test
```

## API

- `POST /api/device/register`
- `POST /api/device/heartbeat`
- `GET /api/notifications/pending?device_uuid={uuid}`
- `POST /api/notifications/{id}/delivered`
- `POST /api/notifications/{id}/opened`
- `POST /api/notifications/{id}/acknowledge`

Status endpoints รับ JSON `{ "device_uuid": "..." }` และรับเฉพาะประกาศที่เคยถูก assign จาก pending endpoint ให้เครื่องนั้นแล้ว

## 07/09/2569
 - Full Screen
 - TopMost
 - ไม่มี Border
 - ปิดไม่ได้
 - Alt+F4 ไม่ได้
 - Alt+Tab ไม่ได้
 - Windows Key ถูก block

### block:
 - Alt + Tab
 - Alt + Esc
 - Alt + F4
 - Ctrl + Esc
 - Windows Key