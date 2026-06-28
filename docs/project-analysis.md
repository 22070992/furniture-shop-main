# Phân tích dự án website cửa hàng nội thất Furemir

## Tên đề tài phù hợp

Xây dựng website thương mại điện tử bán nội thất Furemir bằng Laravel 10, Blade và jQuery.

## Mục tiêu hệ thống

Hệ thống hỗ trợ khách hàng xem, tìm kiếm, lọc sản phẩm nội thất, quản lý giỏ hàng, đặt hàng, theo dõi đơn, gửi phản hồi trực quan; đồng thời cung cấp cho quản trị viên các chức năng quản lý catalog, khách hàng, đơn hàng, thống kê và một số công cụ hỗ trợ AI.

## Đối tượng sử dụng

- Khách truy cập: xem trang chủ, shop, chi tiết sản phẩm, đăng ký và đăng nhập.
- Người dùng đã đăng nhập: quản lý tài khoản, wishlist, compare, cart, checkout, đơn hàng, review sau mua hàng và visual feedback.
- Quản trị viên: quản lý sản phẩm, danh mục, đơn hàng, khách hàng, feedback, review, thống kê, AI catalog quality và cấu hình AI combo.

## Công nghệ sử dụng

- Backend: Laravel 10, PHP 8.2, Eloquent ORM, Blade template.
- Database: MySQL.
- Frontend: Blade, jQuery, Bootstrap/Tabler ở admin, CSS/JS tĩnh trong `public/`.
- Auth/role: Laravel Auth session, Spatie Laravel Permission, cột `is_staff` trong bảng `users`.
- Upload/storage: Laravel Storage cho ảnh review, feedback screenshot/attachment và public assets.
- Thanh toán: COD và module VNPAY Sandbox/Production qua service ký HMAC SHA512.
- AI: AI chatbox, AI dashboard summary, AI catalog checker và AI interior combo với fallback rule-based khi provider không khả dụng.

## Kiến trúc hệ thống

Ứng dụng dùng kiến trúc MVC của Laravel. Route web nhận request, controller xử lý validation/nghiệp vụ, service tách các phần phức tạp như VNPAY, catalog quality, recommendation và AI combo. Dữ liệu được lưu bằng Eloquent/MySQL; giao diện hiển thị bằng Blade kết hợp jQuery cho AJAX, cart localStorage và các widget nổi.

![Sơ đồ kiến trúc](final-report/diagrams/architecture.png)

## Cấu trúc thư mục chính

- `app/Http/Controllers`: controller public, user, admin, payment, feedback, review.
- `app/Models`: model Eloquent như User, Product, Order, Payment, Review, VisualFeedback.
- `app/Services`: service xử lý AI, catalog quality, recommendation, payment.
- `database/migrations`: migration schema hiện có và các phase mở rộng.
- `database/seeders`: seeder catalog, analytics, feedback và dữ liệu demo.
- `resources/views/pages`: giao diện public/user.
- `resources/views/admin`: giao diện quản trị.
- `public/js`, `public/css`: jQuery logic, cart, shop, wishlist/compare, visual feedback, AI widgets.
- `routes/web.php`: route chính của hệ thống.

## Database

- Tổng số bảng hiện tại: 39.
- Sản phẩm: 72 bản ghi, SKU/variant: 216 bản ghi.
- Đơn hàng: 262 bản ghi, chi tiết đơn hàng: 634 bản ghi.
- Người dùng: 71 bản ghi.
- Visual feedback: 8 bản ghi.

![ERD rút gọn](final-report/diagrams/erd-summary.png)

Chi tiết đầy đủ nằm trong `docs/final-report/database-schema.md`.

## Route và phân nhóm

- Public/Auth/Other: 45 route.
- User: 31 route.
- Admin: 115 route.

Một số route chính:

