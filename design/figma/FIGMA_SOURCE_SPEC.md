# CorpNotify — Figma Source Spec

Generated from the current real implementation in:
`D:\02_Project\CorpNotify`

## Source of truth
- Laravel views: `server\resources\views`
- Web styles: `server\public\css\corpnotify.css`
- Web routes: `server\routes\web.php`
- Windows Agent: `CorpNotifyAgent\NotificationForm.cs`
- Policy Agent: `CorpNotifyAgent\PolicyNotificationForm.cs`
- UI rules: `Icon-Symbol-Usage-Rule.md`

## Figma pages
1. `00 Foundations`
2. `01 Admin Web`
3. `02 Notification Management`
4. `03 Devices`
5. `04 Windows Agent`
6. `05 Policy Flow`
7. `06 Components`
8. `07 Prototype Flow`

## Base viewport
- Desktop Web: 1440 x 1024
- Windows Agent: 1920 x 1080
- Tablet check: 1024 x 768
- Mobile check: 390 x 844
## Foundations / design tokens
- Background: `#F6F8FB`
- Surface: `#FFFFFF`
- Border: `#E6EAF0`
- Primary text: `#101828`
- Muted text: `#667085`
- Primary blue: `#2563EB`
- Primary soft: `#EFF6FF`
- Danger / Critical: `#DC2626`
- Success: `#16A34A`
- Warning: `#D97706`
- Policy purple: `#7C3AED`

## Typography
Current product uses system/Segoe UI style typography.
For Figma use `Segoe UI` when available and preserve current visual hierarchy.
- Page title: 32–40 / Bold
- Section title: 18–20 / Bold
- Card title: 15–16 / Bold
- Body: 14–16 / Regular
- Meta: 12–13 / Regular or Semibold
- Badge: 11–12 / Bold

## Radius & spacing
- Main panel radius: 16–20
- Card radius: 14–18
- Button radius: 10–12
- Chip radius: 999
- Standard page gap: 20–24
- Card padding: 16–20
- Main content max width: 1180–1320
## Admin Web — required frames
### A01 Login
Source: `server\resources\views\auth\login.blade.php`
- CorpNotify shared navbar/logo shell
- Email
- Password
- Remember me
- Login primary button

### A02 Dashboard — Admin
Source: `server\resources\views\dashboard.blade.php`
- Overview hero + role badge + date chip
- 4 metric cards
- 7-day announcement chart
- acknowledgement donut
- device status
- recent activity
- recent notifications
- quick actions

### A03 Dashboard — Viewer
Use same dashboard but remove privileged quick actions.
Keep read-only reporting and notification/device overview.

### A04 Notifications List
Source: `server\resources\views\notifications\index.blade.php`
- Communication Center hero
- total count + Create Notification
- 4 metrics
- Search + Type filter
- status tabs
- modern notification feed
- contextual action menu
- pagination + empty states
### A05 Create Notification
Source: `server\resources\views\notifications\form.blade.php`
- Notification Composer header
- Announcement content section
- Type / Target / Target value
- Detail URL
- Start / Expire date-time
- Active switch
- Sticky status/action aside
- Save confirmation state

### A06 Edit Notification
Same form structure as A05 using existing values.
Preserve versioning behavior in implementation; Figma only represents UI.

### A07 Notification Detail — Regular
Source: `server\resources\views\notifications\show.blade.php`
- Title + type/status badges
- Delivered / Opened / Acknowledged / Target metrics
- Announcement content panel
- Detail key-value panel
- Back + Edit actions

### A08 Policy Detail — Admin Audit
- Policy + quiz content
- questions and correct-answer indicators
- Policy Tracking & Audit
- search + status filter
- per-device expandable row
- timestamps: Delivered / Opened / Read Complete / Acknowledged
- Quiz attempts
- Audit timeline
- CSV / Excel export
### A09 Devices
Source: `server\resources\views\devices\index.blade.php`
- Device Management hero
- total / active / online metrics
- device list panel
- hostname + online/offline status
- username / department / IP
- agent version + last seen
- pagination + empty state

