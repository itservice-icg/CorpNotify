# HANDOFF — CorpNotify

Project:
D:\02_Project\CorpNotify

Laravel Server:
D:\02_Project\CorpNotify\server

Windows Agent:
D:\02_Project\CorpNotify\CorpNotifyAgent

==================================================
CURRENT VERIFIED STATUS — 2026-09-08
==================================================

Implemented and verified in the current workspace:

- Policy versioning: editing a published Policy creates a new version and preserves the old version/questions.
- Quiz attempt history: each submission is stored in notification_quiz_attempts and notification_quiz_attempt_answers.
- Read completion: read_completed_at and policy_read_completed event are recorded by the Agent flow.
- Append-only notification events and device snapshots.
- Admin device tracking, filters, timeline, CSV and Excel export.
- Per-device API token authentication with transitional support for pre-enrollment devices.
- Role route protection: admin/manager may modify notifications; viewer is read-only.
- Blade compiled views path is configured for this workspace in server/.env.

Validation:

- Laravel: 15 tests passed, 90 assertions.
- Windows Agent: 7 tests passed, 0 failed.
- Database migrations on the configured database: all migrations through 2026_09_07_000005 are marked Ran.

Still required for production sign-off:

- End-to-end Windows Agent GUI testing against the real server/database, including retry and network interruption.
- Review whether the external Bootstrap/Bootstrap Icons CDN is acceptable for the deployment environment.
- Keep published Policy versions immutable and retain historical attempts/events.
- Web UI confirmation and important feedback use SweetAlert2; do not add native alert/confirm dialogs.


==================================================
1. SYSTEM PURPOSE
==================================================

CorpNotify เป็นระบบประกาศภายในองค์กร

ประกอบด้วย:

1. Laravel Admin / API Server
2. Windows CorpNotifyAgent
3. Device tracking
4. Notification delivery tracking
5. Policy acknowledgement
6. Policy Quiz / แบบทดสอบ


Notification Flow:

Server
→ Agent Poll
→ Notification assigned to Device
→ Delivered
→ Opened
→ Acknowledged


Policy Flow:

Policy Notification
→ แสดงประกาศ
→ เปิด Policy
→ ผู้ใช้ต้องอ่านลงถึงด้านล่าง
→ ทำแบบทดสอบ
→ ต้องตอบถูกครบ
→ Acknowledge
→ ปิด Policy window


==================================================
2. IMPORTANT — DO NOT BREAK
==================================================

ห้ามเปลี่ยนโดยไม่จำเป็น:

- Existing API routes
- Device registration
- Notification matching logic
- Notification targeting
- Policy kiosk flow
- Existing notification delivery flow
- Existing database data
- Existing acknowledgement behavior

ก่อนแก้ Source Code ให้ inspect implementation จริงก่อนเสมอ

ห้าม assume:
- table
- field
- relation
- route
- Agent behavior


==================================================
3. CURRENT NOTIFICATION API
==================================================

Relevant routes include:

GET
/api/notifications/pending

POST
/api/notifications/{notification}/delivered

POST
/api/notifications/{notification}/opened

POST
/api/notifications/{notification}/quiz

POST
/api/notifications/{notification}/acknowledge


Main API Controller:

server\app\Http\Controllers\Api\NotificationApiController.php


==================================================
4. DEVICE NOTIFICATION TRACKING
==================================================

Table:

notification_devices

Current important fields:

notification_id
device_id
delivered_at
opened_at
acknowledged_at


Meaning:

delivered_at
= Agent ได้รับ Notification

opened_at
= User เปิด Notification / Policy

acknowledged_at
= User รับทราบสำเร็จ


Notification ↔ Device relation already exists.


==================================================
5. POLICY QUIZ
==================================================

Tables:

notification_quiz_questions

notification_quiz_responses


Quiz response currently stores:

notification_id
device_id
quiz_question_id
answer
is_correct
answered_at


Current values for answer:

use
not_use


Server currently records which:

- Notification
- Device
- Question
- Answer
- Correct / Incorrect
- Answered time


IMPORTANT:

Current implementation uses:

NotificationQuizResponse::updateOrCreate()

key approximately:

device_id + quiz_question_id


Therefore:

