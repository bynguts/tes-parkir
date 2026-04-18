<!-- Archive AI: Floating Assistant UI -->
<!-- Marked.js for Markdown → HTML rendering -->
<script src="https://cdn.jsdelivr.net/npm/marked@9/marked.min.js"></script>

<div id="archive-ai-root" class="fixed bottom-8 right-8 z-[9999] flex flex-col items-end gap-4">
    
    <!-- Chat Window (Glassmorphism) -->
    <div id="ai-chat-window" class="hidden flex flex-col w-[420px] h-[580px] bg-white/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-white/50 overflow-hidden transition-all duration-300 transform scale-95 opacity-0 origin-bottom-right">
        
        <!-- Header -->
        <div class="bg-slate-900 px-5 py-4 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-white/10 rounded-xl flex items-center justify-center">
                    <i class="fa-solid fa-wand-magic-sparkles text-white text-base"></i>
                </div>
                <div>
                    <h3 class="font-manrope font-extrabold text-white text-sm leading-tight">Archive AI</h3>
                    <p class="text-slate-400 text-[9px] uppercase tracking-widest font-inter">Live Enterprise Support</p>
                </div>
            </div>
            <button onclick="toggleAIChat()" class="text-slate-400 hover:text-white transition-colors">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Message Area -->
        <div id="ai-message-area" class="flex-1 overflow-y-auto p-4 space-y-3 ai-scrollbar">
            <!-- Bot Greeting -->
            <div class="flex flex-col items-start gap-1">
                <div class="ai-bubble-bot">
                    Halo, saya <strong>Archive AI</strong>. Ada yang bisa saya bantu terkait operasional SmartParking hari ini? Saya bisa menganalisis data pendapatan, slot, atau memberikan saran strategis.
                </div>
                <span class="ai-timestamp">System • Now</span>
            </div>
        </div>

        <!-- Input Area -->
        <div class="p-3 bg-slate-50 border-t border-slate-100 flex-shrink-0">
            <form id="ai-chat-form" class="relative group flex items-center gap-2">
                <input type="text" id="ai-user-input" 
                       placeholder="Tanyakan sesuatu tentang parkir..." 
                       class="flex-1 bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-inter text-slate-900 placeholder-slate-400 focus:outline-none focus:border-slate-400 transition-colors"
                       autocomplete="off">
                <button type="submit" class="w-9 h-9 bg-slate-900 text-white rounded-xl flex items-center justify-center hover:bg-slate-700 transition-all flex-shrink-0">
                    <i class="fa-solid fa-paper-plane text-[13px]"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- FAB Button -->
    <button id="ai-fab" onclick="toggleAIChat()" class="relative w-14 h-14 bg-slate-900 border border-slate-700 rounded-full shadow-[0_8px_32px_rgba(15,23,42,0.3)] flex items-center justify-center hover:scale-110 active:scale-95 transition-all group ai-breathing">
        <i class="fa-solid fa-wand-magic-sparkles text-white text-xl transition-transform group-hover:rotate-12"></i>
    </button>
</div>

<script>
// Configure marked.js for safe rendering
marked.setOptions({
    breaks: true,
    gfm: true,
    sanitize: false
});

let aiChatVisible = false;

function toggleAIChat() {
    const win = document.getElementById('ai-chat-window');
    const fab = document.getElementById('ai-fab');
    if (aiChatVisible) {
        win.classList.add('scale-95', 'opacity-0');
        setTimeout(() => win.classList.add('hidden'), 280);
        fab.querySelector('i').style.transform = '';
    } else {
        win.classList.remove('hidden');
        requestAnimationFrame(() => {
            requestAnimationFrame(() => win.classList.remove('scale-95', 'opacity-0'));
        });
    }
    aiChatVisible = !aiChatVisible;
}

document.getElementById('ai-chat-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const input = document.getElementById('ai-user-input');
    const query = input.value.trim();
    if (!query) return;

    appendMessage('user', query);
    input.value = '';

    const loadingId = 'ai-loading-' + Date.now();
    showTyping(loadingId);

    try {
        const response = await fetch('<?= BASE_URL ?>api/ai_chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query: query })
        });
        
        const data = await response.json();
        removeTyping(loadingId);
        
        if (data.error) throw new Error(data.error);
        appendMessage('bot', data.response);
    } catch (err) {
        removeTyping(loadingId);
        appendMessage('bot', '⚠️ **Terjadi kesalahan:**\n\n' + err.message);
    }
});

function appendMessage(role, text) {
    const area = document.getElementById('ai-message-area');
    const wrapper = document.createElement('div');
    wrapper.className = `flex flex-col ${role === 'user' ? 'items-end' : 'items-start'} gap-1 ai-msg-anim`;

    const bubble = document.createElement('div');

    if (role === 'user') {
        bubble.className = 'ai-bubble-user';
        bubble.textContent = text;
    } else {
        bubble.className = 'ai-bubble-bot';
        // Parse Markdown → HTML, then wrap tables in scroll container
        const rendered = marked.parse(text);
        const tmp = document.createElement('div');
        tmp.innerHTML = rendered;
        tmp.querySelectorAll('table').forEach(tbl => {
            const wrapper = document.createElement('div');
            wrapper.className = 'ai-table-scroll';
            tbl.parentNode.insertBefore(wrapper, tbl);
            wrapper.appendChild(tbl);
        });
        bubble.innerHTML = tmp.innerHTML;
    }

    const ts = document.createElement('span');
    ts.className = 'ai-timestamp';
    ts.textContent = (role === 'user' ? 'Anda' : 'Archive AI') + ' • Just now';

    wrapper.appendChild(bubble);
    wrapper.appendChild(ts);
    area.appendChild(wrapper);
    area.scrollTop = area.scrollHeight;
}

