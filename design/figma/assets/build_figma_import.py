from pathlib import Path
import base64, html
OUT=Path(r'D:\02_Project\CorpNotify\design\figma\assets')
W,H=1440,900
C={'bg':'#f6f8fb','surface':'#ffffff','border':'#e6eaf0','ink':'#101828','muted':'#667085','blue':'#2563eb','blueSoft':'#eff6ff','green':'#16a34a','greenSoft':'#f0fdf4','orange':'#d97706','orangeSoft':'#fff7ed','red':'#dc2626','redSoft':'#fef2f2','purple':'#7c3aed','purpleSoft':'#f5f3ff','nav':'#ffffff'}
def esc(s): return html.escape(str(s))
def rect(x,y,w,h,fill='#fff',r=14,stroke=None,sw=1,opacity=1):
    st=f' stroke="{stroke}" stroke-width="{sw}"' if stroke else ''
    return f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{r}" fill="{fill}" opacity="{opacity}"{st}/>'
def text(x,y,s,size=16,fill=None,weight=400,anchor='start'):
    return f'<text x="{x}" y="{y}" font-family="Segoe UI,Tahoma,Arial,sans-serif" font-size="{size}" font-weight="{weight}" fill="{fill or C["ink"]}" text-anchor="{anchor}">{esc(s)}</text>'
def line(x1,y1,x2,y2,color=None,sw=1): return f'<line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}" stroke="{color or C["border"]}" stroke-width="{sw}"/>'
def circle(cx,cy,r,fill): return f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="{fill}"/>'
def icon_box(x,y,label,fill,fg): return rect(x,y,38,38,fill,11)+text(x+19,y+25,label,15,fg,800,'middle')
def pill(x,y,w,label,fill,fg): return rect(x,y,w,28,fill,14)+text(x+w/2,y+19,label,12,fg,800,'middle')
def svg_start(w=W,h=H):
    return f'<svg xmlns="http://www.w3.org/2000/svg" width="{w}" height="{h}" viewBox="0 0 {w} {h}"><rect width="100%" height="100%" fill="{C["bg"]}"/>'
def navbar(active='Dashboard'):
    s=rect(0,0,W,68,'#ffffff',0)+line(0,68,W,68,'#e8edf3')
    logo=OUT/'corpnotify-logo.png'
    if logo.exists():
        b64=base64.b64encode(logo.read_bytes()).decode(); s+=f'<image x="52" y="15" width="164" height="38" preserveAspectRatio="xMidYMid meet" href="data:image/png;base64,{b64}"/>'
    else: s+=text(52,43,'CorpNotify',22,C['ink'],800)
    nav=[('Dashboard',250),('ประกาศ',372),('อุปกรณ์',470)]
    for label,x in nav:
        if label==active: s+=rect(x-12,14,98 if label!='Dashboard' else 110,40,C['blueSoft'],11)
        s+=text(x,40,label,14,C['blue'] if label==active else '#475569',700)
    s+=rect(1260,18,120,34,'#ffffff',10,C['border'])+text(1320,40,'ออกจากระบบ',12,'#334155',700,'middle')
    return s
def header_title(eyebrow,title,subtitle,x=54,y=116):
    return text(x,y,eyebrow.upper(),11,C['blue'],800)+text(x,y+43,title,34,C['ink'],800)+text(x,y+73,subtitle,15,C['muted'],400)
def metric(x,y,w,label,value,tone='blue',symbol='•'):
    soft=C.get(tone+'Soft',C['blueSoft']); fg=C.get(tone,C['blue'])
    return rect(x,y,w,108,'#fff',18,C['border'])+icon_box(x+18,y+20,symbol,soft,fg)+text(x+68,y+39,label,14,'#475569',700)+text(x+18,y+84,value,30,C['ink'],850)
def panel(x,y,w,h,title=None):
    s=rect(x,y,w,h,'#fff',18,C['border'])
    if title: s+=text(x+20,y+32,title,16,C['ink'],800)+line(x,y+50,x+w,y+50)
    return s
