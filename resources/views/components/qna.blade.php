<div id="chatContainer" class="flex flex-col h-full overflow-hidden">
    <div id="chatbox" class="flex-1 min-h-0 w-full p-4 bg-[#f8fafc] overflow-y-auto flex flex-col gap-3">
    </div>

    <div class="flex-shrink-0 bg-white border-t border-gray-100 p-3">
        <div id="question" class="w-full p-2 mb-2 text-center text-xs border border-blue-200 rounded-lg bg-blue-50 text-blue-700
                    cursor-pointer hover:bg-blue-100 transition-all border-dashed duration-300"
            onclick="putQuestion()">
            Memuat pertanyaan...
        </div>

        <form id="chatForm" class="flex items-stretch gap-0">
            <textarea id="message" name="message" placeholder="Tulis pertanyaan..."
                class="flex-grow border border-gray-300 rounded-l-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-300 resize-none text-sm transition-[height] duration-200 ease-in-out"
                rows="1" style="height: 42px; max-height: 120px; overflow-y: hidden;" required></textarea>

            <button id="submitBtn" type="submit"
                class="bg-blue-600 text-white px-5 rounded-r-xl hover:bg-blue-700 active:scale-95 transition-all flex items-center justify-center shadow-sm min-w-[55px]">
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>
    </div>
</div>

<style>
#message {
    transition: height 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    line-height: 1.5;
}

#message::-webkit-scrollbar {
    width: 4px;
}

#message::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}

.opacity-0 {
    opacity: 0;
    transform: translateY(-5px);
}

#question {
    transition: all 0.3s ease-in-out;
}
</style>

<script>
const chatForm = document.getElementById('chatForm');
const chatbox = document.querySelector('#chatbox');
const messageInput = document.getElementById('message');
const submitBtn = document.getElementById('submitBtn');
const questionElement = document.getElementById('question');
const ONE_WEEK_MS = 7 * 24 * 60 * 60 * 1000;
let isBusy = false;

function setChatBusy(busy) {
    isBusy = busy;
    submitBtn.disabled = busy;
    messageInput.disabled = busy;

    if (busy) {
        submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
        questionElement.classList.add('pointer-events-none', 'opacity-50');
    } else {
        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        questionElement.classList.remove('pointer-events-none', 'opacity-50');
        setTimeout(() => messageInput.focus(), 50);
    }
}

function resizeTextarea() {
    messageInput.style.overflowY = 'hidden';
    messageInput.style.height = 'auto';

    let scHeight = messageInput.scrollHeight;

    if (scHeight <= 42) {
        messageInput.style.height = '42px';
    } else if (scHeight >= 120) {
        messageInput.style.height = '120px';
        messageInput.style.overflowY = 'auto';
    } else {
        messageInput.style.height = scHeight + 'px';
    }
}

messageInput.addEventListener('input', resizeTextarea);

function putQuestion() {
    if (isBusy) return;
    const text = questionElement.textContent.trim();
    messageInput.value = text;

    setTimeout(() => {
        resizeTextarea();
        messageInput.focus();
    }, 50);
}

function loadChatHistory() {
    try {
        const raw = localStorage.getItem('skaribot_chat_history');
        if (!raw) return;

        const history = JSON.parse(raw);
        if (!Array.isArray(history)) {
            localStorage.removeItem('skaribot_chat_history');
            return;
        }

        const now = Date.now();
        const valid = history.filter(item => item && item.timestamp && (now - item.timestamp < ONE_WEEK_MS));

        if (valid.length !== history.length) {
            if (valid.length === 0) {
                localStorage.removeItem('skaribot_chat_history');
            } else {
                localStorage.setItem('skaribot_chat_history', JSON.stringify(valid));
            }
        }

        valid.forEach(msg => {
            appendMessage(msg.sender, msg.text, false);
        });
    } catch (e) {
        console.error('Failed to load chat history:', e);
    }
}

function saveChatMessage(sender, text) {
    try {
        const raw = localStorage.getItem('skaribot_chat_history');
        const history = raw ? JSON.parse(raw) : [];
        const now = Date.now();
        const valid = Array.isArray(history)
            ? history.filter(item => item && item.timestamp && (now - item.timestamp < ONE_WEEK_MS))
            : [];

        valid.push({ sender, text, timestamp: now });
        localStorage.setItem('skaribot_chat_history', JSON.stringify(valid));
    } catch (e) {
        console.error('Failed to save chat message:', e);
    }
}

chatForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (isBusy) return;

    const message = messageInput.value.trim();
    if (!message) return;

    setChatBusy(true);

    appendMessage('user', message, false);
    saveChatMessage('user', message);

    messageInput.value = '';
    resizeTextarea();

    const typingBubble = appendTyping();

    try {
        const res = await fetch("{{ route('chat.ask') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': "{{ csrf_token() }}"
            },
            body: JSON.stringify({
                message
            })
        });

        if (!res.ok) throw new Error("Server Error");

        const data = await res.json();
        typingBubble.remove();
        appendMessage('bot', data.reply, true, () => {
            setChatBusy(false);
        });
        saveChatMessage('bot', data.reply);
    } catch (err) {
        typingBubble.remove();
        const errMsg = "Maaf, ada gangguan koneksi. Coba lagi ya!";
        appendMessage('bot', errMsg, true, () => {
            setChatBusy(false);
        });
        console.error(err);
    }
});

messageInput.addEventListener('keydown', (e) => {
    if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        if (!isBusy) {
            chatForm.dispatchEvent(new Event('submit'));
        }
    }
});

function scrollToBottom() {
    chatbox.scrollTop = chatbox.scrollHeight;
}

function formatBotMessage(text) {
    if (!text) return '';
    return text
        .replace(/\\([*_`~[\]()#\\])/g, '$1')
        .replace(/^[ \t]*[-*]\s+/gm, '• ')
        .replace(/^#{1,6}\s*(.+)$/gm, '<b>$1</b>')
        .replace(/\*\*(.*?)\*\*/g, '<b>$1</b>')
        .replace(/(^|[^*])\*(?!\*)([^*\n]+)\*(?!\*)/g, '$1<b>$2</b>')
        .replace(/`{1,3}(.*?)`{1,3}/g, '$1');
}

function appendMessage(sender, text, animate = false, onComplete = null) {
    const content = sender === 'bot' ? formatBotMessage(text) : text;
    const div = document.createElement('div');
    div.classList.add('bubble', sender);
    chatbox.appendChild(div);

    if (!animate) {
        div.innerHTML = content;
        scrollToBottom();
        if (onComplete) onComplete();
        return;
    }

    const tokens = content.match(/<[^>]+>|[^<]/g) || [];
    if (tokens.length === 0) {
        div.innerHTML = content;
        scrollToBottom();
        if (onComplete) onComplete();
        return;
    }

    const total = tokens.length;
    const step = Math.max(1, Math.ceil(total / 40));
    let currentIndex = 0;

    const timer = setInterval(() => {
        currentIndex = Math.min(total, currentIndex + step);
        div.innerHTML = tokens.slice(0, currentIndex).join('');
        scrollToBottom();

        if (currentIndex >= total) {
            clearInterval(timer);
            div.innerHTML = content;
            scrollToBottom();
            if (onComplete) onComplete();
        }
    }, 20);
}

function appendTyping() {
    const typing = document.createElement('div');
    typing.classList.add('bubble', 'bot');
    typing.innerHTML = `<div class="typing"><span></span><span></span><span></span></div>`;
    chatbox.appendChild(typing);
    scrollToBottom();
    return typing;
}

const questions = [
    "Apa saja jurusannya?",
    "Bagaimana cara mendaftar?",
    "Dimana letak sekolahnya?",
    "Fasilitas sekolah apa saja?",
    "Apa visi dan misinya?",
    "Ekskulnya ada apa saja?"
];
let currentQuestionIndex = 0;

setInterval(() => {
    questionElement.classList.add('opacity-0');
    setTimeout(() => {
        currentQuestionIndex = (currentQuestionIndex + 1) % questions.length;
        questionElement.textContent = questions[currentQuestionIndex];
        questionElement.classList.remove('opacity-0');
    }, 300);
}, 4000);

document.addEventListener('DOMContentLoaded', () => {
    questionElement.textContent = questions[0];
    loadChatHistory();
});
</script>