- `GET|HEAD /payment/vnpay/return - vnpay.return`
- `GET|HEAD /payment/vnpay/ipn - vnpay.ipn`
- `POST /feedback - feedback.store`
- `GET|HEAD /feedback/{feedback}/screenshot - feedback.screenshot`
- `GET|HEAD /feedback-attachments/{attachment} - feedback.attachment`
- `GET|HEAD // - user`
- `GET|HEAD /shop - shop`
- `GET|HEAD /collections - collections.index`
- `GET|HEAD /collections/{collection} - collections.show`
- `GET|HEAD /cart - cart`
- `GET|HEAD /compare - compare.index`
- `GET|HEAD /compare/status - compare.status`
- `POST /compare - compare.store`
- `DELETE /compare/{product_id} - compare.destroy`
- `DELETE /compare - compare.clear`
- `POST /wishlist - wishlist.store`
- `DELETE /wishlist/{product_id} - wishlist.destroy`
- `GET|HEAD /checkout - checkout`
- `POST /ai-interior-combo/recommend - ai-interior-combo.recommend`
- `POST /ai-interior-combo/cart-items - ai-interior-combo.cart_items`
- `GET|HEAD /cart/async`
- `POST /checkout - checkout`
- `POST /checkout/vnpay - vnpay.create`
- `GET|HEAD /checkout/{order_id} - checkout.handle`
- `GET|HEAD /myorders - my_orders`
- `GET|HEAD /myorders/{order_id} - my_detailed_order`
- `PATCH /myorders/{order_id}`
- `GET|HEAD /wishlist - wishlist.index`
- `GET|HEAD /admin - admin`
- `GET|HEAD /admin/dashboard/orders-statistic`
- `GET|HEAD /admin/ai/dashboard-summary - admin.ai.dashboard_summary`
- `GET|HEAD /admin/ai/product-content - admin.ai.product_content`
- `GET|HEAD /admin/ai-combo - admin.ai-combo.index`
- `PATCH /admin/ai-combo - admin.ai-combo.update`
- `GET|HEAD /admin/feedback - admin.feedback.index`
- `GET|HEAD /admin/feedback/{feedback} - admin.feedback.show`
- `PATCH /admin/feedback/{feedback} - admin.feedback.update`
- `POST /admin/feedback/{feedback}/comments - admin.feedback.comments.store`
- `DELETE /admin/feedback/{feedback} - admin.feedback.destroy`
- `GET|HEAD /admin/reviews - admin.reviews.index`
- `GET|HEAD /admin/reviews/{review} - admin.reviews.show`
- `PATCH /admin/reviews/{review} - admin.reviews.update`
- `GET|HEAD /admin/catalog-quality - admin.catalog-quality.index`
- `POST /admin/catalog-quality/runs - admin.catalog-quality.runs.store`
- `GET|HEAD /admin/catalog-quality/runs/{run} - admin.catalog-quality.runs.show`
- `PATCH /admin/catalog-quality/issues/{issue} - admin.catalog-quality.issues.update`
- `POST /admin/catalog-quality/issues/{issue}/apply - admin.catalog-quality.issues.apply`
- `POST /admin/catalog-quality/products/{product}/rescan - admin.catalog-quality.products.rescan`
- `GET|HEAD /admin/employee`
- `GET|HEAD /admin/employee/pagination`
- `GET|HEAD /admin/employee/{user_id}`
- `GET|HEAD /admin/employee/{user_id}/details`
- `GET|HEAD /admin/customers`
- `GET|HEAD /admin/customers/{user_id}/details`
- `GET|HEAD /admin/customers/pagination`
- `POST /admin/employee/create`
- `POST /admin/employee/update`
- `GET|HEAD /admin/users/{user_id}/ban`
- `GET|HEAD /admin/users/{user_id}/unban`
- `GET|HEAD /admin/brands - brands.search`
- `GET|HEAD /admin/brands/pagination - brands.search`
- `POST /admin/brands/create - brands.create`
- `PUT /admin/brands/update - brands.edit`
- `DELETE /admin/brands/{brand_id}/delete - brands.delete`
- `GET|HEAD /admin/categories`
- `GET|HEAD /admin/categories/getall`
- `GET|HEAD /admin/categories/{category_id}`
- `POST /admin/categories - categories.create`
- `PATCH /admin/categories/{category_id} - categories.update`
- `DELETE /admin/categories/{category_id} - categories.delete`
- `GET|HEAD /admin/suppliers - suppliers.index`
- `GET|HEAD /admin/suppliers/pagination - suppliers.search`
- `POST /admin/suppliers/create`
- `PUT /admin/suppliers/update - suppliers.edit`
- `DELETE /admin/suppliers/{supplier_id}/delete - suppliers.delete`
- `GET|HEAD /admin/orders`
- `GET|HEAD /admin/orders/search`
- `GET|HEAD /admin/orders/{order_id} - orders.details`
- `GET|HEAD /admin/warranties`
- `GET|HEAD /admin/warranties/search - warranty.search`

