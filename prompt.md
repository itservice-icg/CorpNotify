คุณคือ Senior Full-Stack Developer และ System Architect

ให้สร้างระบบ **CorpNotify** สำหรับแจ้งประกาศภายในองค์กร โดยมี Web Admin สำหรับสร้าง/แก้ไข/ยกเลิกประกาศ และมี Windows Client Agent สำหรับแสดง Popup บนเครื่องพนักงาน

## เป้าหมายระบบ

Admin สามารถสร้างประกาศจากหน้า Web แล้วส่งไปยังเครื่อง Windows ของพนักงานโดยไม่ต้องแก้หรือ Build Client Agent ใหม่ทุกครั้ง

Architecture:

```text
Laravel Admin Panel
        ↓
Laravel REST API
        ↓
MySQL
        ↑
HTTPS Polling
        ↑
CorpNotifyAgent.exe
        ↓
Windows Popup
```

## Technology Stack

### Server

* PHP
* Laravel
* MySQL
* Blade
* Bootstrap หรือ UI ที่เรียบง่าย
* REST API
* Authentication สำหรับ Admin

### Client

* C#
* .NET 8
* WinForms
* Windows 10 / Windows 11
* Build เป็น standalone `.exe`
* ไม่ต้องติดตั้ง .NET Runtime เพิ่ม

---

# Phase 1: Laravel Backend

สร้าง Module สำหรับจัดการประกาศ

## Table: notifications

```text
id
title
message
type
url
target_type
start_at
expire_at
is_active
created_by
created_at
updated_at
deleted_at
```

type รองรับ:

```text
info
warning
critical
```

target_type:

```text
all
department
device
user
```

---

## Table: devices

```text
id
device_uuid
hostname
username
ip_address
department
agent_version
last_seen_at
is_active
created_at
updated_at
```

`device_uuid` ต้อง unique

---

## Table: notification_devices

ใช้เก็บสถานะการส่งประกาศไปยังแต่ละเครื่อง

```text
id
notification_id
device_id
delivered_at
opened_at
acknowledged_at
created_at
updated_at
```

Foreign Key ต้องถูกต้องและใช้ InnoDB

---

# Admin Panel

สร้างหน้า:

```text
/dashboard

/notifications
/notifications/create
/notifications/{id}
/notifications/{id}/edit

/devices
```

หน้า Notifications ต้องแสดง:

```text
หัวข้อ
ประเภท
กลุ่มเป้าหมาย
เวลาเริ่ม
เวลาหมดอายุ
สถานะ
จำนวนเครื่องที่ได้รับ
จำนวนผู้รับทราบ
Action
```

Action:

```text
ดู
แก้ไข
ปิดประกาศ
```

ไม่ใช้ Hard Delete เป็นค่าเริ่มต้น

เมื่อ Admin ต้องการลบประกาศ ให้ใช้:

```text
is_active = false
```

หรือ Soft Delete

เพื่อเก็บ Audit History

---

# Create Notification

Form ต้องมี:

```text
Title

Message

Type
- Info
- Warning
- Critical

Target
- ทุกเครื่อง
- Department
- Device
- Username

URL

Start Date/Time

Expire Date/Time
```

Validation ต้องครบ

---

# REST API

สร้าง API ดังนี้

## Register Device

```http
POST /api/device/register
```

Request:

```json
{
  "device_uuid": "...",
  "hostname": "PC-001",
  "username": "user01",
  "ip_address": "192.168.1.10",
  "agent_version": "1.0.0"
}
```

ถ้า device_uuid มีอยู่แล้วให้ Update Device เดิม

ห้ามสร้าง Device ซ้ำ

---

## Heartbeat

```http
POST /api/device/heartbeat
```

ใช้ Update:

```text
last_seen_at
hostname
username
ip_address
agent_version
```

---

## Pending Notifications

```http
GET /api/notifications/pending
```

Client ต้องส่ง device_uuid มาด้วย

Server ต้อง Return เฉพาะประกาศที่:

```text
is_active = true

start_at <= CURRENT_TIME

expire_at > CURRENT_TIME
หรือ expire_at = null
```

และตรงกับ Target ของ Device

ประกาศที่ Device acknowledge แล้วไม่ต้องส่งซ้ำ

ตัวอย่าง Response:

```json
[
  {
    "id": 25,
    "title": "แจ้งปิดระบบ ERP",
    "message": "ระบบ ERP จะปิดปรับปรุงเวลา 17:00 - 18:00",
    "type": "warning",
    "url": "https://intranet.company.local/news/25"
  }
]
```

---

## Delivered

```http
POST /api/notifications/{id}/delivered
```

บันทึก:

```text
delivered_at
```

---

## Opened

```http
POST /api/notifications/{id}/opened
```

บันทึก:

```text
opened_at
```

---

## Acknowledge

```http
POST /api/notifications/{id}/acknowledge
```

บันทึก:

```text
acknowledged_at
```

---

# Phase 2: Windows Agent

สร้าง Project:

```text
CorpNotifyAgent/
├── Program.cs
├── ApiClient.cs
├── DeviceManager.cs
├── NotificationManager.cs
├── NotificationForm.cs
├── ConfigManager.cs
├── Models/
│   └── Notification.cs
└── appsettings.json
```

Agent ต้องทำงานใน User Session

ไม่ใช้ Windows Service สำหรับแสดง UI ใน Phase แรก

---

# Client Startup Flow

เมื่อโปรแกรมเริ่ม:

