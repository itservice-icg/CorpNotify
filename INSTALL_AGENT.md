# ติดตั้ง CorpNotifyAgent ให้เปิดพร้อมเครื่อง

ไฟล์ `CorpNotifyAgent.exe` เป็น Agent แบบ self-contained แต่ไม่ใช่ Installer สำเร็จรูป
จึงให้ใช้ `Install-CorpNotifyAgent.ps1` เพื่อคัดลอกไฟล์และตั้งค่าเปิดอัตโนมัติ

## เตรียมไฟล์

วางไฟล์เหล่านี้ไว้โฟลเดอร์เดียวกัน:

```text
Install-CorpNotifyAgent.ps1
CorpNotifyAgent.exe
```

## ติดตั้ง

เปิด PowerShell แบบ **Run as Administrator** แล้วรัน:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\Install-CorpNotifyAgent.ps1 `
  -ApiUrl "https://notify.company.local/api" `
  -EnrollmentKey "ใส่-enrollment-key-ของระบบ" `
  -Department "IT"
```

สคริปต์จะ:

- ติดตั้งไปที่ `C:\Program Files\CorpNotifyAgent`
- สร้าง Task Scheduler ชื่อ `CorpNotifyAgent`
- เปิด Agent เมื่อ user ที่ติดตั้งล็อกอินเข้า Windows
- เริ่ม Agent ให้ทันทีหลังติดตั้ง
- เก็บ device identity และ log ที่ `C:\ProgramData\CorpNotify`

## ถอนการติดตั้ง

```powershell
.\Install-CorpNotifyAgent.ps1 `
  -ApiUrl "https://notify.company.local/api" `
  -EnrollmentKey "unused" `
  -Uninstall
```

คำสั่งถอนการติดตั้งจะลบ startup task และไฟล์ใน `Program Files` แต่ไม่ลบ device identity/log ใน `C:\ProgramData\CorpNotify`

## ข้อควรระวัง

- เครื่องพนักงานต้องเข้าถึง API Server ได้
- Production ควรใช้ HTTPS เท่านั้น
- ห้ามใช้ `127.0.0.1` เว้นแต่ Laravel Server อยู่เครื่องเดียวกัน
- Enrollment key ไม่ควรส่งรวมใน package สาธารณะ
- Scheduled Task ใช้ interactive logon เพราะ Agent เป็น WinForms/Kiosk ไม่ใช่ Windows Service