def dashboard():
    s=svg_start()+navbar('Dashboard')+header_title('Overview','Dashboard','ภาพรวมการใช้งานระบบแจ้งประกาศภายในองค์กร')
    s+=pill(260,130,86,'ADMIN','#fff','#64748b')+rect(1190,112,190,52,'#fff',13,C['border'])+text(1208,132,'วันนี้',11,C['muted'],700)+text(1208,153,'8 ก.ย. 2569',14,C['ink'],800)
    xs=[54,388,722,1056]; data=[('ประกาศที่เปิดอยู่','20','blue','N'),('อุปกรณ์ทั้งหมด','24','purple','PC'),('ออนไลน์ใน 2 นาที','22','green','ON'),('รับทราบแล้ว','40','orange','ACK')]
    for x,d in zip(xs,data): s+=metric(x,205,304,*d)
    s+=panel(54,337,594,250,'ภาพรวมประกาศ')+text(540,369,'7 วันที่ผ่านมา',11,C['muted'],700)
    base=530
    for i,h in enumerate([55,82,64,118,91,128,103]):
        x=92+i*72; s+=rect(x,base-h,14,h,'#3b82f6',5)+rect(x+20,base-h*.7,14,h*.7,'#22c55e',5)+text(x+17,554,['จ','อ','พ','พฤ','ศ','ส','อา'][i],11,C['muted'],600,'middle')
    s+=text(230,574,'● ประกาศที่สร้าง',11,'#3b82f6',600)+text(360,574,'● รับทราบแล้ว',11,'#22c55e',600)
    s+=panel(666,337,332,250,'สถานะการรับทราบ')+circle(832,448,72,'#e5e7eb')+circle(832,448,56,'#fff')+text(832,445,'78%',28,C['ink'],850,'middle')+text(832,467,'รับทราบแล้ว',11,C['muted'],600,'middle')
    s+=panel(1016,337,370,250,'สถานะอุปกรณ์')
    for i,(name,state) in enumerate([('IT-CHAYANON','ออนไลน์'),('OFFICE-07','ออนไลน์'),('SALES-02','ออฟไลน์')]):
        yy=390+i*54; s+=circle(1042,yy+7,5,C['green'] if state=='ออนไลน์' else '#94a3b8')+text(1060,yy+10,name,13,C['ink'],750)+text(1305,yy+10,state,11,C['green'] if state=='ออนไลน์' else C['muted'],700,'end')
    s+=panel(54,605,422,238,'กิจกรรมล่าสุด')+panel(494,605,492,238,'ประกาศล่าสุด')+panel(1004,605,382,238,'การดำเนินการด่วน')
    for i,t in enumerate(['ส่งแบบทดสอบ','รับทราบประกาศแล้ว','เปิดประกาศ']): s+=circle(80,660+i*48,5,C['blue'])+text(98,665+i*48,t,13,C['ink'],700)+text(430,665+i*48,['09:22','09:18','09:10'][i],11,C['muted'],500,'end')
    for i,t in enumerate(['ประกาศแจ้งเตือนพนักงาน','ประกาศใหม่','Policy การใช้งาน IT']): s+=icon_box(518,646+i*56,'N',C['blueSoft'],C['blue'])+text(566,662+i*56,t,13,C['ink'],750)+text(566,681+i*56,'เปิดใช้งาน · ส่งถึง 2 เครื่อง',10,C['muted'])
    for i,(t,fg,bg) in enumerate([('สร้างประกาศใหม่',C['blue'],C['blueSoft']),('จัดการอุปกรณ์',C['purple'],C['purpleSoft']),('ดู Policy และการรับทราบ',C['green'],C['greenSoft'])]): s+=rect(1028,644+i*54,334,42,bg,11)+text(1046,670+i*54,t,13,fg,750)
    return s+'</svg>'
