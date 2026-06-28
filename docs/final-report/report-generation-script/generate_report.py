from __future__ import annotations

import json
import math
import shutil
import textwrap
from collections import Counter, defaultdict
from datetime import datetime
from pathlib import Path

from docx import Document
from docx.enum.section import WD_SECTION
from docx.enum.table import WD_ALIGN_VERTICAL, WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Inches, Pt, RGBColor
from PIL import Image, ImageDraw, ImageFont
from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_JUSTIFY, TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import cm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import Image as PdfImage
from reportlab.platypus import PageBreak, Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle


ROOT = Path(__file__).resolve().parents[3]
DOCS = ROOT / "docs"
FINAL = DOCS / "final-report"
DATA = FINAL / "data"
SCREENSHOTS = FINAL / "screenshots"
DIAGRAMS = FINAL / "diagrams"

PROJECT_JSON = DATA / "project-data.json"
TEST_JSON = DATA / "functional-tests.json"

REPORT_MD = FINAL / "Bao_cao_do_an_website_noi_that.md"
REPORT_DOCX = FINAL / "Bao_cao_do_an_website_noi_that.docx"
REPORT_PDF = FINAL / "Bao_cao_do_an_website_noi_that.pdf"
PROJECT_ANALYSIS = DOCS / "project-analysis.md"
FINAL_PROJECT_ANALYSIS = FINAL / "project-analysis.md"
FUNCTIONAL_TESTS = DOCS / "functional-test-results.md"
FINAL_FUNCTIONAL_TESTS = FINAL / "functional-test-results.md"
DATABASE_SCHEMA = FINAL / "database-schema.md"
INSTALLATION_GUIDE = FINAL / "installation-guide.md"
TEST_ACCOUNTS = DOCS / "test-accounts.md"
NOTES = FINAL / "generation-notes.md"


def read_json(path: Path):
    return json.loads(path.read_text(encoding="utf-8"))


project = read_json(PROJECT_JSON)
tests = read_json(TEST_JSON)
schema = project["schema"]
routes = project["routes"]
tables = project["tables"]


def table_count(name: str) -> int:
    return int(schema.get(name, {}).get("count", 0))


def route_lines(keyword: str) -> list[str]:
    return [
        f"{r['method']} /{r['uri']}" + (f" ({r['name']})" if r.get("name") else "")
        for r in routes
        if keyword in r["uri"] or keyword in (r.get("action") or "")
    ]


def main_routes() -> list[str]:
    keep = [
        "",
        "shop",
        "products/{identifier}",
        "cart",
        "checkout",
        "myorders",
        "wishlist",
        "compare",
        "collections",
        "feedback",
        "admin",
        "payment/vnpay",
        "ai-interior-combo",
    ]
    selected: list[str] = []
    for route in routes:
        uri = route["uri"]
        if uri == "/" or any(uri.startswith(k) for k in keep if k):
            selected.append(
                f"{route['method']} /{uri}"
                + (f" - {route['name']}" if route.get("name") else "")
            )
    return selected


def migration_files() -> list[str]:
    return sorted(str(p.relative_to(ROOT)) for p in (ROOT / "database/migrations").glob("*.php"))


def screenshot_exists(name: str) -> bool:
    return bool(name) and (SCREENSHOTS / name).exists()


def safe(text: object) -> str:
    if text is None:
        return ""
    return str(text).replace("|", "\\|").replace("\n", " ").strip()


def write_text(path: Path, content: str):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content.strip() + "\n", encoding="utf-8")


def draw_box(draw, xy, title, body="", fill="#F7F9FC", outline="#31465F"):
    x1, y1, x2, y2 = xy
    draw.rounded_rectangle(xy, radius=16, fill=fill, outline=outline, width=2)
    draw.text((x1 + 18, y1 + 16), title, fill="#111827", font=FONT_BOLD)
    if body:
        lines = textwrap.wrap(body, width=max(18, int((x2 - x1) / 16)))
        y = y1 + 50
        for line in lines[:4]:
            draw.text((x1 + 18, y), line, fill="#374151", font=FONT_REG)
            y += 24


def arrow(draw, start, end, color="#4B5563"):
    draw.line([start, end], fill=color, width=3)
    sx, sy = start
    ex, ey = end
    angle = math.atan2(ey - sy, ex - sx)
    size = 10
    points = [
        (ex, ey),
        (ex - size * math.cos(angle - 0.5), ey - size * math.sin(angle - 0.5)),
        (ex - size * math.cos(angle + 0.5), ey - size * math.sin(angle + 0.5)),
    ]
    draw.polygon(points, fill=color)


def load_font(size: int, bold: bool = False):
    candidates = [
        "/System/Library/Fonts/Supplemental/Times New Roman Bold.ttf" if bold else "/System/Library/Fonts/Supplemental/Times New Roman.ttf",
        "/System/Library/Fonts/Supplemental/Arial Bold.ttf" if bold else "/System/Library/Fonts/Supplemental/Arial.ttf",
        "/Library/Fonts/Arial Bold.ttf" if bold else "/Library/Fonts/Arial.ttf",
    ]
    for candidate in candidates:
        path = Path(candidate)
        if path.exists():
            return ImageFont.truetype(str(path), size)
    return ImageFont.load_default()


FONT_REG = load_font(24)
FONT_BOLD = load_font(28, True)
FONT_TITLE = load_font(36, True)


