# Icon & Symbol Usage Rule

## กฎการใช้สัญลักษณ์ใน UI

ในโปรเจกต์นี้ **ห้ามใช้ Emoji เป็นองค์ประกอบของ UI ทุกกรณี**

หากต้องการแสดงสัญลักษณ์ สถานะ Action Navigation หรือ Visual Indicator ให้ใช้ **Icon เท่านั้น**

### ต้องใช้ Icon สำหรับ

- ปุ่ม Action
- เมนู
- Navigation
- Status
- Notification
- Alert
- Search / Filter
- Edit / Delete / Add
- Download / Upload
- Settings
- User / Profile
- Success / Warning / Error / Info
- Expand / Collapse
- Arrow / Direction
- File / Folder
- Calendar / Time
- Checkbox / Selection
- Dashboard Metric
- Empty State

### ห้ามใช้

- Emoji เช่น 🙂 🚀 ✅ ❌ ⚠️ 🔍 📁 📄 🗑️ ✏️
- Unicode Emoji
- Emoji ที่ใส่ตรงใน HTML / JSX / Blade / JavaScript
- Emoji ในปุ่ม หัวข้อ Card Notification หรือ Status
- Emoji แทน Status Icon

ตัวอย่างที่ห้ามใช้:

```html
<button>🔍 Search</button>
<button>✏️ Edit</button>
<button>🗑️ Delete</button>
```

ให้ใช้ Icon Component แทน:

```tsx
<Button>
  <SearchIcon />
  Search
</Button>

<Button>
  <PencilIcon />
  Edit
</Button>

<Button>
  <TrashIcon />
  Delete
</Button>
```

## Icon Library

ให้ใช้ Icon Library ที่มีอยู่ในโปรเจกต์ก่อนเสมอ

ตัวอย่างที่แนะนำ:

- Lucide Icons
- Heroicons
- Tabler Icons
- Material Symbols

ห้ามเพิ่ม Icon Library ใหม่หากโปรเจกต์มี Icon Library ใช้งานอยู่แล้ว เว้นแต่มีเหตุผลจำเป็น

## Consistency

Icon ต้องมีรูปแบบสอดคล้องกันทั้งระบบ:

- ขนาดใกล้เคียงกัน
- Stroke / Weight เดียวกัน
- Alignment ถูกต้อง
- ใช้ Icon เดียวกันสำหรับ Action เดียวกัน
- ห้ามผสมหลาย Icon Style โดยไม่จำเป็น

ตัวอย่าง:

```tsx
<Search size={16} />
<Pencil size={16} />
<Trash2 size={16} />
```

## Accessibility

Icon ที่เป็น Action ต้องมีข้อความหรือ Accessible Label ที่อธิบายความหมาย:

```tsx
<button aria-label="Delete notification">
  <Trash2 size={16} />
</button>
```

ห้ามใช้ Icon เพียงอย่างเดียวหากผู้ใช้อาจไม่เข้าใจความหมายของ Action

## Enforcement

เมื่อสร้างหรือแก้ไข UI:

1. ตรวจสอบว่าไม่มี Emoji ถูกเพิ่มเข้ามา
2. หากพบ Emoji เดิมใน UI ให้เปลี่ยนเป็น Icon ที่เหมาะสม
3. Reuse Icon Library เดิมของโปรเจกต์
4. ห้ามเปลี่ยน Business Logic เพียงเพื่อเปลี่ยน Emoji เป็น Icon
5. รักษา Accessibility ของ Icon และ Action
6. การออกแบบใหม่ต้องใช้กฎนี้เป็น Default

## กฎหลัก

> UI Symbol = Icon Only  
> Emoji = Not Allowed

## SweetAlert2 Rule

สำหรับ Web UI ของ CorpNotify ให้ใช้ **SweetAlert2** เป็นมาตรฐานสำหรับ:

- กล่องยืนยันก่อน Create / Update / Deactivate / Delete
- Success หลังทำรายการสำเร็จ เมื่อจำเป็นต้องแจ้งผลแบบชัดเจน
- Warning, Error และ Info ที่เป็น feedback สำคัญของผู้ใช้

ห้ามใช้ `window.alert()` หรือ `window.confirm()` ในหน้า Web ที่ผู้ใช้มองเห็น
เว้นแต่เป็นกรณีฉุกเฉินหรือหน้าเก่าที่ไม่สามารถโหลด SweetAlert2 ได้

กฎการใช้งาน:

1. ใช้ SweetAlert2 เวอร์ชันที่โหลดไว้ใน Layout กลางของระบบ
2. ต้องมีปุ่มยืนยันและยกเลิกที่สื่อความหมายชัดเจน
3. Action ที่ยืนยันแล้วต้องเรียก Form / Route / CSRF เดิม
4. ต้องไม่เปลี่ยน Business Logic เพียงเพื่อเพิ่ม Dialog
5. Dialog ต้องรองรับ Keyboard และแสดงผลได้บน Mobile