def notifications():
    s=svg_start()+navbar('ประกาศ')+header_title('Communication Center','Notifications','ติดตามประกาศภายในองค์กร การส่งถึง และการรับทราบจากเครื่องพนักงาน')
    s+=pill(300,130,48,'41','#111827','#fff')+rect(1200,118,180,46,C['blue'],12)+text(1290,147,'สร้างประกาศ',14,'#fff',800,'middle')
    data=[('ในหน้านี้','20','blue','D'),('เปิดใช้งาน','20','green','ON'),('ส่งถึงแล้ว','40','purple','S'),('รับทราบแล้ว','40','orange','A')]
    for x,d in zip([54,388,722,1056],data): s+=metric(x,198,304,*d)
    s+=panel(54,328,1332,500)
    s+=rect(74,348,1000,44,'#f8fafc',12,C['border'])+text(96,376,'ค้นหาหัวข้อ เนื้อหา เป้าหมาย หรือประเภท',13,'#94a3b8')+rect(1090,348,276,44,'#fff',12,C['border'])+text(1110,376,'ทุกประเภท',13,'#334155',700)
    tabs=[('ทั้งหมด',20),('เปิดอยู่',20),('ปิดแล้ว',0),('ยังไม่มีผู้รับทราบ',0),('มีผู้รับทราบ',20)]
    x=74
    for i,(lab,cnt) in enumerate(tabs):
        w=108 if i<3 else 166; s+=pill(x,410,w,f'{lab} {cnt}', '#111827' if i==0 else '#f8fafc','#fff' if i==0 else '#64748b'); x+=w+8
    rows=[('ประกาศใหม่','INFO','ข้อความ ประกาศใหม่','24 minutes ago','blue'),('ประกาศแจ้งเตือนพนักงาน','POLICY','เรื่อง การใช้งานระบบ IT และ AI อย่างปลอดภัย','40 minutes ago','purple'),('ประกาศจากฝ่าย IT','INFO','แนวทางการใช้งานระบบสารสนเทศอย่างปลอดภัย','44 minutes ago','blue'),('แจ้งปิดปรับปรุงระบบ','WARNING','ระบบจะปิดปรับปรุงชั่วคราวตามช่วงเวลาที่กำหนด','5 hours ago','orange')]
    y=462
    for title,badge,desc,tm,tone in rows:
        fg=C[tone]; soft=C[tone+'Soft']; s+=rect(74,y,1292,76,'#fff',14)+rect(74,y,4,76,fg,4)+icon_box(94,y+18,badge[0],soft,fg)+text(148,y+29,title,14,C['ink'],800)+pill(335,y+13,82,badge,soft,fg)+text(1328,y+28,tm,11,'#94a3b8',500,'end')+text(148,y+51,desc,12,'#475569')
        sx=780
        for lab in ['เปิดใช้งาน','ทุกเครื่อง','ส่งถึง 2','รับทราบ 2']:
            s+=pill(sx,y+42,100 if lab!='ทุกเครื่อง' else 88,lab,'#f8fafc','#64748b'); sx+=108
        y+=82
    return s+'</svg>'
def devices():
    s=svg_start()+navbar('อุปกรณ์')+header_title('Device Management','อุปกรณ์','ตรวจสอบอุปกรณ์ที่ลงทะเบียน สถานะการเชื่อมต่อ และข้อมูล Agent')
    data=[('อุปกรณ์ทั้งหมด','24','blue','PC'),('ลงทะเบียนใช้งาน','24','green','OK'),('ออนไลน์ใน 2 นาที','22','purple','ON')]
    for x,d in zip([54,496,938],data): s+=metric(x,205,388,*d)
    s+=panel(54,337,1332,500,'รายการอุปกรณ์')+text(1288,370,'หน้า 1 / 3',11,C['muted'],700,'end')
    rows=[('IT-CHAYANON','Chayanon','IT','192.168.1.25','v1.0.7','ออนไลน์'),('OFFICE-07','Somsak','Admin','192.168.1.48','v1.0.7','ออนไลน์'),('SALES-02','Anan','Sales','192.168.1.63','v1.0.6','ออฟไลน์'),('WAREHOUSE-03','Kanda','Warehouse','192.168.1.72','v1.0.7','ออนไลน์')]
    y=402
    for host,user,dept,ip,ver,state in rows:
        online=state=='ออนไลน์'; fg=C['green'] if online else '#94a3b8'; soft=C['greenSoft'] if online else '#f1f5f9'
        s+=rect(74,y,1292,82,'#fff',14,C['border'])+rect(74,y+12,4,58,fg,3)+icon_box(96,y+20,'PC',soft,fg)+text(150,y+28,host,14,C['ink'],800)+pill(276,y+13,82,state,soft,fg)+text(150,y+53,f'{user}   ·   {dept}   ·   {ip}',12,C['muted'])+text(1324,y+31,f'Agent {ver}',12,'#475569',700,'end')+text(1324,y+54,'เชื่อมต่อล่าสุด 08/09/2026 11:05',10,'#94a3b8',500,'end')
        y+=94
    return s+'</svg>'
