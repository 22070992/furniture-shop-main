# Ghi chú sinh báo cáo

- Thời gian sinh: 2026-06-20T19:22:59
- Preset tài liệu: narrative_proposal, override theo yêu cầu đồ án A4/Times New Roman/13pt.
- DOCX đã được tạo bằng python-docx từ dữ liệu route, schema, test và ảnh chụp thực tế.
- PDF được tạo bằng ReportLab từ cùng dữ liệu/ảnh vì renderer LibreOffice bundled bị thiếu thư viện `liblcms2.2.dylib` trong môi trường hiện tại.
- Không đưa `.env`, password database, API key hoặc secret VNPAY vào báo cáo.
- Kiểm thử Laravel sau khi tạo báo cáo: `php artisan test` pass 93 tests với 277 assertions.
- Kiểm thử giao diện bằng Chromium viewport 1440x900: 46/46 test pass, 46 ảnh minh họa tồn tại.
