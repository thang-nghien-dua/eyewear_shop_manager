# LUMINA Recovery Pack

Bộ này gom lại toàn bộ file đã làm tới thời điểm hiện tại cho đồ án shop mắt kính LUMINA.

## Thành phần chính
- Docker: `docker-compose.yml`, `docker/apache/*`
- Config PHP: `app/config/*`
- Helpers: `app/helpers/functions.php`
- Layout: `app/views/partials/*`
- Trang public:
  - `index.php`
  - `products.php`
  - `product-detail.php`
  - `add-to-cart.php`
  - `cart.php`
  - `login.php`
- Database:
  - `database/schema.sql`
  - `database/seed.sql`

## Cách chạy
```bash
docker compose up -d --build
```

Mở:
- Web: `http://localhost:8080`
- phpMyAdmin: `http://localhost:8081`

## Lưu ý icon Flaticon
Bạn cần tự chép bộ `Regular Rounded` vào thư mục:

`public/assets/vendor/flaticon-uicons/`

Cấu trúc:
- `css/uicons-regular-rounded.css`
- `webfonts/...`

## Nếu muốn import lại DB từ đầu
```bash
docker compose down -v
docker compose up -d --build
```
