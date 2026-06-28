# Hướng dẫn cài đặt và chạy dự án

## Môi trường đã kiểm tra

- PHP: 8.2.31
- Composer: 2.10.1
- Framework: Laravel 10
- Database: MySQL (`furniture_shop`)
- Frontend: Blade, jQuery, asset tĩnh trong `public/`
- Test: PHPUnit qua `php artisan test`

## Các bước chạy local

1. Cài dependency PHP:

```bash
composer install
```

2. Kiểm tra file `.env` đã có cấu hình database local. Không commit `.env` lên Git.

3. Chạy migration nếu cần:

```bash
php artisan migrate
```

4. Tạo symbolic link storage nếu chưa có:

```bash
php artisan storage:link
```

5. Xóa cache cấu hình sau khi đổi env:

```bash
php artisan optimize:clear
```

6. Khởi động server:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

7. Mở website:

```text
http://127.0.0.1:8000
```

## Lưu ý frontend

Repository hiện tại có `vite.config.js` nhưng không có `package.json`, vì vậy không có lệnh `npm run build` xác định trong source hiện tại. Các màn hình đã kiểm thử dùng asset tĩnh trong `public/css`, `public/js`, `public/vendor`.

## Tài khoản demo

Xem `docs/test-accounts.md`. Hai tài khoản demo chỉ phục vụ kiểm thử và báo cáo.