## Windows Agent — required frames
Source: `CorpNotifyAgent\NotificationForm.cs`

### W01 Regular Notification — Initial
- Full-screen kiosk window
- dark CorpNotify header
- new notification count
- instruction strip
- one or more notification cards
- type badge + title + message preview
- View details action
- fixed footer
- acknowledgement disabled until bottom reached

### W02 Regular Notification — Expanded
- Same as W01
- full message expanded
- Hide details action

### W03 Regular Notification — Ready to acknowledge
- footer primary action enabled
- button text: `รับทราบและปิด`

Important behavior represented in prototype:
- no close/minimize control
- cannot complete until scrolled to bottom
- acknowledgement closes the kiosk screen
## Policy kiosk flow — required frames
Source: `CorpNotifyAgent\PolicyNotificationForm.cs`

### P01 Step 1 — Notice
- Full-screen kiosk
- dark header + CorpNotify
- step indicator: `1 ประกาศ / 2 นโยบาย / 3 แบบทดสอบ`
- type badge
- notification title + message
- instruction that Policy + Quiz are required
- primary button to Policy

### P02 Step 2 — Policy Reading
- active step = นโยบาย
- instruction bar
- scrollable Policy body
- fixed footer
- disabled CTA before read-to-bottom
- enabled CTA at bottom: `รับทราบ ›`

### P03 Step 3 — Quiz Empty
- active step = แบบทดสอบ
- instruction bar
- question cards
- binary choices: `ใช้ / ไม่ใช้`
- Submit button

### P04 Quiz Validation
- incomplete-answer error state
- preserve all selected answers

### P05 Quiz Failed
- general failure message only
- do not reveal the exact wrong question
- allow retry

### P06 Quiz Passed
- success state
- acknowledge and close flow
## Components to build in Figma
- Navbar / Admin
- Brand logo lockup
- Primary / Secondary / Danger buttons
- Icon button
- Metric card
- Panel / Card
- Notification feed item
- Device card
- Type badge: Info / Warning / Critical / Policy
- Status badge: Active / Inactive / Online / Offline
- Meta pill
- Search field
- Select / filter
- Segmented tabs
- Pagination
- Empty state
- SweetAlert-style confirmation modal
- Policy step indicator
- Quiz answer toggle
- Audit device accordion

## Interaction variants
Create component variants for:
- Default / Hover / Focus / Disabled
- Active / Inactive
- Online / Offline
- Selected / Unselected
- Info / Warning / Critical / Policy
- Menu Closed / Open
- Accordion Collapsed / Expanded

## Icon rule
UI symbols must use icons only; no Emoji.
Web currently standardizes on Bootstrap Icons.
Do not introduce decorative Emoji in the Figma screens.
Text data coming from users/database is not rewritten by this rule.
## Prototype connections
### Admin
Login → Dashboard
Dashboard → Notifications
Dashboard → Devices
Dashboard Quick Action → Create Notification
Notifications → Notification Detail
Notifications → Edit
Create/Edit → confirmation modal → Notifications
Policy Detail → Export actions shown as prototype endpoints

### Regular Agent
Initial → Expand Details → Scroll Bottom → Acknowledge & Close

### Policy Agent
Notice → Policy Reading → Read Bottom → Quiz
Quiz incomplete → Validation error
Quiz failed → Retry
Quiz passed → Acknowledge → Close

## Assets already in the real project
- `server\public\images\corpnotify-logo.png`
- `server\public\images\corpnotify-favicon.png`

## Fidelity rules
- Base designs on current implemented fields and flows only.
- Do not invent new business actions.
- Preserve role restrictions: admin/manager can modify; viewer is read-only.
- Preserve Policy immutable/versioned behavior conceptually.
- Preserve audit terminology: Delivered, Opened, Read complete, Quiz Attempts, Acknowledged.
- Preserve kiosk requirement on Agent flows.

## Status
This spec is ready to be used as the source map for building the editable Figma file.