```text
Start Agent
    ↓
Load Configuration
    ↓
Generate / Load device_uuid
    ↓
Register Device
    ↓
Start Heartbeat
    ↓
Poll Notification
    ↓
Show Popup
```

---

# Device UUID

ครั้งแรกที่เปิด Agent ให้ Generate UUID

ตัวอย่าง:

```text
93d9321e-b501-41ee-a210-...
```

บันทึกไว้ Local

เช่น:

```text
%ProgramData%\CorpNotify\device.json
```

ห้าม Generate ใหม่ทุกครั้งที่เปิดโปรแกรม

---

# Polling

ทุก:

```text
15 วินาที
```

ให้เรียก:

```http
GET /api/notifications/pending
```

ห้าม Block UI Thread

ใช้ async/await

---

# Popup UI

ตัวอย่าง:

```text
┌─────────────────────────────────────────┐
│ 🔔 ประกาศจากบริษัท                     │
├─────────────────────────────────────────┤
│                                         │
│ แจ้งปิดระบบ ERP                         │
│                                         │
│ ระบบ ERP จะปิดปรับปรุง                  │
│ เวลา 17:00 - 18:00                      │
│                                         │
│ [เปิดรายละเอียด]          [รับทราบ]    │
└─────────────────────────────────────────┘
```

Popup ต้อง:

* อยู่ด้านหน้าของหน้าต่างทั่วไป
* ไม่ Freeze Application
* รองรับภาษาไทย UTF-8
* แสดง Title
* Message
* Type
* URL
* ปุ่มรับทราบ
* ปุ่มเปิดรายละเอียด

---

# Notification Type

Info:

```text
🔵 Info
```

Warning:

```text
🟡 Warning
```

Critical:

```text
🔴 Critical
```

Critical สามารถตั้ง TopMost ได้

แต่ห้ามสร้างพฤติกรรมที่ User ไม่สามารถปิดหรือควบคุมเครื่องได้

---

# Open URL

เมื่อ User กด:

```text
เปิดรายละเอียด
```

เปิด URL ด้วย Default Browser

ใช้:

```csharp
Process.Start(new ProcessStartInfo
{
    FileName = url,
    UseShellExecute = true
});
```

ก่อนเปิด URL ต้อง Validate Scheme

อนุญาตเฉพาะ:

```text
http
https
```

ห้าม Execute:

```text
file://
cmd://
powershell
javascript:
```

---

# Security

Client ห้ามรองรับ Remote Command Execution

Server ห้ามส่ง:

```text
CMD
PowerShell
Executable
Script
Registry Command
```

ให้ Client รองรับเฉพาะ Action ที่กำหนดไว้เท่านั้น

Phase แรกใช้:

```text
show_message
open_url
```

API ต้องใช้ HTTPS

ต้อง Validate ทุก Input

Admin routes ต้อง Authentication

---

# Client Configuration

```json
{
  "ApiUrl": "https://notify.company.local/api",
  "PollingIntervalSeconds": 15
}
```

---

# Logging

Agent เก็บ Log:

```text
%ProgramData%\CorpNotify\logs\
```

เช่น:

```text
2026-08-13 09:00 Agent started
2026-08-13 09:00 Device registered
2026-08-13 09:01 Notification 25 received
2026-08-13 09:01 Notification 25 displayed
2026-08-13 09:02 Notification 25 acknowledged
```

ห้าม Log:

```text
password
token
secret
```

---

# Error Handling

หาก Server Offline:

Agent ต้องไม่ Crash

Flow:

```text
API Error
   ↓
Write Log
   ↓
Wait
   ↓
Retry next polling cycle
```

ห้ามแสดง Error Popup รบกวน User ทุกครั้งที่ Server ติดต่อไม่ได้

---

# Build

Build Client เป็น:

```text
win-x64
self-contained
single-file
Release
```

ตัวอย่าง:

```bash
dotnet publish -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true
```

Output:

```text
CorpNotifyAgent.exe
```

---

# Coding Requirements

* Code อ่านง่าย
* แยก Responsibility
* ไม่ Over-engineer
* ใช้ async/await
* มี Error Handling
* ไม่มี Hard-coded API URL ใน Source
* ไม่มี Credential ใน Source
* รองรับภาษาไทย
* Windows 10/11
* Laravel ใช้ Migration
* Naming Database ใช้ snake_case
* Model / Controller ตาม Laravel convention

---

# สิ่งที่ต้องทำก่อนเป็นอันดับแรก

อย่าสร้างระบบทั้งหมดพร้อมกัน

เริ่มจาก Prototype ที่ทำงานจริงตามลำดับ:

```text
1. Laravel notifications migration
2. Notification CRUD
3. Device registration API
4. Pending notification API
5. C# Agent
6. Agent Register Device
7. Agent Poll API
8. Windows Popup
9. Acknowledge API
```

เป้าหมายแรกคือ:

```text
Admin สร้างประกาศจาก Laravel
        ↓
เครื่อง Client Poll เจอ
        ↓
Popup เด้งบน Windows
        ↓
User กดรับทราบ
        ↓
Laravel บันทึก acknowledged_at
```

เมื่อ Flow นี้ทำงานสมบูรณ์แล้วจึงค่อยเพิ่ม:

```text
Department
User Targeting
Scheduling
Dashboard
Agent Auto Update
MSI Deployment
GPO Deployment
```

ทุกครั้งก่อนแก้ Code ให้ตรวจโครงสร้าง Project ปัจจุบันก่อน และอย่าทำลาย Function เดิมที่ใช้งานได้