def composer():
    s=svg_start()+navbar('ประกาศ')+header_title('Notification Composer','สร้างประกาศใหม่','กำหนดข้อความ กลุ่มเป้าหมาย เวลาเผยแพร่ และ Policy Quiz ได้ในหน้าเดียว')
    s+=panel(54,205,920,620)+panel(994,205,392,300,'สถานะประกาศ')
    s+=text(78,244,'เนื้อหาประกาศ',17,C['ink'],800)+text(78,266,'เขียนให้สั้น ชัด และผู้รับเข้าใจได้ทันที',12,C['muted'])
    s+=text(78,302,'หัวข้อ',12,'#334155',700)+rect(78,316,870,44,'#fff',10,C['border'])+text(92,344,'หัวข้อประกาศ',13,'#94a3b8')
    s+=text(78,392,'ข้อความ',12,'#334155',700)+rect(78,406,870,110,'#fff',10,C['border'])+text(92,434,'พิมพ์ข้อความประกาศ...',13,'#94a3b8')
    s+=line(54,545,974,545)+text(78,580,'การส่งและกลุ่มเป้าหมาย',17,C['ink'],800)+text(78,605,'กำหนดประเภทประกาศ ผู้รับ และลิงก์รายละเอียด',12,C['muted'])
    labels=['ประเภท','เป้าหมาย','ค่าเป้าหมาย']; vals=['Info','ทุกเครื่อง','เว้นว่างเมื่อเลือกทุกเครื่อง']; xs=[78,370,662]
    for x,l,v in zip(xs,labels,vals): s+=text(x,644,l,12,'#334155',700)+rect(x,658,264,44,'#fff',10,C['border'])+text(x+14,686,v,12,'#64748b')
    s+=text(78,738,'ช่วงเวลาเผยแพร่',17,C['ink'],800)+rect(78,758,416,44,'#fff',10,C['border'])+rect(510,758,438,44,'#fff',10,C['border'])+text(92,786,'08/09/2026 11:10',12,'#64748b')+text(524,786,'ไม่กำหนด',12,'#94a3b8')
    s+=text(1018,248,'เปิดใช้งานเพื่อให้ Agent เห็นประกาศตามช่วงเวลา',12,C['muted'])+pill(1018,286,118,'เปิดใช้งาน',C['greenSoft'],C['green'])+rect(1018,350,344,46,C['blue'],11)+text(1190,379,'สร้างประกาศ',14,'#fff',800,'middle')+rect(1018,408,344,44,'#fff',11,C['border'])+text(1190,436,'ยกเลิก',13,'#475569',700,'middle')
    return s+'</svg>'
