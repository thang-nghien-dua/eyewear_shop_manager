# BÁO CÁO ĐỒ ÁN MÔN HỌC: PHÂN TÍCH THIẾT KẾ HỆ THỐNG
## HỆ THỐNG QUẢN LÝ VÀ BÁN HÀNG CỬA HÀNG MẮT KÍNH LUMINA

* **Sinh viên thực hiện:** Trương Văn Thắng
* **Môn học:** Phân tích thiết kế hệ thống
* **Công nghệ sử dụng:** PHP (Core), MySQL, Docker Container, HTML5, CSS3, Javascript.

---

## 1. Giới thiệu chung về Dự án
Hệ thống LUMINA Eyeglass Shop là một nền tảng thương mại điện tử chuyên biệt dành cho cửa hàng mắt kính trực tuyến và quản lý cửa hàng nội bộ. Điểm đặc thù của hệ thống là khả năng quản lý và áp dụng hồ sơ khúc xạ (đơn kính thuốc) của khách hàng để cửa hàng có thể cắt kính chính xác theo số đo y tế của từng cá nhân.

---

## 2. Các Tác nhân & Phân quyền Hệ thống (Role-based Access Control)

Hệ thống được thiết kế và phân tích theo 3 nhóm đối tượng sử dụng chính:

### A. Khách hàng (Customer)
* **Đăng ký/Đăng nhập:** Quản lý tài khoản cá nhân.
- **Trang chủ & Cửa hàng:** Xem thông tin, tìm kiếm, xem chi tiết kính mắt và tròng kính.
- **Ví đơn kính thuốc (Prescription Wallet):** Lưu trữ nhiều hồ sơ số đo khúc xạ mắt (Cận thị, Viễn thị, Loạn thị, khoảng cách đồng tử PD,...) để sử dụng nhanh khi đặt mua kính.
- **Giỏ hàng & Thanh toán:** Hỗ trợ tính năng "MUA NGAY" chuyển hướng trực tiếp tới trang thanh toán, lựa chọn thanh toán COD hoặc trực tuyến (tích hợp VNPAY).
- **Quản lý đơn hàng:** Theo dõi lịch sử đơn hàng, xem chi tiết tiến độ giao hàng, thực hiện hủy đơn trực tiếp (nếu chưa thanh toán) hoặc gửi yêu cầu hủy đơn kèm lý do chi tiết sang trang riêng biệt, gửi yêu cầu đổi trả/bảo hành.

### B. Nhân viên vận hành / Bán hàng / Quản lý (Staff Roles: manager, sales, operations)
* **Giao diện làm việc riêng (Staff Area):** Có thanh điều hướng (Sidebar) tối giản được thiết kế riêng biệt để tập trung vào nghiệp vụ xử lý nghiệp vụ hằng ngày.
* **Quản lý đơn hàng:**
  - Tiếp nhận đơn hàng mới, cập nhật trạng thái đơn (Chờ xác nhận, chờ nhập hàng, đang xử lý tròng, đang giao, đã giao, hủy đơn,...).
  - Phê duyệt/từ chối các yêu cầu hủy đơn kèm lý do của khách hàng.
* **Xử lý Đổi trả / Bảo hành:**
  - Tiếp nhận và phê duyệt các yêu cầu đổi trả hoặc bảo hành sản phẩm bị lỗi từ phía khách hàng.

