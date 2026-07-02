<div class="ai-chatbox" data-ai-chatbox data-endpoint="{{ route('ai-chat.reply') }}">
    <button class="ai-chatbox-toggle" type="button" aria-label="Open Furemir AI chat" data-ai-chat-toggle>
        <i class="pe-7s-comment"></i>
        <span>AI</span>
    </button>

    <section class="ai-chatbox-panel" aria-label="Furemir AI chat" data-ai-chat-panel hidden>
        <div class="ai-chatbox-header">
            <div>
                <strong>Furemir AI</strong>
                <span>Tư vấn nội thất</span>
            </div>
            <button type="button" aria-label="Close chat" data-ai-chat-close>
                <i class="ti-close"></i>
            </button>
        </div>

        <div class="ai-chatbox-messages" data-ai-chat-messages>
            <div class="ai-chat-message bot">
                <p>Chào bạn, hãy mô tả căn phòng, ngân sách, màu sắc hoặc sản phẩm bạn đang cần. Mình sẽ tư vấn theo dữ liệu sản phẩm Furemir.</p>
            </div>
        </div>

        <div class="ai-chatbox-quick" data-ai-chat-quick>
            <button type="button" data-prompt="Tư vấn bàn cho phòng khách nhỏ">Tư vấn bàn phòng khách</button>
            <button type="button" data-prompt="Tôi cần ghế làm việc thoải mái dưới 5 triệu">Ghế làm việc dưới 5 triệu</button>
            <button type="button" data-prompt="Gợi ý giường và tủ cho phòng ngủ màu sáng">Combo phòng ngủ sáng</button>
        </div>

        <form class="ai-chatbox-form" data-ai-chat-form>
            <input type="text" name="message" autocomplete="off" maxlength="1000"
                placeholder="Nhập câu hỏi..." data-ai-chat-input>
            <button type="submit" aria-label="Send message">
                <i class="ti-arrow-right"></i>
            </button>
        </form>
    </section>
</div>