def create_diagrams():
    DIAGRAMS.mkdir(parents=True, exist_ok=True)

    img = Image.new("RGB", (1600, 900), "white")
    draw = ImageDraw.Draw(img)
    draw.text((60, 40), "Kiến trúc tổng quan hệ thống Furemir", fill="#111827", font=FONT_TITLE)
    boxes = {
        "browser": (80, 170, 400, 320),
        "laravel": (520, 170, 880, 320),
        "controllers": (1000, 170, 1400, 320),
        "models": (520, 470, 880, 620),
        "mysql": (1000, 470, 1400, 620),
        "storage": (80, 470, 400, 620),
    }
    draw_box(draw, boxes["browser"], "Blade + jQuery", "Trang người dùng, admin, AJAX, localStorage cart, screenshot feedback.")
    draw_box(draw, boxes["laravel"], "Laravel 10", "Routing, middleware auth, validation, service classes.")
    draw_box(draw, boxes["controllers"], "Controllers / Services", "Catalog, checkout, payment, review, feedback, AI modules.")
    draw_box(draw, boxes["models"], "Eloquent Models", "User, Product, SKU, Order, Review, Feedback, Payment.")
    draw_box(draw, boxes["mysql"], "MySQL furniture_shop", f"{len(tables)} bảng, quan hệ khóa ngoại và dữ liệu nghiệp vụ.")
    draw_box(draw, boxes["storage"], "Storage/Public assets", "Ảnh sản phẩm, screenshot feedback, file upload.")
    arrow(draw, (400, 245), (520, 245))
    arrow(draw, (880, 245), (1000, 245))
    arrow(draw, (1180, 320), (1180, 470))
    arrow(draw, (1000, 545), (880, 545))
    arrow(draw, (520, 545), (400, 545))
    arrow(draw, (700, 470), (700, 320))
    img.save(DIAGRAMS / "architecture.png")

    img = Image.new("RGB", (1600, 1000), "white")
    draw = ImageDraw.Draw(img)
    draw.text((60, 40), "ERD rút gọn các quan hệ chính", fill="#111827", font=FONT_TITLE)
    erd_boxes = {
        "users": (70, 140, 340, 250),
        "address": (70, 330, 340, 430),
        "orders": (460, 140, 740, 250),
        "details": (860, 140, 1160, 250),
        "payments": (460, 330, 740, 430),
        "reviews": (860, 330, 1160, 430),
        "products": (1240, 140, 1530, 250),
        "skus": (1240, 330, 1530, 430),
        "images": (1240, 520, 1530, 620),
        "feedback": (70, 560, 430, 680),
        "comments": (520, 560, 860, 680),
        "attachments": (940, 560, 1280, 680),
        "lookup": (520, 760, 960, 880),
    }
    labels = {
        "users": "users\nPK user_id",
        "address": "address\nFK user_id",
        "orders": "orders\nPK order_id, FK customer_id",
        "details": "order_details\nFK order_id, sku",
        "payments": "payments\nFK order_id",
        "reviews": "reviews / review_images\nFK user_id, product_id, order_detail_id",
        "products": "products\nPK product_id, FK category_id",
        "skus": "product_details\nPK sku, FK product_id/color_id",
        "images": "product_images\nFK sku",
        "feedback": "visual_feedbacks\nFK user_id, assigned_to",
        "comments": "visual_feedback_comments\nFK feedback_id/user_id",
        "attachments": "visual_feedback_attachments\nFK feedback_id/user_id",
        "lookup": "categories, colors, tags, brands,\ndiscounts, warranties, suppliers",
    }
    for key, xy in erd_boxes.items():
        title, *body = labels[key].split("\n")
        draw_box(draw, xy, title, "\n".join(body), "#F8FAFC")
    for a, b in [
        ("users", "orders"), ("users", "address"), ("orders", "details"),
        ("orders", "payments"), ("details", "reviews"), ("products", "details"),
        ("products", "skus"), ("skus", "images"), ("users", "feedback"),
        ("feedback", "comments"), ("feedback", "attachments"), ("lookup", "products"),
    ]:
        ax = (erd_boxes[a][2], (erd_boxes[a][1] + erd_boxes[a][3]) // 2)
        bx = (erd_boxes[b][0], (erd_boxes[b][1] + erd_boxes[b][3]) // 2)
        if a == "lookup":
            ax = ((erd_boxes[a][0] + erd_boxes[a][2]) // 2, erd_boxes[a][1])
            bx = ((erd_boxes[b][0] + erd_boxes[b][2]) // 2, erd_boxes[b][3])
        arrow(draw, ax, bx)
    img.save(DIAGRAMS / "erd-summary.png")

    img = Image.new("RGB", (1600, 900), "white")
    draw = ImageDraw.Draw(img)
    draw.text((60, 40), "Luồng xử lý checkout COD/VNPAY", fill="#111827", font=FONT_TITLE)
    steps = [
        ("Giỏ hàng", "User chọn sản phẩm và số lượng"),
        ("Checkout", "Server validate địa chỉ, SKU, tồn kho, giá"),
        ("COD", "Tạo order unpaid/pending payment"),
        ("VNPAY", "Tạo payment pending và URL có chữ ký"),
        ("Return/IPN", "Return hiển thị kết quả, IPN xác minh chính thức"),
        ("Orders", "Cập nhật payment_status, paid_at nếu giao dịch hợp lệ"),
    ]
    x = 70
    y = 220
    previous = None
    for i, (title, body) in enumerate(steps):
        xy = (x, y, x + 220, y + 160)
        draw_box(draw, xy, title, body, "#F7FBF4")
        if previous:
            arrow(draw, (previous[2], (previous[1] + previous[3]) // 2), (xy[0], (xy[1] + xy[3]) // 2))
        previous = xy
        x += 250
        if i == 2:
            x = 570
            y = 510
            previous = None
    img.save(DIAGRAMS / "checkout-flow.png")


def schema_markdown() -> str:
    lines = [
        "# Cấu trúc database",
        "",
        f"- Database: `{project['app']['database_name']}`",
        f"- Kết nối: `{project['app']['database_connection']}`",
        f"- Tổng số bảng: {len(tables)}",
        "",
        "## Danh sách bảng",
        "",
        "| Bảng | Số dòng | Khóa chính/cột chính | Khóa ngoại |",
        "|---|---:|---|---|",
    ]
    for table in tables:
        info = schema[table]
        columns = info["columns"]
        primary = [c["COLUMN_NAME"] for c in columns if c.get("COLUMN_KEY") == "PRI"]
        fks = [
            f"{fk['COLUMN_NAME']} → {fk['REFERENCED_TABLE_NAME']}.{fk['REFERENCED_COLUMN_NAME']}"
            for fk in info["foreign_keys"]
        ]
        lines.append(
            f"| `{table}` | {info.get('count', 0)} | {safe(', '.join(primary) or columns[0]['COLUMN_NAME'])} | {safe(', '.join(fks) or 'Không có')} |"
        )
    lines.extend(["", "## Chi tiết cột theo bảng", ""])
    for table in tables:
        lines.extend([f"### `{table}`", "", "| Cột | Kiểu | Null | Key | Default | Extra |", "|---|---|---|---|---|---|"])
        for col in schema[table]["columns"]:
            lines.append(
                f"| `{col['COLUMN_NAME']}` | `{safe(col['COLUMN_TYPE'])}` | {col['IS_NULLABLE']} | {col['COLUMN_KEY'] or ''} | {safe(col['COLUMN_DEFAULT'])} | {safe(col['EXTRA'])} |"
            )
        lines.append("")
    return "\n".join(lines)


def tests_markdown() -> str:
    pass_count = sum(1 for t in tests if t["status"] == "Pass")
    fail_count = len(tests) - pass_count
    lines = [
        "# Kết quả kiểm thử chức năng",
        "",
        f"- Tổng số test: {len(tests)}",
        f"- Pass: {pass_count}",
        f"- Fail: {fail_count}",
        f"- Viewport: Chromium desktop 1440x900",
        "",
        "| Mã test | Chức năng | Bước thực hiện | Dữ liệu kiểm thử | Kết quả mong đợi | Kết quả thực tế | Trạng thái | Ảnh | Ghi chú |",
        "|---|---|---|---|---|---|---|---|---|",
    ]
    for t in tests:
        lines.append(
            f"| {safe(t['id'])} | {safe(t['feature'])} | {safe(t['steps'])} | {safe(t['test_data'])} | {safe(t['expected'])} | {safe(t['actual'])} | {safe(t['status'])} | {safe(t['screenshot'])} | {safe(t.get('notes', ''))} |"
        )
    return "\n".join(lines)


def installation_markdown() -> str:
    return f"""
# Hướng dẫn cài đặt và chạy dự án

## Môi trường đã kiểm tra

- PHP: 8.2.31
- Composer: 2.10.1
- Framework: Laravel 10
- Database: MySQL (`{project['app']['database_name']}`)
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
"""


def project_analysis_markdown() -> str:
    route_summary = Counter()
    for r in routes:
        uri = r["uri"]
        if uri.startswith("admin"):
            route_summary["Admin"] += 1
        elif uri.startswith(("checkout", "cart", "myorders", "account", "wishlist", "compare", "reviews", "feedback", "my-feedback")):
            route_summary["User"] += 1
        else:
            route_summary["Public/Auth/Other"] += 1
    lines = [
        "# Phân tích dự án website cửa hàng nội thất Furemir",
        "",
        "## Tên đề tài phù hợp",
        "",
        "Xây dựng website thương mại điện tử bán nội thất Furemir bằng Laravel 10, Blade và jQuery.",
        "",
        "## Mục tiêu hệ thống",
        "",
        "Hệ thống hỗ trợ khách hàng xem, tìm kiếm, lọc sản phẩm nội thất, quản lý giỏ hàng, đặt hàng, theo dõi đơn, gửi phản hồi trực quan; đồng thời cung cấp cho quản trị viên các chức năng quản lý catalog, khách hàng, đơn hàng, thống kê và một số công cụ hỗ trợ AI.",
        "",
        "## Đối tượng sử dụng",
        "",
        "- Khách truy cập: xem trang chủ, shop, chi tiết sản phẩm, đăng ký và đăng nhập.",
        "- Người dùng đã đăng nhập: quản lý tài khoản, wishlist, compare, cart, checkout, đơn hàng, review sau mua hàng và visual feedback.",
        "- Quản trị viên: quản lý sản phẩm, danh mục, đơn hàng, khách hàng, feedback, review, thống kê, AI catalog quality và cấu hình AI combo.",
        "",
        "## Công nghệ sử dụng",
        "",
        "- Backend: Laravel 10, PHP 8.2, Eloquent ORM, Blade template.",
        "- Database: MySQL.",
        "- Frontend: Blade, jQuery, Bootstrap/Tabler ở admin, CSS/JS tĩnh trong `public/`.",
        "- Auth/role: Laravel Auth session, Spatie Laravel Permission, cột `is_staff` trong bảng `users`.",
        "- Upload/storage: Laravel Storage cho ảnh review, feedback screenshot/attachment và public assets.",
        "- Thanh toán: COD và module VNPAY Sandbox/Production qua service ký HMAC SHA512.",
        "- AI: AI chatbox, AI dashboard summary, AI catalog checker và AI interior combo với fallback rule-based khi provider không khả dụng.",
        "",
        "## Kiến trúc hệ thống",
        "",
        "Ứng dụng dùng kiến trúc MVC của Laravel. Route web nhận request, controller xử lý validation/nghiệp vụ, service tách các phần phức tạp như VNPAY, catalog quality, recommendation và AI combo. Dữ liệu được lưu bằng Eloquent/MySQL; giao diện hiển thị bằng Blade kết hợp jQuery cho AJAX, cart localStorage và các widget nổi.",
        "",
        "![Sơ đồ kiến trúc](final-report/diagrams/architecture.png)",
        "",
        "## Cấu trúc thư mục chính",
        "",
        "- `app/Http/Controllers`: controller public, user, admin, payment, feedback, review.",
        "- `app/Models`: model Eloquent như User, Product, Order, Payment, Review, VisualFeedback.",
        "- `app/Services`: service xử lý AI, catalog quality, recommendation, payment.",
        "- `database/migrations`: migration schema hiện có và các phase mở rộng.",
        "- `database/seeders`: seeder catalog, analytics, feedback và dữ liệu demo.",
        "- `resources/views/pages`: giao diện public/user.",
        "- `resources/views/admin`: giao diện quản trị.",
        "- `public/js`, `public/css`: jQuery logic, cart, shop, wishlist/compare, visual feedback, AI widgets.",
        "- `routes/web.php`: route chính của hệ thống.",
        "",
        "## Database",
        "",
        f"- Tổng số bảng hiện tại: {len(tables)}.",
        f"- Sản phẩm: {table_count('products')} bản ghi, SKU/variant: {table_count('product_details')} bản ghi.",
        f"- Đơn hàng: {table_count('orders')} bản ghi, chi tiết đơn hàng: {table_count('order_details')} bản ghi.",
        f"- Người dùng: {table_count('users')} bản ghi.",
        f"- Visual feedback: {table_count('visual_feedbacks')} bản ghi.",
        "",
        "![ERD rút gọn](final-report/diagrams/erd-summary.png)",
        "",
        "Chi tiết đầy đủ nằm trong `docs/final-report/database-schema.md`.",
        "",
        "## Route và phân nhóm",
        "",
        f"- Public/Auth/Other: {route_summary['Public/Auth/Other']} route.",
        f"- User: {route_summary['User']} route.",
        f"- Admin: {route_summary['Admin']} route.",
        "",
        "Một số route chính:",
        "",
    ]
    for line in main_routes()[:80]:
        lines.append(f"- `{line}`")
    lines.extend([
        "",
        "## Vai trò và phân quyền",
        "",
        "- Bảng `users` dùng khóa chính `user_id`, có các cột `is_staff`, `is_verified`, `is_active`.",
        "- Role hiện có từ Spatie Permission: `Administrators`.",
        "- Route admin nằm dưới prefix `/admin` và yêu cầu quyền admin/staff theo middleware hiện có.",
        "",
        "## Chức năng đã phát hiện",
        "",
        "### Khách truy cập",
        "",
        "- Trang chủ với banner, category nổi bật, sản phẩm nổi bật và các section nội thất.",
        "- Shop: danh sách sản phẩm, tìm kiếm, lọc category/color/tag/giá và sắp xếp.",
        "- Chi tiết sản phẩm: ảnh, SKU, màu, giá, mô tả, tồn kho, material, size, related products, complete the room, recently viewed.",
        "- Room collection landing pages: Living Room, Bedroom, Dining Room, Office.",
        "- Đăng ký, xác thực OTP, đăng nhập, social login route Google/Facebook.",
        "",
        "### Người dùng",
        "",
        "- Hồ sơ tài khoản, địa chỉ, đổi mật khẩu.",
        "- Giỏ hàng dùng localStorage kết hợp `/cart/async` để đồng bộ giá/tồn kho từ server.",
        "- Checkout COD và VNPAY; đơn hàng, lịch sử đơn hàng và chi tiết đơn.",
        "- Wishlist, Product Compare, Verified Product Reviews sau đơn giao thành công.",
        "- Visual Feedback trực tiếp trên UI và trang `My feedback`.",
        "- AI Chatbox và AI Interior Combo Recommendation trên trang chi tiết sản phẩm.",
        "",
        "### Quản trị viên",
        "",
        "- Dashboard có thống kê, AI dashboard summary và tổng quan visual feedback.",
        "- Quản lý sản phẩm, SKU/variant, ảnh, màu, danh mục, brand, tag, nhà cung cấp, receipt, warranty, discount.",
        "- Quản lý khách hàng, nhân viên, role, permission, settings.",
        "- Quản lý đơn hàng, cập nhật trạng thái, chi tiết đơn.",
        "- Quản lý verified reviews.",
        "- Quản lý Visual Feedback, xem screenshot, selector, Copy for Codex, Copy Fix Prompt.",
        "- AI Catalog Quality Checker và cấu hình AI Combo.",
        "",
        "## Quy tắc nghiệp vụ quan trọng",
        "",
        "- User phải đăng nhập và được verified/active mới dùng các chức năng tài khoản.",
        "- Admin được xác định qua `is_staff` và role/middleware.",
        "- Checkout không tin giá từ frontend; backend đối chiếu SKU và tồn kho.",
        "- VNPAY dùng IPN làm nguồn cập nhật thanh toán chính thức; Return URL chỉ hiển thị kết quả.",
        "- Review chỉ hợp lệ khi order thuộc user, đã giao thành công và order detail chưa review.",
        "- Wishlist yêu cầu đăng nhập; compare hỗ trợ session.",
        "- Visual feedback không tin role từ frontend, backend lấy từ user đang xác thực.",
        "",
        "## Chức năng chưa hoàn thiện hoặc giới hạn",
        "",
        "- Chưa test thanh toán VNPAY sandbox thật vì chưa có `VNPAY_TMN_CODE`, `VNPAY_HASH_SECRET` và public IPN URL.",
        "- Database hiện chưa có approved review demo, nên product detail hiển thị trạng thái chưa có review thật.",
        "- Repository có `vite.config.js` nhưng không có `package.json`, vì vậy không xác định được lệnh build frontend bằng npm trong source hiện tại.",
        "- AI provider/key không được đưa vào báo cáo; các màn hình AI được kiểm thử ở mức UI/fallback, không xác nhận chất lượng phản hồi từ API thật.",
        "- Đăng ký được kiểm tra ở giao diện; bước OTP phụ thuộc mail/log và không nhập OTP trong ảnh để tránh sinh thêm tài khoản demo không cần thiết.",
    ])
    return "\n".join(lines)


def report_markdown() -> str:
    pass_count = sum(1 for t in tests if t["status"] == "Pass")
    fail_count = len(tests) - pass_count
    figures = [t for t in tests if screenshot_exists(t.get("screenshot", ""))]
    lines = [
        "# BÁO CÁO ĐỒ ÁN WEBSITE CỬA HÀNG NỘI THẤT FUREMIR",
        "",
        "**Tên trường:** [Tên trường]",
        "",
        "**Tên khoa:** [Tên khoa]",
        "",
        "**Tên đề tài:** Xây dựng website thương mại điện tử bán nội thất Furemir bằng Laravel",
        "",
        "**Sinh viên thực hiện:** Đỗ Quang Hưng",
        "",
        "**Mã sinh viên:** [Mã sinh viên]",
        "",
        "**Giảng viên hướng dẫn:** [Giảng viên hướng dẫn]",
        "",
        "**Địa điểm và năm thực hiện:** [Địa điểm], 2026",
        "",
        "## Lời cảm ơn",
        "",
        "Em xin gửi lời cảm ơn đến giảng viên hướng dẫn và các thầy cô đã hỗ trợ trong quá trình học tập, định hướng phân tích yêu cầu và hoàn thiện đồ án. Em cũng cảm ơn gia đình, bạn bè đã động viên để em có điều kiện tập trung xây dựng, kiểm thử và hoàn thiện hệ thống website cửa hàng nội thất.",
        "",
        "## Lời cam đoan",
        "",
        "Em cam đoan nội dung báo cáo được xây dựng dựa trên mã nguồn, cơ sở dữ liệu và kết quả kiểm thử thực tế của project hiện tại. Những phần dữ liệu demo, tài khoản demo hoặc giới hạn kỹ thuật đều được ghi rõ trong báo cáo; em không cố ý mô tả chức năng không tồn tại hoặc chưa được kiểm chứng.",
        "",
        "## Tóm tắt đề tài",
        "",
        "Đề tài tập trung xây dựng website thương mại điện tử bán nội thất Furemir trên nền tảng Laravel 10, Blade và jQuery. Hệ thống cho phép khách truy cập xem sản phẩm, tìm kiếm, lọc theo danh mục, màu sắc, giá, xem chi tiết SKU/biến thể, tham khảo sản phẩm liên quan và landing page theo phòng. Người dùng đã đăng nhập có thể quản lý tài khoản, địa chỉ, wishlist, so sánh sản phẩm, thêm sản phẩm vào giỏ hàng, đặt hàng COD hoặc VNPAY, theo dõi lịch sử đơn hàng và gửi visual feedback trực tiếp trên giao diện. Phía quản trị viên cung cấp dashboard, quản lý catalog, danh mục, màu, khách hàng, đơn hàng, review, feedback, thống kê, discount và các công cụ hỗ trợ AI như admin summary, catalog quality checker và cấu hình AI combo. Báo cáo này được tạo từ việc phân tích trực tiếp route, controller, model, migration, view, database và kiểm thử bằng trình duyệt trên localhost. Kết quả kiểm thử ghi nhận {pass_count}/{len(tests)} test pass, {fail_count} fail trong phạm vi các chức năng đã kiểm tra. Một số giới hạn còn tồn tại gồm chưa kiểm thử thanh toán VNPAY sandbox thật do thiếu credential và public IPN URL, chưa có dữ liệu review approved mẫu, và frontend hiện không có `package.json` để xác định quy trình build npm.",
        "",
        "## Mục lục",
        "",
        "[Mục lục tự động được tạo trong file DOCX. Trong Microsoft Word, chọn Update Field nếu cần cập nhật số trang.]",
        "",
        "## Danh mục hình ảnh",
        "",
    ]
    for i, t in enumerate(figures, 1):
        lines.append(f"- Hình {i}: {t['feature']} ({t['screenshot']})")
    lines.extend([
        "",
        "## Danh mục bảng biểu",
        "",
        "- Bảng 1: Thống kê công nghệ và môi trường.",
        "- Bảng 2: Danh sách bảng dữ liệu chính.",
        "- Bảng 3: Kết quả kiểm thử chức năng.",
        "",
        "# Chương 1: Tổng quan đề tài",
        "",
        "## Lý do chọn đề tài",
        "",
        "Nội thất là nhóm sản phẩm có giá trị cao, nhiều biến thể về màu sắc, chất liệu, kích thước và nhu cầu tư vấn. Một website thương mại điện tử cho cửa hàng nội thất cần hỗ trợ người mua quan sát sản phẩm, so sánh, lưu sản phẩm yêu thích, đặt hàng và theo dõi đơn thuận tiện. Đồng thời, quản trị viên cần công cụ quản lý catalog, đơn hàng, phản hồi và chất lượng dữ liệu sản phẩm.",
        "",
        "## Bài toán thực tế",
        "",
        "Bài toán đặt ra là xây dựng một hệ thống bán nội thất có giao diện thân thiện, dữ liệu sản phẩm rõ ràng, hỗ trợ quy trình mua hàng và cung cấp công cụ quản trị đủ dùng cho hoạt động vận hành. Hệ thống cũng cần có khả năng mở rộng cho thanh toán online, đánh giá sản phẩm thật, feedback trực quan và các gợi ý sản phẩm theo phòng.",
        "",
        "## Mục tiêu",
        "",
        "- Xây dựng website bán nội thất có catalog, shop, cart, checkout và order.",
        "- Cung cấp trang quản trị cho sản phẩm, đơn hàng, người dùng, feedback, review và thống kê.",
        "- Bổ sung các chức năng nâng cao như wishlist, compare, visual feedback, verified reviews, room collection, AI combo và VNPAY.",
        "- Kiểm thử giao diện trên localhost và tạo báo cáo có ảnh minh họa.",
        "",
        "## Đối tượng sử dụng",
        "",
        "Hệ thống phục vụ khách truy cập, người dùng đăng ký tài khoản và quản trị viên cửa hàng.",
        "",
        "## Phạm vi",
        "",
        "Báo cáo ghi nhận các chức năng thực sự có trong source code và được kiểm thử trên localhost. Các tích hợp cần credential thật như VNPAY sandbox hoặc AI provider được mô tả ở mức kiến trúc và màn hình có sẵn, không khẳng định đã xử lý giao dịch thật.",
        "",
        "# Chương 2: Cơ sở lý thuyết và công nghệ",
        "",
        "## Laravel 10",
        "",
        "Laravel là framework PHP theo mô hình MVC. Trong dự án, Laravel xử lý route, controller, request validation, session authentication, Eloquent ORM, migration, storage và test bằng PHPUnit.",
        "",
        "## Blade và jQuery",
        "",
        "Blade được dùng để render giao diện server-side. jQuery xử lý AJAX, cart localStorage, shop filter, wishlist/compare, visual feedback và widget AI. Dự án không chuyển sang React/Vue/SPA.",
        "",
        "## MySQL và Eloquent ORM",
        "",
        "MySQL lưu người dùng, sản phẩm, SKU, đơn hàng, payment, review, feedback, discount và dữ liệu quản trị. Eloquent model khai báo relationship và hỗ trợ truy vấn theo nghiệp vụ.",
        "",
        "## Spatie Laravel Permission",
        "",
        "Package này cung cấp bảng role/permission. Project hiện có role `Administrators` và kết hợp cột `is_staff` để phân quyền admin.",
        "",
        "## VNPAY",
        "",
        "Module VNPAY được triển khai theo hướng tách service ký URL thanh toán, verify callback, xử lý Return URL và IPN idempotent. COD được giữ nguyên.",
        "",
        "# Chương 3: Phân tích và thiết kế hệ thống",
        "",
        "## Yêu cầu chức năng",
        "",
        "Các yêu cầu chức năng gồm xem sản phẩm, tìm kiếm/lọc/sắp xếp, chi tiết sản phẩm theo SKU, cart/checkout, đơn hàng, wishlist, compare, review sau mua hàng, visual feedback, quản trị catalog, order, customer, review, feedback, thống kê và các công cụ AI hỗ trợ.",
        "",
        "## Yêu cầu phi chức năng",
        "",
        "- Bảo mật thông qua authentication, authorization, CSRF và validation backend.",
        "- Giao diện responsive cho desktop, tablet và mobile.",
        "- Không tin giá, tồn kho hoặc role gửi từ frontend.",
        "- Dữ liệu thanh toán online phải được xác minh chữ ký và amount.",
        "- Không lưu secret/API key trong source hoặc báo cáo.",
        "",
        "## Tác nhân",
        "",
        "- Khách truy cập.",
        "- Người dùng đã đăng nhập.",
        "- Quản trị viên.",
        "- Cổng thanh toán VNPAY qua Return/IPN.",
        "",
        "## Kiến trúc hệ thống",
        "",
        "![Hình kiến trúc](diagrams/architecture.png)",
        "",
        "## Thiết kế database",
        "",
        "![Hình ERD](diagrams/erd-summary.png)",
        "",
        f"Hệ thống hiện có {len(tables)} bảng. Các cụm bảng quan trọng gồm `users/address`, `products/product_details/product_images/categories/colors/tags`, `orders/order_details/payments`, `reviews/review_images`, `visual_feedbacks/comments/attachments/activities`, `wishlist_items`, `room_collections` và các bảng quản trị khác.",
        "",
        "# Chương 4: Xây dựng hệ thống",
        "",
        "## Môi trường phát triển",
        "",
        "| Thành phần | Phiên bản/Ghi chú |",
        "|---|---|",
        "| PHP | 8.2.31 |",
        "| Laravel | 10.x |",
        "| Database | MySQL |",
        "| Frontend | Blade + jQuery |",
        "| Test | PHPUnit, php artisan test |",
        "| Server local | php artisan serve --host=127.0.0.1 --port=8000 |",
        "",
        "## Cấu trúc source code",
        "",
        "Source code được tổ chức theo chuẩn Laravel: `app/Http/Controllers`, `app/Models`, `app/Services`, `resources/views`, `public/js`, `public/css`, `database/migrations` và `routes/web.php`.",
        "",
        "## Luồng checkout",
        "",
        "![Luồng checkout](diagrams/checkout-flow.png)",
        "",
        "## Đoạn code tiêu biểu",
        "",
        "Các đoạn code tiêu biểu nằm trong source thực tế: `app/Services/Payments/VnpayService.php` xử lý chữ ký VNPAY, `public/js/cart.js` xử lý cart/checkout bằng jQuery, `public/js/visual_feedback.js` thu thập selector/screenshot, và các controller admin quản lý dữ liệu.",
        "",
        "# Chương 5: Giao diện và kết quả thực hiện",
        "",
    ])
    for i, t in enumerate(figures, 1):
        lines.extend([
            f"## Hình {i}: {t['feature']}",
            "",
            f"- Mục đích: minh họa chức năng {t['feature']}.",
            f"- Cách sử dụng/kiểm thử: {t['steps']}",
            f"- Kết quả đạt được: {t['actual']}",
            "",
            f"![Hình {i}: {t['feature']}](screenshots/{t['screenshot']})",
            "",
            f"*Hình {i}: {t['feature']}*",
            "",
        ])
    lines.extend([
        "# Chương 6: Kiểm thử hệ thống",
        "",
        "## Phương pháp kiểm thử",
        "",
        "Kiểm thử được thực hiện bằng trình duyệt Chromium trong Codex desktop, viewport 1440x900, đi qua các route và thao tác người dùng thật. Mỗi test có ảnh minh họa sau khi trang tải xong.",
        "",
        f"## Kết quả tổng hợp",
        "",
        f"- Tổng test: {len(tests)}.",
        f"- Pass: {pass_count}.",
        f"- Fail: {fail_count}.",
        "",
        "Bảng test case đầy đủ nằm trong `functional-test-results.md` và phụ lục của báo cáo.",
        "",
        "## Các lỗi/giới hạn phát hiện",
        "",
        "- Không kiểm thử giao dịch VNPAY thật do thiếu credential và URL public cho IPN.",
        "- Không có approved review mẫu nên product detail không hiển thị rating thật.",
        "- Không có `package.json`, vì vậy không chạy được build frontend npm/Vite trong project hiện tại.",
        "- Các tính năng AI được kiểm tra giao diện/fallback, chưa xác nhận phản hồi API thật.",
        "",
        "# Chương 7: Kết luận và hướng phát triển",
        "",
        "## Kết quả đạt được",
        "",
        "Project đã có website bán nội thất tương đối đầy đủ: catalog, product detail, cart, checkout, order, admin dashboard, quản lý dữ liệu, review, visual feedback, wishlist, compare, SEO room collections, VNPAY module và các chức năng AI hỗ trợ.",
        "",
        "## Điểm mạnh",
        "",
        "- Kiến trúc Laravel quen thuộc, dễ bảo trì.",
        "- Dữ liệu sản phẩm có SKU/màu/ảnh, phù hợp sản phẩm nội thất.",
        "- Admin có nhiều màn hình quản lý và công cụ kiểm tra chất lượng.",
        "- Visual Feedback giúp người dùng/admin báo lỗi gắn với element cụ thể.",
        "",
        "## Hạn chế",
        "",
        "- Cart hiện vẫn phụ thuộc mạnh vào localStorage ở frontend.",
        "- Cần credential thật để kiểm thử VNPAY sandbox end-to-end.",
        "- Cần bổ sung dữ liệu review approved và quy trình email/notification production.",
        "",
        "## Hướng phát triển",
        "",
        "- Hoàn thiện persistent cart database và abandoned cart dashboard nếu chưa triển khai đầy đủ.",
        "- Tích hợp VNPAY sandbox thật qua ngrok/Laravel Cloud và chuyển production khi có thông tin merchant.",
        "- Bổ sung notification center, order tracking timeline, email transactional.",
        "- Tối ưu SEO, sitemap, metadata và dữ liệu structured data trước khi deploy.",
        "",
        "# Tài liệu tham khảo",
        "",
        "- Laravel Documentation, https://laravel.com/docs/10.x, truy cập ngày 20/06/2026.",
        "- Spatie Laravel Permission Documentation, https://spatie.be/docs/laravel-permission, truy cập ngày 20/06/2026.",
        "- PHPUnit Documentation, https://docs.phpunit.de/, truy cập ngày 20/06/2026.",
        "- VNPay Sandbox Integration Documentation, tài liệu cấu hình VNPAY Sandbox do cổng thanh toán cung cấp, tham khảo theo yêu cầu tích hợp.",
        "",
        "# Phụ lục",
        "",
        "## Tài khoản demo",
        "",
        "- Admin: `demo.admin@furemir-demo.test` / mật khẩu demo `Demo@123456!`.",
        "- User: `demo.user@furemir-demo.test` / mật khẩu demo `Demo@123456!`.",
        "",
        "## Danh sách migration",
        "",
    ])
    for m in migration_files():
        lines.append(f"- `{m}`")
    lines.extend(["", "## Route chính", ""])
    for r in main_routes()[:120]:
        lines.append(f"- `{r}`")
    return "\n".join(lines)


def test_accounts_markdown() -> str:
    return """
# Tài khoản demo phục vụ kiểm thử

Các tài khoản dưới đây được tạo riêng cho báo cáo và kiểm thử local.

| Vai trò | Email | Mật khẩu demo | Ghi chú |
|---|---|---|---|
| User | `demo.user@furemir-demo.test` | `Demo@123456!` | Có địa chỉ mặc định demo để checkout COD |
| Admin | `demo.admin@furemir-demo.test` | `Demo@123456!` | Có quyền quản trị/Administrators |

Không sử dụng các mật khẩu này cho môi trường production.
"""


def set_cell_text(cell, text, bold=False):
    cell.text = ""
    p = cell.paragraphs[0]
    p.alignment = WD_ALIGN_PARAGRAPH.LEFT
    run = p.add_run(str(text))
    run.font.name = "Times New Roman"
    run._element.rPr.rFonts.set(qn("w:ascii"), "Times New Roman")
    run._element.rPr.rFonts.set(qn("w:hAnsi"), "Times New Roman")
    run.font.size = Pt(10)
    run.bold = bold
    cell.vertical_alignment = WD_ALIGN_VERTICAL.CENTER


def set_cell_shading(cell, fill: str):
    tc_pr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:fill"), fill)
    tc_pr.append(shd)


def add_page_number(paragraph):
    paragraph.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = paragraph.add_run()
    fld_char1 = OxmlElement("w:fldChar")
    fld_char1.set(qn("w:fldCharType"), "begin")
    instr = OxmlElement("w:instrText")
    instr.set(qn("xml:space"), "preserve")
    instr.text = "PAGE"
    fld_char2 = OxmlElement("w:fldChar")
    fld_char2.set(qn("w:fldCharType"), "end")
    run._r.append(fld_char1)
    run._r.append(instr)
    run._r.append(fld_char2)


def add_toc(paragraph):
    run = paragraph.add_run()
    fld_char = OxmlElement("w:fldChar")
    fld_char.set(qn("w:fldCharType"), "begin")
    instr = OxmlElement("w:instrText")
    instr.set(qn("xml:space"), "preserve")
    instr.text = r'TOC \o "1-3" \h \z \u'
    fld_char2 = OxmlElement("w:fldChar")
    fld_char2.set(qn("w:fldCharType"), "separate")
    fld_char3 = OxmlElement("w:fldChar")
    fld_char3.set(qn("w:fldCharType"), "end")
    run._r.append(fld_char)
    run._r.append(instr)
    run._r.append(fld_char2)
    run = paragraph.add_run("Nhấn chuột phải và chọn Update Field để cập nhật mục lục.")
    run.italic = True
    paragraph.add_run()._r.append(fld_char3)


def set_run_font(run, size=13, bold=False, color=None):
    run.font.name = "Times New Roman"
    run._element.rPr.rFonts.set(qn("w:ascii"), "Times New Roman")
    run._element.rPr.rFonts.set(qn("w:hAnsi"), "Times New Roman")
    run.font.size = Pt(size)
    run.bold = bold
    if color:
        run.font.color.rgb = RGBColor.from_string(color)


def configure_doc(doc: Document):
    section = doc.sections[0]
    section.page_width = Cm(21)
    section.page_height = Cm(29.7)
    section.left_margin = Cm(3.2)
    section.right_margin = Cm(2.0)
    section.top_margin = Cm(2.2)
    section.bottom_margin = Cm(2.2)
    section.header_distance = Cm(1.2)
    section.footer_distance = Cm(1.2)
    for style_name, size, bold in [
        ("Normal", 13, False),
        ("Heading 1", 16, True),
        ("Heading 2", 14, True),
        ("Heading 3", 13, True),
    ]:
        style = doc.styles[style_name]
        style.font.name = "Times New Roman"
        style._element.rPr.rFonts.set(qn("w:ascii"), "Times New Roman")
        style._element.rPr.rFonts.set(qn("w:hAnsi"), "Times New Roman")
        style.font.size = Pt(size)
        style.font.bold = bold
        if style_name.startswith("Heading"):
            style.font.color.rgb = RGBColor(0, 0, 0)
        pf = style.paragraph_format
        pf.line_spacing = 1.3
        pf.space_after = Pt(6)
        if style_name == "Normal":
            pf.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY

    footer = section.footer.paragraphs[0]
    add_page_number(footer)


def add_para(doc, text="", style=None, align=None, bold=False):
    p = doc.add_paragraph(style=style)
    if align is not None:
        p.alignment = align
    elif style is None:
        p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
    p.paragraph_format.line_spacing = 1.3
    p.paragraph_format.space_after = Pt(6)
    if text:
        run = p.add_run(text)
        set_run_font(run, 13, bold=bold)
    return p


def add_bullets(doc, items):
    for item in items:
        p = doc.add_paragraph(style="List Bullet")
        p.paragraph_format.line_spacing = 1.3
        p.paragraph_format.space_after = Pt(3)
        run = p.add_run(item)
        set_run_font(run, 13)


def add_table(doc, headers, rows, widths=None):
    table = doc.add_table(rows=1, cols=len(headers))
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table.style = "Table Grid"
    for i, h in enumerate(headers):
        set_cell_text(table.rows[0].cells[i], h, bold=True)
        set_cell_shading(table.rows[0].cells[i], "E8EEF5")
    for row in rows:
        cells = table.add_row().cells
        for i, value in enumerate(row):
            set_cell_text(cells[i], value)
    if widths:
        for row in table.rows:
            for idx, width in enumerate(widths):
                row.cells[idx].width = width
    doc.add_paragraph()
    return table


def add_image(doc, image_path: Path, caption: str, number: int, max_width=6.0):
    if not image_path.exists():
        return
    with Image.open(image_path) as img:
        width_px, height_px = img.size
    ratio = height_px / max(width_px, 1)
    width = Inches(max_width)
    if ratio * max_width > 5.0:
        width = Inches(min(max_width, 5.0 / ratio))
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.add_run().add_picture(str(image_path), width=width)
    cap = doc.add_paragraph()
    cap.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = cap.add_run(f"Hình {number}: {caption}")
    set_run_font(run, 12, bold=False)
    run.italic = True


def build_docx():
    doc = Document()
    configure_doc(doc)

    # Cover
    for text, size, bold in [
        ("[TÊN TRƯỜNG]", 14, True),
        ("[TÊN KHOA]", 14, True),
        ("BÁO CÁO ĐỒ ÁN", 18, True),
        ("WEBSITE CỬA HÀNG NỘI THẤT FUREMIR", 18, True),
    ]:
        p = doc.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p.paragraph_format.space_after = Pt(12)
        run = p.add_run(text)
        set_run_font(run, size, bold=bold)
    doc.add_paragraph()
    for label, value in [
        ("Sinh viên thực hiện", "Đỗ Quang Hưng"),
        ("Mã sinh viên", "[Mã sinh viên]"),
        ("Giảng viên hướng dẫn", "[Giảng viên hướng dẫn]"),
        ("Địa điểm và năm thực hiện", "[Địa điểm], 2026"),
    ]:
        p = doc.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        run = p.add_run(f"{label}: {value}")
        set_run_font(run, 13, bold=False)
    doc.add_page_break()

    doc.add_heading("Lời cảm ơn", level=1)
    add_para(doc, "Em xin gửi lời cảm ơn đến giảng viên hướng dẫn và các thầy cô đã hỗ trợ trong quá trình học tập, định hướng phân tích yêu cầu và hoàn thiện đồ án. Em cũng cảm ơn gia đình, bạn bè đã động viên để em có điều kiện tập trung xây dựng, kiểm thử và hoàn thiện hệ thống website cửa hàng nội thất.")

    doc.add_heading("Lời cam đoan", level=1)
    add_para(doc, "Em cam đoan nội dung báo cáo được xây dựng dựa trên mã nguồn, cơ sở dữ liệu và kết quả kiểm thử thực tế của project hiện tại. Những phần dữ liệu demo, tài khoản demo hoặc giới hạn kỹ thuật đều được ghi rõ trong báo cáo; em không cố ý mô tả chức năng không tồn tại hoặc chưa được kiểm chứng.")

    doc.add_heading("Tóm tắt đề tài", level=1)
    add_para(doc, "Đề tài tập trung xây dựng website thương mại điện tử bán nội thất Furemir trên nền tảng Laravel 10, Blade và jQuery. Hệ thống cho phép khách truy cập xem sản phẩm, tìm kiếm, lọc theo danh mục, màu sắc, giá, xem chi tiết SKU/biến thể, tham khảo sản phẩm liên quan và landing page theo phòng. Người dùng đã đăng nhập có thể quản lý tài khoản, địa chỉ, wishlist, so sánh sản phẩm, thêm sản phẩm vào giỏ hàng, đặt hàng COD hoặc VNPAY, theo dõi lịch sử đơn hàng và gửi visual feedback trực tiếp trên giao diện. Phía quản trị viên cung cấp dashboard, quản lý catalog, danh mục, màu, khách hàng, đơn hàng, review, feedback, thống kê, discount và các công cụ hỗ trợ AI. Báo cáo này được tạo từ việc phân tích trực tiếp source code, database và kiểm thử trình duyệt trên localhost.")

    doc.add_heading("Mục lục", level=1)
    add_toc(doc.add_paragraph())
    doc.add_page_break()

    figures = [t for t in tests if screenshot_exists(t.get("screenshot", ""))]
    doc.add_heading("Danh mục hình ảnh", level=1)
    for i, t in enumerate(figures, 1):
        add_para(doc, f"Hình {i}: {t['feature']} - {t['screenshot']}")
    doc.add_heading("Danh mục bảng biểu", level=1)
    add_para(doc, "Bảng 1: Thống kê công nghệ và môi trường.")
    add_para(doc, "Bảng 2: Danh sách bảng dữ liệu chính.")
    add_para(doc, "Bảng 3: Kết quả kiểm thử chức năng.")
    doc.add_page_break()

    doc.add_heading("Chương 1: Tổng quan đề tài", level=1)
    for heading, text in [
        ("Lý do chọn đề tài", "Nội thất là nhóm sản phẩm có giá trị cao, nhiều biến thể và cần trải nghiệm hình ảnh rõ ràng. Website bán nội thất giúp khách hàng tìm kiếm, so sánh và đặt mua sản phẩm thuận tiện hơn."),
        ("Bài toán thực tế", "Cửa hàng cần một hệ thống quản lý catalog, đơn hàng, khách hàng và phản hồi. Người mua cần xem sản phẩm theo màu/SKU, lưu wishlist, so sánh, đặt hàng và theo dõi trạng thái."),
        ("Mục tiêu", "Xây dựng website thương mại điện tử nội thất có frontend cho khách/user và backend admin, đồng thời bổ sung các chức năng nâng cao đã có trong project như visual feedback, verified review, VNPAY, room collection, AI combo và catalog quality checker."),
        ("Phạm vi", "Báo cáo chỉ mô tả chức năng tồn tại trong source code hoặc đã kiểm thử thành công. Các tích hợp thiếu credential thật được ghi nhận là giới hạn."),
    ]:
        doc.add_heading(heading, level=2)
        add_para(doc, text)

    doc.add_heading("Chương 2: Cơ sở lý thuyết và công nghệ", level=1)
    tech_rows = [
        ("Laravel 10", "Framework PHP MVC, route, controller, Eloquent, migration, storage, test."),
        ("Blade + jQuery", "Render giao diện server-side và xử lý AJAX/widget trên frontend."),
        ("MySQL", "Lưu dữ liệu người dùng, sản phẩm, SKU, đơn hàng, payment, review, feedback."),
        ("Spatie Permission", "Cung cấp bảng role/permission, kết hợp is_staff để quản trị."),
        ("VNPAY", "Tích hợp thanh toán online qua URL ký HMAC và IPN xác minh."),
        ("AI modules", "AI chatbox, admin summary, catalog checker và combo suggestion với fallback."),
    ]
    add_table(doc, ["Công nghệ", "Vai trò trong dự án"], tech_rows, [Cm(4), Cm(10)])

    doc.add_heading("Chương 3: Phân tích và thiết kế hệ thống", level=1)
    doc.add_heading("Yêu cầu chức năng", level=2)
    add_bullets(doc, [
        "Khách xem trang chủ, shop, chi tiết sản phẩm, room collection, đăng ký và đăng nhập.",
        "User quản lý tài khoản, cart, checkout, order, wishlist, compare, review và feedback.",
        "Admin quản lý catalog, đơn hàng, khách hàng, thống kê, review, feedback, discount và AI tools.",
    ])
    doc.add_heading("Kiến trúc hệ thống", level=2)
    add_image(doc, DIAGRAMS / "architecture.png", "Sơ đồ kiến trúc tổng quan", 1)
    doc.add_heading("Thiết kế cơ sở dữ liệu", level=2)
    add_para(doc, f"Database `{project['app']['database_name']}` hiện có {len(tables)} bảng. Các cụm quan hệ chính gồm user/address, product/SKU/image/category/color, order/order_detail/payment, review, visual feedback và wishlist.")
    add_image(doc, DIAGRAMS / "erd-summary.png", "ERD rút gọn các quan hệ chính", 2)
    db_rows = []
    for table in ["users", "products", "product_details", "product_images", "orders", "order_details", "payments", "reviews", "visual_feedbacks", "wishlist_items", "room_collections"]:
        if table in schema:
            db_rows.append((table, schema[table].get("count", 0), ", ".join([c["COLUMN_NAME"] for c in schema[table]["columns"] if c.get("COLUMN_KEY") == "PRI"]) or "-"))
    add_table(doc, ["Bảng", "Số dòng", "Khóa chính"], db_rows, [Cm(5), Cm(3), Cm(6)])

    doc.add_heading("Chương 4: Xây dựng hệ thống", level=1)
    doc.add_heading("Môi trường phát triển", level=2)
    add_table(doc, ["Thành phần", "Giá trị"], [
        ("PHP", "8.2.31"),
        ("Laravel", "10.x"),
        ("Database", "MySQL"),
        ("Frontend", "Blade + jQuery"),
        ("Local server", "php artisan serve --host=127.0.0.1 --port=8000"),
    ], [Cm(5), Cm(9)])
    doc.add_heading("Luồng checkout", level=2)
    add_image(doc, DIAGRAMS / "checkout-flow.png", "Luồng xử lý checkout COD/VNPAY", 3)
    doc.add_heading("Cấu trúc source code", level=2)
    add_bullets(doc, [
        "Controller nằm trong app/Http/Controllers, tách admin/public/payment/feedback/review.",
        "Model nằm trong app/Models, dùng khóa chính thực tế như user_id, product_id, order_id và sku.",
        "View nằm trong resources/views/pages và resources/views/admin.",
        "JavaScript nghiệp vụ nằm trong public/js như cart.js, shop_api.js, visual_feedback.js, ai_interior_combo.js.",
    ])

    doc.add_heading("Chương 5: Giao diện và kết quả thực hiện", level=1)
    fig_no = 4
    for t in figures:
        doc.add_heading(t["feature"], level=2)
        add_para(doc, f"Mục đích: minh họa chức năng {t['feature']}.")
        add_para(doc, f"Cách kiểm thử: {t['steps']}")
        add_para(doc, f"Kết quả: {t['actual']}")
        add_image(doc, SCREENSHOTS / t["screenshot"], t["feature"], fig_no)
        fig_no += 1

    doc.add_heading("Chương 6: Kiểm thử hệ thống", level=1)
    pass_count = sum(1 for t in tests if t["status"] == "Pass")
    fail_count = len(tests) - pass_count
    add_para(doc, f"Kiểm thử được thực hiện trên Chromium viewport 1440x900. Tổng số test: {len(tests)}, Pass: {pass_count}, Fail: {fail_count}.")
    test_rows = [(t["id"], t["feature"], t["status"], t.get("screenshot", "")) for t in tests]
    add_table(doc, ["Mã", "Chức năng", "Trạng thái", "Ảnh"], test_rows, [Cm(2.8), Cm(7), Cm(2.4), Cm(4)])
    doc.add_heading("Giới hạn kiểm thử", level=2)
    add_bullets(doc, [
        "Chưa kiểm thử giao dịch VNPAY sandbox thật vì thiếu credential và URL public cho IPN.",
        "AI provider không được cấu hình công khai trong báo cáo, nên chỉ kiểm tra UI/fallback.",
        "Không có approved review demo để kiểm tra hiển thị rating thật ở product detail.",
    ])

    doc.add_heading("Chương 7: Kết luận và hướng phát triển", level=1)
    add_para(doc, "Website Furemir đã có nhiều chức năng của một hệ thống bán nội thất: catalog, SKU, cart, checkout, order, admin, feedback, review, wishlist, compare, SEO collection, VNPAY và các module AI hỗ trợ. Kết quả kiểm thử trên localhost cho thấy các màn hình chính hoạt động ổn định trong phạm vi dữ liệu demo.")
    doc.add_heading("Hướng phát triển", level=2)
    add_bullets(doc, [
        "Kiểm thử VNPAY sandbox thật bằng ngrok hoặc Laravel Cloud khi có credential.",
        "Bổ sung notification center và order tracking timeline.",
        "Hoàn thiện dữ liệu review approved và email transaction.",
        "Tối ưu quy trình build frontend nếu khôi phục package.json/Vite pipeline.",
    ])

    doc.add_heading("Tài liệu tham khảo", level=1)
    add_bullets(doc, [
        "Laravel Documentation, https://laravel.com/docs/10.x, truy cập ngày 20/06/2026.",
        "Spatie Laravel Permission Documentation, https://spatie.be/docs/laravel-permission, truy cập ngày 20/06/2026.",
        "PHPUnit Documentation, https://docs.phpunit.de/, truy cập ngày 20/06/2026.",
        "Tài liệu VNPAY Sandbox do cổng thanh toán cung cấp.",
    ])

    doc.add_heading("Phụ lục", level=1)
    doc.add_heading("Tài khoản demo", level=2)
    add_table(doc, ["Vai trò", "Email", "Mật khẩu demo"], [
        ("User", "demo.user@furemir-demo.test", "Demo@123456!"),
        ("Admin", "demo.admin@furemir-demo.test", "Demo@123456!"),
    ], [Cm(3), Cm(7), Cm(4)])
    doc.add_heading("Route chính", level=2)
    add_bullets(doc, main_routes()[:80])
    doc.add_heading("Danh sách migration", level=2)
    add_bullets(doc, migration_files()[:80])

    doc.save(REPORT_DOCX)


def register_pdf_fonts():
    regular = Path("/System/Library/Fonts/Supplemental/Times New Roman.ttf")
    bold = Path("/System/Library/Fonts/Supplemental/Times New Roman Bold.ttf")
    if regular.exists():
        pdfmetrics.registerFont(TTFont("TimesNewRoman", str(regular)))
    if bold.exists():
        pdfmetrics.registerFont(TTFont("TimesNewRoman-Bold", str(bold)))
    return (
        "TimesNewRoman" if regular.exists() else "Times-Roman",
        "TimesNewRoman-Bold" if bold.exists() else "Times-Bold",
    )


def p(text: str) -> str:
    return (
        str(text)
        .replace("&", "&amp;")
        .replace("<", "&lt;")
        .replace(">", "&gt;")
        .replace("\n", "<br/>")
    )


def pdf_table(data, col_widths):
    table = Table(data, colWidths=col_widths, repeatRows=1)
    table.setStyle(
        TableStyle(
            [
                ("FONTNAME", (0, 0), (-1, 0), "TimesNewRoman-Bold"),
                ("FONTNAME", (0, 1), (-1, -1), "TimesNewRoman"),
                ("FONTSIZE", (0, 0), (-1, -1), 8),
                ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#E8EEF5")),
                ("GRID", (0, 0), (-1, -1), 0.25, colors.HexColor("#9CA3AF")),
                ("VALIGN", (0, 0), (-1, -1), "TOP"),
                ("LEFTPADDING", (0, 0), (-1, -1), 4),
                ("RIGHTPADDING", (0, 0), (-1, -1), 4),
                ("TOPPADDING", (0, 0), (-1, -1), 4),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
            ]
        )
    )
    return table


def build_pdf():
    regular, bold = register_pdf_fonts()
    styles = getSampleStyleSheet()
    styles.add(
        ParagraphStyle(
            "VNBody",
            parent=styles["Normal"],
            fontName=regular,
            fontSize=11.5,
            leading=16,
            alignment=TA_JUSTIFY,
            spaceAfter=7,
        )
    )
    styles.add(
        ParagraphStyle(
            "VNTitle",
            parent=styles["Title"],
            fontName=bold,
            fontSize=18,
            leading=24,
            alignment=TA_CENTER,
            spaceAfter=18,
        )
    )
    styles.add(
        ParagraphStyle(
            "VNHeading1",
            parent=styles["Heading1"],
            fontName=bold,
            fontSize=15,
            leading=20,
            spaceBefore=10,
            spaceAfter=8,
        )
    )
    styles.add(
        ParagraphStyle(
            "VNHeading2",
            parent=styles["Heading2"],
            fontName=bold,
            fontSize=13,
            leading=17,
            spaceBefore=8,
            spaceAfter=6,
        )
    )
    styles.add(
        ParagraphStyle(
            "VNCaption",
            parent=styles["Normal"],
            fontName=regular,
            fontSize=10,
            leading=13,
            alignment=TA_CENTER,
            spaceAfter=10,
        )
    )
    styles.add(
        ParagraphStyle(
            "VNCell",
            parent=styles["Normal"],
            fontName=regular,
            fontSize=8,
            leading=10,
            alignment=TA_LEFT,
        )
    )

    doc = SimpleDocTemplate(
        str(REPORT_PDF),
        pagesize=A4,
        leftMargin=3.2 * cm,
        rightMargin=2.0 * cm,
        topMargin=2.2 * cm,
        bottomMargin=2.2 * cm,
        title="Bao cao do an website noi that Furemir",
    )
    story = []

    def H1(text):
        story.append(Paragraph(p(text), styles["VNHeading1"]))

    def H2(text):
        story.append(Paragraph(p(text), styles["VNHeading2"]))

    def P(text):
        story.append(Paragraph(p(text), styles["VNBody"]))

    story.append(Paragraph("[TÊN TRƯỜNG]<br/>[TÊN KHOA]", styles["VNTitle"]))
    story.append(Spacer(1, 1.2 * cm))
    story.append(Paragraph("BÁO CÁO ĐỒ ÁN<br/>WEBSITE CỬA HÀNG NỘI THẤT FUREMIR", styles["VNTitle"]))
    story.append(Spacer(1, 0.8 * cm))
    for label, value in [
        ("Sinh viên thực hiện", "Đỗ Quang Hưng"),
        ("Mã sinh viên", "[Mã sinh viên]"),
        ("Giảng viên hướng dẫn", "[Giảng viên hướng dẫn]"),
        ("Địa điểm và năm thực hiện", "[Địa điểm], 2026"),
    ]:
        story.append(Paragraph(f"{label}: {value}", styles["VNBody"]))
    story.append(PageBreak())

    H1("Lời cảm ơn")
    P("Em xin gửi lời cảm ơn đến giảng viên hướng dẫn và các thầy cô đã hỗ trợ trong quá trình học tập, định hướng phân tích yêu cầu và hoàn thiện đồ án.")
    H1("Lời cam đoan")
    P("Em cam đoan nội dung báo cáo được xây dựng dựa trên mã nguồn, cơ sở dữ liệu và kết quả kiểm thử thực tế của project hiện tại.")
    H1("Tóm tắt đề tài")
    P("Đề tài xây dựng website thương mại điện tử bán nội thất Furemir bằng Laravel 10, Blade và jQuery. Hệ thống hỗ trợ khách xem/lọc sản phẩm, người dùng quản lý cart, order, wishlist, compare, feedback; admin quản lý catalog, đơn hàng, review, visual feedback, thống kê và các công cụ AI.")

    H1("Chương 1: Tổng quan đề tài")
    P("Website nội thất cần trình bày sản phẩm theo ảnh, màu, kích thước và SKU rõ ràng, đồng thời hỗ trợ mua hàng, theo dõi đơn và quản trị catalog.")
    H1("Chương 2: Cơ sở lý thuyết và công nghệ")
    story.append(
        pdf_table(
            [[Paragraph("Công nghệ", styles["VNCell"]), Paragraph("Vai trò", styles["VNCell"])]]
            + [
                [Paragraph(p(a), styles["VNCell"]), Paragraph(p(b), styles["VNCell"])]
                for a, b in [
                    ("Laravel 10", "MVC, route, controller, Eloquent, migration, storage, test."),
                    ("Blade + jQuery", "Render UI và xử lý AJAX/widget."),
                    ("MySQL", "Lưu dữ liệu nghiệp vụ."),
                    ("VNPAY", "Thanh toán online qua URL ký và IPN."),
                    ("AI modules", "Chatbox, catalog quality, AI combo với fallback."),
                ]
            ],
            [4 * cm, 10 * cm],
        )
    )
    story.append(Spacer(1, 0.4 * cm))

    H1("Chương 3: Phân tích và thiết kế hệ thống")
    P(f"Database hiện có {len(tables)} bảng, {table_count('products')} sản phẩm, {table_count('product_details')} SKU/variant, {table_count('orders')} đơn hàng và {table_count('visual_feedbacks')} visual feedback.")
    for idx, (img, caption) in enumerate(
        [
            (DIAGRAMS / "architecture.png", "Sơ đồ kiến trúc tổng quan"),
            (DIAGRAMS / "erd-summary.png", "ERD rút gọn"),
            (DIAGRAMS / "checkout-flow.png", "Luồng checkout COD/VNPAY"),
        ],
        1,
    ):
        story.append(PdfImage(str(img), width=14 * cm, height=7.9 * cm))
        story.append(Paragraph(f"Hình {idx}: {caption}", styles["VNCaption"]))

    H1("Chương 4: Xây dựng hệ thống")
    P("Source code được tổ chức theo chuẩn Laravel, gồm controller, model, service, migration, Blade view và JavaScript public. Các service tách logic thanh toán, AI, catalog quality và recommendation.")

    H1("Chương 5: Giao diện và kết quả thực hiện")
    figure_no = 4
    for test in tests:
        screenshot = test.get("screenshot")
        if not screenshot_exists(screenshot):
            continue
        H2(f"Hình {figure_no}: {test['feature']}")
        P(f"Cách kiểm thử: {test['steps']}")
        P(f"Kết quả: {test['actual']}")
        img_path = SCREENSHOTS / screenshot
        with Image.open(img_path) as img:
            w, h = img.size
        max_w = 14.5 * cm
        max_h = 9.2 * cm
        ratio = min(max_w / w, max_h / h)
        story.append(PdfImage(str(img_path), width=w * ratio, height=h * ratio))
        story.append(Paragraph(f"Hình {figure_no}: {test['feature']}", styles["VNCaption"]))
        figure_no += 1

    H1("Chương 6: Kiểm thử hệ thống")
    pass_count = sum(1 for t in tests if t["status"] == "Pass")
    fail_count = len(tests) - pass_count
    P(f"Tổng số test: {len(tests)}. Pass: {pass_count}. Fail: {fail_count}. Viewport kiểm thử: Chromium 1440x900.")
    rows = [[Paragraph("Mã", styles["VNCell"]), Paragraph("Chức năng", styles["VNCell"]), Paragraph("Trạng thái", styles["VNCell"])]]
    for test in tests:
        rows.append([
            Paragraph(p(test["id"]), styles["VNCell"]),
            Paragraph(p(test["feature"]), styles["VNCell"]),
            Paragraph(p(test["status"]), styles["VNCell"]),
        ])
    story.append(pdf_table(rows, [2.5 * cm, 9 * cm, 2.5 * cm]))

    H1("Chương 7: Kết luận và hướng phát triển")
    P("Project đã đáp ứng nhiều chức năng cốt lõi của website bán nội thất và có thêm các chức năng nâng cao như visual feedback, wishlist, compare, room collection, AI combo, catalog checker và VNPAY module.")
    P("Hướng phát triển gồm kiểm thử VNPAY sandbox thật, bổ sung notification center, dữ liệu review approved, tối ưu SEO và hoàn thiện quy trình build frontend.")

    H1("Tài liệu tham khảo")
    for ref in [
        "Laravel Documentation, https://laravel.com/docs/10.x, truy cập ngày 20/06/2026.",
        "Spatie Laravel Permission Documentation, https://spatie.be/docs/laravel-permission, truy cập ngày 20/06/2026.",
        "PHPUnit Documentation, https://docs.phpunit.de/, truy cập ngày 20/06/2026.",
    ]:
        P(ref)

    H1("Phụ lục")
    P("Tài khoản demo: demo.user@furemir-demo.test và demo.admin@furemir-demo.test, mật khẩu demo Demo@123456!. Không sử dụng mật khẩu này cho production.")

    def add_page(canvas, _doc):
        canvas.saveState()
        canvas.setFont(regular, 9)
        canvas.drawCentredString(A4[0] / 2, 1.2 * cm, str(canvas.getPageNumber()))
        canvas.restoreState()

    doc.build(story, onFirstPage=add_page, onLaterPages=add_page)


def main():
    FINAL.mkdir(parents=True, exist_ok=True)
    DIAGRAMS.mkdir(parents=True, exist_ok=True)
    create_diagrams()

    write_text(DATABASE_SCHEMA, schema_markdown())
    write_text(INSTALLATION_GUIDE, installation_markdown())
    write_text(PROJECT_ANALYSIS, project_analysis_markdown())
    shutil.copyfile(PROJECT_ANALYSIS, FINAL_PROJECT_ANALYSIS)
    write_text(FUNCTIONAL_TESTS, tests_markdown())
    shutil.copyfile(FUNCTIONAL_TESTS, FINAL_FUNCTIONAL_TESTS)
    write_text(TEST_ACCOUNTS, test_accounts_markdown())
    write_text(REPORT_MD, report_markdown())
    build_docx()
    build_pdf()
    write_text(NOTES, f"""
# Ghi chú sinh báo cáo

- Thời gian sinh: {datetime.now().isoformat(timespec='seconds')}
- Preset tài liệu: narrative_proposal, override theo yêu cầu đồ án A4/Times New Roman/13pt.
- DOCX đã được tạo bằng python-docx từ dữ liệu route, schema, test và ảnh chụp thực tế.
- PDF được tạo bằng ReportLab từ cùng dữ liệu/ảnh vì renderer LibreOffice bundled bị thiếu thư viện `liblcms2.2.dylib` trong môi trường hiện tại.
- Không đưa `.env`, password database, API key hoặc secret VNPAY vào báo cáo.
""")


if __name__ == "__main__":
    main()
