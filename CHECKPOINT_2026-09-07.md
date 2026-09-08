# CorpNotify Checkpoint — 2026-09-07

## สถานะล่าสุด

ระบบ CorpNotify มี flow หลักสำหรับ Notification และ Policy แล้ว ได้แก่:

- Device registration และ heartbeat
- Notification polling และ delivery tracking
- Opened / Read completed / Acknowledged tracking
- Policy Quiz และ quiz attempt history
- Append-only notification event log
- Device snapshot ใน attempt/event audit data
- Admin notification tracking, attempt UI และ timeline
- CSV / Excel export
- Per-device Bearer token authentication
- Windows Agent รองรับ Policy kiosk flow
- Dashboard แบบ modern และ Role-based Smart Dashboard

## Role-based Dashboard

รองรับ role:

- `admin` — เห็นข้อมูลและ Quick Actions สำหรับสร้างประกาศ/จัดการอุปกรณ์
- `manager` — เห็นข้อมูลและ Quick Actions สำหรับการบริหารงาน
- `viewer` — เห็นข้อมูลสรุปและรายงาน โดยไม่แสดง Quick Actions สำหรับการแก้ไข

ผู้ใช้ที่ไม่มี role หรือมีค่าไม่รู้จักจะ fallback เป็น `viewer` เพื่อความปลอดภัย

เพิ่ม migration:

```text
server/database/migrations/2026_09_07_000005_add_role_to_users_table.php
```

Admin ที่สร้างผ่าน `AdminUserSeeder` จะได้รับ role `admin`

## UI / Icon Standard

ใช้กฎจาก:

```text
Icon-Symbol-Usage-Rule.md
```

การปรับล่าสุด:

- ใช้ Bootstrap Icons เป็น icon library กลางของ web UI
- ลบ Emoji จาก UI หลักและ Windows Agent title
- เพิ่ม `aria-hidden="true"` ให้ decorative icons
- ปรับ Dashboard, Navigation, Notifications และ action icons
- ปรับ Devices/Login ให้ใช้ card, spacing, border และ shadow แนวเดียวกับ Dashboard

## ไฟล์สำคัญที่แก้ล่าสุด

- `server/app/Http/Controllers/DashboardController.php`
- `server/app/Models/User.php`
- `server/database/seeders/AdminUserSeeder.php`
- `server/database/migrations/2026_09_07_000005_add_role_to_users_table.php`
- `server/resources/views/dashboard.blade.php`
- `server/resources/views/layouts/app.blade.php`
- `server/resources/views/notifications/index.blade.php`
- `server/public/css/corpnotify.css`
- `CorpNotifyAgent/PolicyNotificationForm.cs`

## Bug fix ล่าสุด

แก้หน้า Notification detail กรณี `$attempt->answers` เป็น `null`:

```blade
@foreach(($attempt->answers ?? collect()) as $answer)
```

ทำให้หน้า `/notifications/{id}` ไม่ล้มด้วย `foreach() argument must be of type array|object, null given`

## Test ล่าสุด

Laravel:

```text
14 tests passed
86 assertions passed
```

Agent:

```text
7 tests passed
0 failed
```

Agent build:

```text
0 warnings
0 errors
```

View cache:

```text
Compiled views cleared successfully
```

ควรรันคำสั่ง Laravel จากโฟลเดอร์ `server`:

```powershell
cd C:\Users\NAWIN\Downloads\CorpNotify\server
php artisan test --without-tty
```

## สิ่งที่ยังต้องทำก่อนถือว่า Production-complete

1. รัน migration `2026_09_07_000005_add_role_to_users_table.php` บนฐานข้อมูลจริง
2. กำหนด role ให้ผู้ใช้เดิมใน production ตามหน้าที่จริง
3. เพิ่ม middleware/authorization สำหรับจำกัด route ตาม role หากต้องการ security เต็มรูปแบบ
4. ทดสอบ Agent GUI แบบ end-to-end กับฐานข้อมูลจริง:
   - รับประกาศ
   - Delivered
   - Opened
   - Read to bottom
   - Quiz fail
   - Retry
   - Quiz pass
   - Acknowledge
5. ตรวจ visual UI จริงใน Chrome/Windows Agent หลัง deploy
6. พิจารณาเปลี่ยน Bootstrap Icons CDN เป็น asset ที่ bundle/local หาก production ห้ามพึ่ง external CDN

## ข้อควรระวัง

- ห้ามแก้ไข Policy ที่ publish แล้วแบบ in-place หากมี response อยู่แล้ว
- ห้ามลบ historical quiz attempts หรือ notification events
- `notification_quiz_responses` ยังเป็น legacy latest-answer record และอาจถูก update ด้วย `updateOrCreate()`; audit history ที่เชื่อถือได้ควรใช้ `notification_quiz_attempts` และ `notification_quiz_attempt_answers`
- Dashboard acknowledgment หลักนับเป็นรายการประกาศต่อเครื่อง ไม่ใช่จำนวนคนไม่ซ้ำ
- ห้ามลบ `VIEW_COMPILED_PATH` จาก `.env` จนกว่าปัญหา compiled Blade directory เดิมจะได้รับการแก้ไข