## Vai trò và phân quyền

- Bảng `users` dùng khóa chính `user_id`, có các cột `is_staff`, `is_verified`, `is_active`.
- Role hiện có từ Spatie Permission: `Administrators`.
- Route admin nằm dưới prefix `/admin` và yêu cầu quyền admin/staff theo middleware hiện có.

## Chức năng đã phát hiện

### Khách truy cập

- Trang chủ với banner, category nổi bật, sản phẩm nổi bật và các section nội thất.
- Shop: danh sách sản phẩm, tìm kiếm, lọc category/color/tag/giá và sắp xếp.
- Chi tiết sản phẩm: ảnh, SKU, màu, giá, mô tả, tồn kho, material, size, related products, complete the room, recently viewed.
- Room collection landing pages: Living Room, Bedroom, Dining Room, Office.
- Đăng ký, xác thực OTP, đăng nhập, social login route Google/Facebook.

### Người dùng

- Hồ sơ tài khoản, địa chỉ, đổi mật khẩu.
- Giỏ hàng dùng localStorage kết hợp `/cart/async` để đồng bộ giá/tồn kho từ server.
- Checkout COD và VNPAY; đơn hàng, lịch sử đơn hàng và chi tiết đơn.
- Wishlist, Product Compare, Verified Product Reviews sau đơn giao thành công.
- Visual Feedback trực tiếp trên UI và trang `My feedback`.
- AI Chatbox và AI Interior Combo Recommendation trên trang chi tiết sản phẩm.

### Quản trị viên

- Dashboard có thống kê, AI dashboard summary và tổng quan visual feedback.
- Quản lý sản phẩm, SKU/variant, ảnh, màu, danh mục, brand, tag, nhà cung cấp, receipt, warranty, discount.
- Quản lý khách hàng, nhân viên, role, permission, settings.
- Quản lý đơn hàng, cập nhật trạng thái, chi tiết đơn.
- Quản lý verified reviews.
- Quản lý Visual Feedback, xem screenshot, selector, Copy for Codex, Copy Fix Prompt.
- AI Catalog Quality Checker và cấu hình AI Combo.

## Quy tắc nghiệp vụ quan trọng

- User phải đăng nhập và được verified/active mới dùng các chức năng tài khoản.
- Admin được xác định qua `is_staff` và role/middleware.
- Checkout không tin giá từ frontend; backend đối chiếu SKU và tồn kho.
- VNPAY dùng IPN làm nguồn cập nhật thanh toán chính thức; Return URL chỉ hiển thị kết quả.
- Review chỉ hợp lệ khi order thuộc user, đã giao thành công và order detail chưa review.
- Wishlist yêu cầu đăng nhập; compare hỗ trợ session.
- Visual feedback không tin role từ frontend, backend lấy từ user đang xác thực.

## Chức năng chưa hoàn thiện hoặc giới hạn

- Chưa test thanh toán VNPAY sandbox thật vì chưa có `VNPAY_TMN_CODE`, `VNPAY_HASH_SECRET` và public IPN URL.
- Database hiện chưa có approved review demo, nên product detail hiển thị trạng thái chưa có review thật.
- Repository có `vite.config.js` nhưng không có `package.json`, vì vậy không xác định được lệnh build frontend bằng npm trong source hiện tại.
- AI provider/key không được đưa vào báo cáo; các màn hình AI được kiểm thử ở mức UI/fallback, không xác nhận chất lượng phản hồi từ API thật.
- Đăng ký được kiểm tra ở giao diện; bước OTP phụ thuộc mail/log và không nhập OTP trong ảnh để tránh sinh thêm tài khoản demo không cần thiết.