function showTyping(id) {
    const area = document.getElementById('ai-message-area');
    const div = document.createElement('div');
    div.id = id;
    div.className = 'flex flex-col items-start gap-1';
    div.innerHTML = `
        <div class="ai-bubble-bot flex items-center gap-1.5 py-3">
            <span class="ai-dot"></span>
            <span class="ai-dot" style="animation-delay:.15s"></span>
            <span class="ai-dot" style="animation-delay:.3s"></span>
        </div>
    `;
    area.appendChild(div);
    area.scrollTop = area.scrollHeight;
}

function removeTyping(id) {
    const el = document.getElementById(id);
    if (el) el.remove();
}
</script>

<style>
/* ── Chat Window Scrollbar ─────────────────── */
.ai-scrollbar::-webkit-scrollbar { width: 4px; }
.ai-scrollbar::-webkit-scrollbar-track { background: transparent; }
.ai-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }

/* ── Bubble Base ───────────────────────────── */
.ai-bubble-bot {
    background: #f1f5f9;
    color: #0f172a;
    border-radius: 0 16px 16px 16px;
    padding: 10px 14px;
    font-size: 13px;
    line-height: 1.6;
    max-width: 96%;
    min-width: 0;
    overflow: hidden;          /* prevent bubble expanding beyond width */
    font-family: 'Inter', sans-serif;
    word-break: break-word;
}
/* Horizontal scroll container for wide tables */
.ai-table-scroll {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin: 8px 0;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}
.ai-bubble-user {
    background: #0f172a;
    color: #f8fafc;
    border-radius: 16px 16px 0 16px;
    padding: 10px 14px;
    font-size: 13px;
    line-height: 1.6;
    max-width: 88%;
    font-family: 'Inter', sans-serif;
}
.ai-timestamp {
    font-size: 9px;
    color: #94a3b8;
    font-family: 'Inter', sans-serif;
    text-transform: uppercase;
    letter-spacing: .04em;
    padding: 0 4px;
}

/* ── Markdown Rendering inside Bot Bubble ──── */
.ai-bubble-bot h1, .ai-bubble-bot h2, .ai-bubble-bot h3 {
    font-weight: 700;
    margin: 10px 0 4px;
    line-height: 1.3;
    color: #0f172a;
}
.ai-bubble-bot h1 { font-size: 15px; }
.ai-bubble-bot h2 { font-size: 14px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
.ai-bubble-bot h3 { font-size: 13px; color: #0f172a; font-weight: 800; }
.ai-bubble-bot p { margin-bottom: 0.75rem; }
.ai-bubble-bot em { font-style: italic; color: rgba(15, 23, 42, 0.6); }
.ai-bubble-bot ul, .ai-bubble-bot ol { margin-bottom: 1rem; padding-left: 1.25rem; }
.ai-bubble-bot li { margin: 3px 0; }
.ai-bubble-bot hr {
    border: none;
    border-top: 1px solid #e2e8f0;
    margin: 10px 0;
}
.ai-bubble-bot code {
    background: #e2e8f0;
    border-radius: 4px;
    padding: 1px 5px;
    font-family: monospace;
    font-size: 11px;
    color: #0f172a;
}
.ai-bubble-bot blockquote {
    border-left: 3px solid #94a3b8;
    padding-left: 10px;
    color: rgba(15, 23, 42, 0.6);
    margin: 6px 0;
    font-style: italic;
}

/* ── Real Tables (inside scroll wrapper) ──── */
.ai-table-scroll table {
    border-collapse: collapse;
    font-size: 11px;
    min-width: 100%;
    table-layout: auto;
}
.ai-table-scroll thead {
    background: #0f172a;
    color: #f8fafc;
}
.ai-table-scroll thead th {
    padding: 6px 10px;
    text-align: left;
    font-weight: 800;
    white-space: nowrap;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}
.ai-table-scroll tbody tr {
    border-bottom: 1px solid #e2e8f0;
    transition: background .15s;
}
.ai-table-scroll tbody tr:hover { background: #f1f5f9; }
.ai-table-scroll tbody tr:last-child { border-bottom: none; }
.ai-table-scroll tbody td {
    padding: 5px 10px;
    color: #334155;
    vertical-align: middle;
    white-space: nowrap;
}
.ai-table-scroll tbody tr:nth-child(even) { background: #f8fafc; }
/* Scrollbar styling for table container */
.ai-table-scroll::-webkit-scrollbar { height: 4px; }
.ai-table-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }

/* ── Typing Indicator Dots ─────────────────── */
.ai-typing-dot {
    width: 4px; height: 4px;
    background: #0f172a;
    border-radius: 50%;
    opacity: 0.3;
    display: inline-block;
    animation: aiDotBounce .9s infinite ease-in-out;
}
@keyframes aiDotBounce {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-6px); }
}

/* ── Message Entrance Animation ─────────────── */
.ai-msg-anim {
    animation: aiBubbleIn 0.3s cubic-bezier(.16,1,.3,1) forwards;
}
@keyframes aiBubbleIn {
    from { opacity: 0; transform: translateY(8px) scale(.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

/* ── AI Breathing Animation ────────────────── */
.ai-breathing {
    animation: aiBreathing 3s ease-in-out infinite;
}
@keyframes aiBreathing {
    0%, 100% { box-shadow: 0 8px 32px rgba(15, 23, 42, 0.3); transform: scale(1); }
    50% { box-shadow: 0 8px 48px rgba(34,197,94,0.25); transform: scale(1.04); }
}
</style>
