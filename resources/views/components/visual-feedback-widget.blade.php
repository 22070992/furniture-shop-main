<div id="visual-feedback-root"
    data-visual-feedback-ui
    data-authenticated="{{ Auth::check() ? '1' : '0' }}"
    data-login-url="{{ route('login') }}"
    data-store-url="{{ route('feedback.store') }}"
    data-current-route="{{ optional(request()->route())->getName() }}"
    data-current-path="{{ request()->path() === '/' ? '/' : '/' . request()->path() }}"
    data-user-id="{{ Auth::check() ? Auth::user()->getKey() : '' }}"
    data-user-role="{{ Auth::check() && Auth::user()->is_staff ? 'admin' : 'user' }}">
    <button type="button" class="vf-trigger" data-visual-feedback-ui aria-label="Gửi phản hồi">
        <span class="vf-trigger-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false">
                <path d="M12 3a5 5 0 0 1 5 5v1h1.5a1 1 0 1 1 0 2H17v2h1.5a1 1 0 1 1 0 2H17v1a5 5 0 0 1-10 0v-1H5.5a1 1 0 1 1 0-2H7v-2H5.5a1 1 0 1 1 0-2H7V8a5 5 0 0 1 5-5Zm0 2a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V8a3 3 0 0 0-3-3Zm-2 5h4v2h-4v-2Zm0 4h4v2h-4v-2Z" />
            </svg>
        </span>
        <span>Gửi phản hồi</span>
    </button>

    <div class="vf-modebar d-none" data-visual-feedback-ui>
        <strong>Visual Feedback Mode</strong>
        <span>Di chuột hoặc tap vào thành phần cần báo lỗi. Esc để thoát.</span>
        <button type="button" class="vf-exit" data-visual-feedback-ui>Thoát chế độ feedback</button>
    </div>

    <div class="vf-highlight d-none" data-visual-feedback-ui></div>
    <div class="vf-label d-none" data-visual-feedback-ui></div>

    <div class="vf-modal d-none" data-visual-feedback-ui role="dialog" aria-modal="true" aria-labelledby="vf-title">
        <div class="vf-panel" data-visual-feedback-ui>
            <div class="vf-panel-head">
                <div>
                    <div class="vf-kicker">Visual Feedback</div>
                    <h2 id="vf-title">Gửi phản hồi</h2>
                </div>
                <button type="button" class="vf-close" data-visual-feedback-ui aria-label="Đóng">×</button>
            </div>

            <div class="vf-alert d-none" data-visual-feedback-ui></div>

            <div class="vf-preview-wrap">
                <img class="vf-preview d-none" data-visual-feedback-ui alt="Ảnh chụp khu vực được chọn">
                <div class="vf-selected-meta" data-visual-feedback-ui>
                    <strong class="vf-selected-page"></strong>
                    <span class="vf-selected-element"></span>
                </div>
            </div>

            <form class="vf-form" data-visual-feedback-ui enctype="multipart/form-data">
                <label>
                    <span>Loại feedback *</span>
                    <select name="type" required>
                        <option>Bug</option>
                        <option>UI/UX</option>
                        <option>Content</option>
                        <option>Product information</option>
                        <option>Wrong image</option>
                        <option>Feature request</option>
                        <option>Performance</option>
                        <option>Accessibility</option>
                        <option>Other</option>
                    </select>
                </label>

                <label>
                    <span>Tiêu đề *</span>
                    <input type="text" name="title" maxlength="180" required placeholder="Ví dụ: Ảnh sản phẩm không đúng">
                </label>
                <label>
                    <span>Mô tả *</span>
                    <textarea name="description" rows="4" required placeholder="Mô tả vấn đề bạn thấy trên giao diện"></textarea>
                </label>
                <label>
                    <span>File đính kèm</span>
                    <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf,.txt,.doc,.docx">
                </label>

                <div class="vf-actions">
                    <button type="button" class="vf-secondary vf-reselect" data-visual-feedback-ui>Chọn lại element</button>
                    <button type="button" class="vf-secondary vf-cancel" data-visual-feedback-ui>Hủy</button>
                    <button type="submit" class="vf-primary">Gửi phản hồi</button>
                </div>
            </form>
        </div>
    </div>
</div>