ถ้าเครื่องตอบคำถามเดิมอีกครั้ง
คำตอบเก่าจะถูก UPDATE

ตัวอย่าง:

Attempt 1:
ตอบผิด

Attempt 2:
ตอบถูก

Database จะเหลือคำตอบล่าสุด

ไม่ได้เก็บประวัติ Attempt 1


==================================================
6. WINDOWS AGENT POLICY FLOW
==================================================

Relevant files:

CorpNotifyAgent\ApiClient.cs

CorpNotifyAgent\NotificationManager.cs

CorpNotifyAgent\PolicyNotificationForm.cs


NotificationManager currently calls:

MarkDeliveredAsync()

MarkOpenedAsync()

SubmitQuizAsync()

AcknowledgeAsync()


PolicyNotificationForm:

Step 1:
ประกาศ

Step 2:
Policy content

Step 3:
Quiz


Policy window is kiosk style.

User cannot complete flow until requirements are satisfied.


Policy content currently requires scrolling to bottom before button is enabled.

However:

Server DOES NOT currently have a separate:

read_completed_at

Therefore:

opened_at != guaranteed audit field for "อ่าน Policy จบแล้ว"


==================================================
7. ADMIN POLICY DETAIL
==================================================

Relevant files:

server\app\Http\Controllers\NotificationController.php

server\resources\views\notifications\show.blade.php


NotificationController::show() has been updated to load:

creator

quizQuestions

quizResponses.device

quizResponses.question


Admin Policy detail page has been updated with section:

"ผลตอบแบบทดสอบรายเครื่อง"


It displays:

Device hostname
Username
Device UUID

Each quiz question
Answer
Correct / Incorrect
Answered time

and overall status:

ตอบถูกครบ
or
ยังไม่ผ่าน


==================================================
8. DATABASE VERIFIED
==================================================

Real database already contains quiz response data.

Example verified:

Device:
IT-CHAYANON

Username:
Chayanon

Responses exist for multiple notifications.

Therefore quiz responses are being stored in the real DB,
not only defined in migrations.


==================================================
9. NOTIFICATION UI REDESIGN
==================================================

Notification index was redesigned away from legacy table/list style.

Relevant files:

server\resources\views\notifications\index.blade.php

server\public\css\corpnotify.css


Design direction:

Modern Notification Feed
21st.dev inspired
Modern SaaS dashboard

Legacy action buttons were reduced / moved toward contextual actions.

Do not revert this page back to Bootstrap-style table UI.


Backup files were created earlier:

index.blade.php.bak

corpnotify.css.bak


==================================================
10. LARAVEL BLADE CACHE ISSUE
==================================================

A Windows filesystem / permission issue occurred with:

server\storage\framework\views


Laravel failed while replacing compiled Blade file:

rename(...tmp, ...php)

Error:

Access is denied (code: 5)


The problematic compiled file could not be accessed normally.


WORKAROUND IMPLEMENTED:

New compiled Blade directory:

server\storage\framework\compiled_views


.env now includes:

VIEW_COMPILED_PATH="C:/Users/NAWIN/Downloads/CorpNotify/server/storage/framework/compiled_views"


After:

php artisan optimize:clear


AdminNotificationTest passed:

4 tests
25 assertions


Do not remove VIEW_COMPILED_PATH unless original ACL problem is fixed.


==================================================
11. IMPORTANT DATA INTEGRITY RISK
==================================================

NotificationController currently has:

syncQuizQuestions()


Behavior:

existing quiz questions are deleted
then recreated


This is dangerous after a Policy has responses.

Because quiz responses reference quiz_question_id
and database relationships may cascade delete responses.


Therefore:

DO NOT edit/refactor this casually.


Recommended architecture:

Draft
→ Publish
→ Immutable Policy Version


Once Policy is published and users have responded:

DO NOT modify that Policy version.

Create a new version instead.


==================================================
12. NEXT PRIORITIES
==================================================

Priority P0:

A. Policy Versioning

Create a safe versioning strategy:

Policy v1
Policy v2
Policy v3

Published Policy should be immutable.

Historical answers must remain linked to the exact Policy/question version.


B. Quiz Attempt History

Current responses overwrite previous answers.

Need:

quiz_attempts

Suggested fields:

id
notification_id
device_id
attempt_no
started_at
submitted_at
passed
created_at
updated_at


quiz_attempt_answers:

id
quiz_attempt_id
quiz_question_id
answer
is_correct
answered_at


Result:

Device A

Attempt #1
Question 1 → wrong
Question 2 → correct
Failed

Attempt #2
Question 1 → correct
Question 2 → correct
Passed


Never overwrite previous attempts.


==================================================
13. NEXT PRIORITY — READ COMPLETION
==================================================

Add server-side tracking:

read_completed_at


Recommended flow:

Delivered
→ Opened
→ Read Completed
→ Quiz Submitted
→ Quiz Passed
→ Acknowledged


Agent:

When Policy content reaches bottom for the first time:

POST read-completed event


Do NOT use opened_at as read completed.


==================================================
14. AUDIT EVENT LOG
==================================================

Recommended table:

notification_events


Suggested fields:

id
notification_id
device_id
event_type
event_at
metadata JSON
created_at


Possible event types:

delivered
opened
policy_read_completed
quiz_started
quiz_submitted
quiz_failed
quiz_passed
acknowledged


Prefer append-only event history.


==================================================
15. DEVICE SNAPSHOT
==================================================

Current responses reference live Device record.

Potential issue:

Hostname / username / department may change later.


For Audit, snapshot identity at event/attempt time:

device_uuid
hostname
username
department
ip_address
agent_version


Historical records should not visually change
because Device master data changed later.


==================================================
16. API SECURITY
==================================================

Current APIs primarily identify device through:

device_uuid


Do not treat UUID as a secret.


Future production improvement:

per-device credential / token
or signed request

+ HTTPS


Do not implement security changes without ensuring
current Agent compatibility.


==================================================
17. ADMIN TRACKING UI — RECOMMENDED
==================================================

Policy Detail should eventually show:

Hostname
Username
Department
Delivered
Opened
Read Completed
Quiz Attempts
Quiz Passed
Acknowledged
Last Activity


Click/expand a device to see:

Attempt #1
Attempt #2
...

and each submitted answer.


Useful filters:

All
Not opened
Reading
Quiz failed
Quiz passed
Acknowledged


Add export:

CSV / Excel


==================================================
18. EXPECTED FINAL AUDIT FLOW
==================================================

Create Policy Draft
    ↓
Publish Policy v1
    ↓
Assign Device
    ↓
Delivered
    ↓
Opened
    ↓
Read Completed
    ↓
Quiz Attempt #1
    ├─ Failed
    │    ↓
    │  Keep history
    │
    ↓
Quiz Attempt #2
    ↓
Passed
    ↓
Acknowledged
    ↓
Permanent Audit History


==================================================
19. IMPLEMENTATION RULES FOR NEXT AGENT
==================================================

Before modifying anything:

1. Inspect current schema
2. Inspect current migrations
3. Inspect NotificationApiController
4. Inspect NotificationController
5. Inspect Notification model relations
6. Inspect CorpNotifyAgent flow
7. Check existing DB data
8. Create backup before major DB/flow changes


For DB changes:

Create NEW migrations.

Do not modify old production migrations as the primary deployment strategy.


For historical data:

Never intentionally delete existing Policy responses.


For Policy:

Audit history is more important than convenience of editing.


==================================================
20. TESTING REQUIRED
==================================================

After every change test:

Regular Notification:

Create
→ Agent receives
→ Delivered
→ Opened
→ Acknowledged


Policy:

Create Policy
→ Agent receives
→ Delivered
→ Open
→ Read to bottom
→ Quiz wrong
→ retry
→ Quiz correct
→ Acknowledge
→ close


Admin:

Open notification detail
→ verify device
→ verify answers
→ verify timestamps


Historical:

Edit/create newer Policy version
→ old responses MUST remain


Run Laravel tests after changes.


==================================================
CURRENT GOAL
==================================================

Do not redesign random parts of the system.

Next development should focus on making Policy acknowledgement
and quiz results reliable for organizational audit.

Highest priority:

1. Policy Versioning
2. Quiz Attempt History
3. read_completed_at
4. Append-only Audit Events
5. Admin Device Tracking
6. Export / Reporting
7. API authentication hardening