### C. Quản trị viên tối cao (Admin)
- Sở hữu đầy đủ quyền truy cập hệ thống giống như nhân viên.
- **Dashboard trực quan:** Thống kê tổng quan tình trạng cửa hàng theo thời gian thực (Tổng đơn, Đơn cần xử lý, Đơn đang giao, Đơn hoàn tất, Biểu đồ doanh thu 7 ngày gần nhất, Phân bố trạng thái đơn hàng). Các thẻ thống kê hỗ trợ click nhanh để lọc và chuyển hướng.
- **Báo cáo doanh thu & Thống kê:** Báo cáo chi tiết dạng biểu đồ và bảng biểu.
- **Quản lý sản phẩm & Biến thể:** Quản lý kính mắt, tròng kính đi kèm các thuộc tính (Màu sắc, kích thước, số lượng tồn kho).
- **Quản lý danh mục & Tùy chọn tròng kính:** Tổ chức hệ thống danh mục sản phẩm của shop.
- **Quản lý đánh giá:** Quản trị các nhận xét, phản hồi từ khách hàng.
- **Quản lý tài khoản & Phân quyền:** Phân quyền cho nhân viên làm việc trực tiếp (Admin, Manager, Sales, Operations).

---

## 3. Kiến trúc Thư mục Dự án

```text
LUMINA_eyeglass_shop/
├── app/                        # Logic ứng dụng Backend
│   ├── config/                 # Cấu hình Database, App và Cổng thanh toán
│   ├── helpers/                # Các hàm tiện ích dùng chung (functions.php)
│   ├── middleware/             # Bộ kiểm tra và phân quyền đăng nhập (auth.php)
│   └── views/                  # Các phần giao diện dùng chung (Partials)
│       └── partials/           # Header, Footer, Admin Sidebar, Staff Sidebar...
├── database/                   # Cơ sở dữ liệu
│   ├── schema.sql              # Cấu trúc các bảng (users, orders, products, prescriptions...)
│   └── seed.sql                # Dữ liệu thử nghiệm ban đầu
├── docker/                     # Cấu hình container hóa ứng dụng
├── public/                     # Thư mục gốc chạy Web (Public HTML)
│   ├── admin/                  # Giao diện và logic của Admin & Staff
│   │   ├── orders/             # Xử lý đơn hàng
│   │   ├── return-requests/    # Xử lý đổi trả/bảo hành
│   │   ├── products/           # Quản trị sản phẩm (Admin only)
│   │   └── reports/            # Báo cáo thống kê (Admin only)
│   ├── assets/                 # CSS, JS, hình ảnh giao diện
│   ├── checkout.php            # Trang thanh toán
│   ├── cart.php                # Giỏ hàng
│   └── index.php               # Trang chủ
└── docker-compose.yml          # File khởi chạy Docker stack
```

---

## 4. Hướng dẫn Triển khai & Cài đặt

### Yêu cầu hệ thống
* Đã cài đặt [Docker](https://www.docker.com/) và [Docker Compose](https://docs.docker.com/compose/).

### Các bước khởi chạy dự án
1. Khởi chạy toàn bộ hệ thống bằng Docker Compose:
   ```bash
   docker compose up -d --build
   ```
2. Sau khi Docker khởi chạy thành công, truy cập vào các địa chỉ sau:
   - **Trang chủ Website:** [http://localhost:8080](http://localhost:8080)
   - **Trang quản trị cơ sở dữ liệu phpMyAdmin:** [http://localhost:8081](http://localhost:8081)

3. *Lưu ý về Icon Thư viện (Flaticon-uicons):* Để hiển thị đầy đủ icon trực quan như bản thiết kế, bạn cần đặt bộ tài nguyên font `Regular Rounded` của flaticon vào đường dẫn:
   `public/assets/vendor/flaticon-uicons/`
   Cấu trúc thư mục con sau khi chép:
   - `css/uicons-regular-rounded.css`
   - `webfonts/` (chứa các file font `.woff2`, `.ttf`,...)

### Làm sạch và Import lại Cơ sở dữ liệu từ đầu
Nếu muốn xóa toàn bộ dữ liệu hiện tại để import lại sạch sẽ cơ sở dữ liệu mẫu:
```bash
docker compose down -v
docker compose up -d --build
```
Hệ thống sẽ tự động khởi chạy và chạy lại các file SQL khởi tạo trong thư mục `database/` vào MySQL container.
