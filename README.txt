วิธีติดตั้ง (XAMPP)
1. เปิด XAMPP Control Panel กด Start ที่ Apache และ MySQL
2. คัดลอกโฟลเดอร์ tshirt-shop ไปไว้ที่ C:\xampp\htdocs\
3. เปิด http://localhost/tshirt-shop/install.php  (สร้างฐานข้อมูลและสินค้าตัวอย่าง 30 ชิ้น = 5 หน้า)
4. เข้าเว็บที่ http://localhost/tshirt-shop/
5. ดูฐานข้อมูลที่ http://localhost/phpmyadmin  (ชื่อ tshirt_shop)
หลังบ้าน: http://localhost/tshirt-shop/admin.php  รหัสผ่านเริ่มต้น admin1234 (แก้ใน config.php)
QR ชำระเงิน: วางรูปชื่อ qr.png ไว้ในโฟลเดอร์ assets
ลบสินค้าตัวอย่างได้ในหน้าหลังบ้าน
รหัสผู้ทดลองใช้ ชื่อ sirasit12345678910
            รหัสผ่าน 123456

//phpmyadmin//
หากต้องการเริ่มนับออเดอร์ใหม่ให้เริ่มเริ่มต้นเป็นออเดอร์ที่1 ให้พิมค์ทำสั่งใน SQL ว่า

DELETE FROM order_items;
DELETE FROM orders;
ALTER TABLE order_items AUTO_INCREMENT = 1;
ALTER TABLE orders AUTO_INCREMENT = 1;

หาก GO แล้ว Error ให้เอาติ๊กถูกตรงคำว่า Enable foreign key checks ออก
                    