def detail():
    s=svg_start()+navbar('ประกาศ')+header_title('Notification Detail','ประกาศแจ้งเตือนพนักงาน','รายละเอียดประกาศ การส่งถึง และการรับทราบ')
    s+=pill(54,178,84,'POLICY',C['purpleSoft'],C['purple'])+pill(146,178,92,'เปิดใช้งาน',C['greenSoft'],C['green'])+pill(246,178,88,'Policy v2',C['purpleSoft'],C['purple'])
    for x,label,val in [(54,'Delivered','24'),(266,'Opened','22'),(478,'Read complete','20'),(690,'Acknowledged','18'),(902,'Target','ทุกเครื่อง')]: s+=metric(x,226,194,label,val,'blue','•')
    s+=panel(54,356,824,220,'เนื้อหาประกาศ')+text(78,412,'เรื่อง การใช้งานระบบ IT และ AI อย่างปลอดภัย',14,C['ink'],700)+text(78,442,'กรุณาอ่านรายละเอียดและปฏิบัติตามนโยบายขององค์กร',13,'#475569')
    s+=panel(898,356,488,220,'รายละเอียด')
    for i,(k,v) in enumerate([('เป้าหมาย','ทุกเครื่อง'),('เริ่ม','08/09/2026 08:41'),('หมดอายุ','ไม่กำหนด'),('ผู้สร้าง','Admin')]): s+=text(922,412+i*38,k,12,C['muted'],700)+text(1042,412+i*38,v,12,C['ink'],700)
    s+=panel(54,594,1332,250,'Policy Tracking & Audit')
    s+=rect(78,642,640,42,'#fff',10,C['border'])+text(94,669,'ค้นหา Hostname / Username / Department / UUID',12,'#94a3b8')+rect(734,642,220,42,'#fff',10,C['border'])+text(752,669,'ทุกสถานะ',12,'#64748b')
    for i,(host,status) in enumerate([('IT-CHAYANON','รับทราบแล้ว'),('OFFICE-07','Quiz ผ่าน'),('SALES-02','กำลังอ่าน')]):
        y=704+i*42; s+=text(82,y,host,13,C['ink'],750)+text(320,y,'Chayanon · IT',11,C['muted'])+pill(1110,y-21,130,status,C['greenSoft'] if i<2 else C['blueSoft'],C['green'] if i<2 else C['blue'])+text(1320,y,'08/09 11:05',10,C['muted'],500,'end')
    return s+'</svg>'
def agent():
    s=svg_start(); s+=rect(0,0,W,90,'#0f172a',0)+text(50,42,'CorpNotify',22,'#fff',800)+text(50,68,'มีประกาศใหม่ 1 รายการ',12,'#cbd5e1')+pill(1320,28,58,'1',C['blue'],'#fff')
    s+=rect(0,90,W,52,'#fff',0)+text(50,122,'กรุณาอ่านประกาศทั้งหมด แล้วเลื่อนลงด้านล่างสุดเพื่อเปิดใช้งานปุ่มรับทราบ',13,C['muted'])
    s+=rect(54,174,1332,530,'#fff',18,C['border'])+rect(54,174,6,530,C['red'],3)+pill(84,204,100,'CRITICAL',C['redSoft'],C['red'])+text(84,270,'เนื้อหาประกาศ',30,C['ink'],800)+text(84,316,'ข้อความ',16,'#475569')
    s+=line(84,354,1356,354)+text(84,404,'ประเภท',11,C['muted'],700)+text(84,429,'ประกาศทั่วไป',14,C['ink'],750)+text(300,404,'ผู้รับ',11,C['muted'],700)+text(300,429,'พนักงานทั้งหมด',14,C['ink'],750)+text(556,404,'ประกาศเมื่อ',11,C['muted'],700)+text(556,429,'08/09/2026 11:10',14,C['ink'],750)
    s+=rect(84,618,150,46,'#fff',10,C['border'])+text(159,647,'Hide details',13,'#475569',700,'middle')
    s+=rect(0,822,W,78,'#fff',0)+line(0,822,W,822)+text(50,867,'การกดรับทราบจะยืนยันประกาศทั้งหมดในหน้าต่างนี้',12,C['muted'])+rect(1200,840,180,44,C['blue'],10)+text(1290,868,'รับทราบและปิด',13,'#fff',800,'middle')
    return s+'</svg>'
def login():
    s=svg_start(); s+=rect(0,0,W,H,'#f6f8fb',0); s+=rect(430,160,580,560,'#fff',24,C['border'])
    logo=OUT/'corpnotify-logo.png'
    if logo.exists():
        b64=base64.b64encode(logo.read_bytes()).decode(); s+=f'<image x="560" y="205" width="320" height="72" preserveAspectRatio="xMidYMid meet" href="data:image/png;base64,{b64}"/>'
    else: s+=text(720,250,'CorpNotify',30,C['ink'],850,'middle')
    s+=text(720,330,'เข้าสู่ระบบผู้ดูแล',24,C['ink'],800,'middle')+text(720,360,'เข้าสู่ระบบเพื่อจัดการประกาศและอุปกรณ์',13,C['muted'],400,'middle')
    s+=text(500,418,'อีเมล',12,'#334155',700)+rect(500,432,440,48,'#fff',11,C['border'])+text(516,462,'admin@example.com',13,'#94a3b8')
    s+=text(500,520,'รหัสผ่าน',12,'#334155',700)+rect(500,534,440,48,'#fff',11,C['border'])+text(516,564,'••••••••',16,'#94a3b8')
    s+=rect(500,620,440,48,C['blue'],11)+text(720,650,'เข้าสู่ระบบ',14,'#fff',800,'middle')
    return s+'</svg>'
def policy_steps():
    s=svg_start(); s+=rect(0,0,W,96,'#0f172a',0)+text(42,57,'CorpNotify',22,'#fff',800)
    for i,(lab,x) in enumerate([('1  ประกาศ',810),('2  นโยบาย',960),('3  แบบทดสอบ',1110)]): s+=rect(x,26,136,42,C['blue'] if i==1 else '#1e293b',10)+text(x+68,53,lab,13,'#fff' if i==1 else '#94a3b8',800,'middle')
    s+=rect(0,96,W,48,'#fff',0)+text(46,126,'กรุณาอ่านนโยบายทั้งหมด แล้วเลื่อนลงด้านล่างสุดเพื่อเปิดใช้งานปุ่มรับทราบ',13,C['muted'])
    s+=panel(120,176,1200,540)+text(156,216,'Policy การใช้งานระบบ IT และ AI อย่างปลอดภัย',24,C['ink'],800)+line(156,238,1284,238)
    body=['1. ดูแลรหัสผ่านของตนเองและห้ามเปิดเผยให้ผู้อื่น','2. ระวังอีเมล ลิงก์ และไฟล์แนบจากแหล่งที่ไม่รู้จัก','3. ห้ามนำข้อมูลลับของบริษัทเข้าสู่ AI โดยไม่ได้รับอนุญาต','4. หากพบเหตุการณ์ผิดปกติให้แจ้งฝ่าย IT ทันที']
    for i,t in enumerate(body): s+=text(160,292+i*66,t,15,'#334155',600)
    s+=rect(0,822,W,78,'#fff',0)+line(0,822,W,822)+text(48,867,'อ่านเนื้อหาให้ครบเพื่อดำเนินการต่อ',12,C['muted'])+rect(1180,840,200,44,'#cbd5e1',10)+text(1280,868,'เลื่อนลงเพื่ออ่านต่อ',13,'#fff',800,'middle')
    return s+'</svg>'
FILES={'01-dashboard.svg':dashboard,'02-notifications.svg':notifications,'03-devices.svg':devices,'04-notification-composer.svg':composer,'05-notification-detail.svg':detail,'06-agent-notification.svg':agent,'07-login.svg':login,'08-policy-step.svg':policy_steps}
for name,fn in FILES.items(): (OUT/name).write_text(fn(),encoding='utf-8')
(OUT/'README_FIGMA_IMPORT.txt').write_text('CorpNotify Figma-import assets\n\nImport each SVG into Figma using File > Place image or drag/drop. SVG objects remain vector/editable.\nSource: current Laravel Blade/CSS and Windows Agent UI inspected on 2026-09-08.\nFiles: '+', '.join(FILES.keys()),encoding='utf-8')
print('created',len(FILES),'SVG files in',OUT)
