<!-- SmartParking: Floating Assistant UI -->
<!-- Marked.js for Markdown → HTML rendering -->
<script src="https://cdn.jsdelivr.net/npm/marked@9/marked.min.js"></script>

<div id="archive-ai-root" class="relative z-[999999]">
    
    <!-- Chat Window (Glassmorphism) -->
    <div id="ai-chat-window" class="hidden fixed bottom-24 right-8 flex flex-col w-[320px] h-[460px] bg-surface/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-color overflow-hidden transition-all duration-300 transform translate-y-8 opacity-0 origin-bottom-right z-[999999]" style="will-change: transform, opacity; -webkit-font-smoothing: antialiased; backface-visibility: hidden;">
        <!-- Header -->
        <div class="px-4 py-3 flex items-center justify-between flex-shrink-0 bg-brand">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-white/10 rounded-xl flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="white" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
                    </svg>
                </div>
                <div>
                    <h3 class="font-manrope font-extrabold text-white text-sm leading-tight" id="ai-view-title">Cereza</h3>
                    <p class="text-white/70 text-[13px] font-medium font-inter" id="ai-view-status">SmartParking Assistant</p>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <!-- Back to Chat (only visible in history) -->
                <button id="ai-btn-back" onclick="switchView('chat')" class="hidden w-8 h-8 rounded-lg flex items-center justify-center text-white/50 hover:text-white hover:bg-white/10 transition-all" title="Back to Chat">
                    <i class="fa-solid fa-message text-xs"></i>
                </button>
                <!-- History Button -->
                <button id="ai-btn-history" onclick="switchView('history')" class="w-8 h-8 rounded-lg flex items-center justify-center text-white/50 hover:text-white hover:bg-white/10 transition-all" title="Past Conversations">
                    <i class="fa-solid fa-clock-rotate-left text-xs"></i>
                </button>
                <!-- New Chat Button -->
                <button onclick="createNewSession()" class="w-8 h-8 rounded-lg flex items-center justify-center text-white/50 hover:text-white hover:bg-white/10 transition-all" title="New Conversation">
                    <i class="fa-solid fa-plus text-sm"></i>
                </button>
            </div>
        </div>

        <!-- History View (Hidden by default) -->
        <div id="ai-history-area" class="hidden flex-1 overflow-y-auto p-4 space-y-2 ai-scrollbar bg-page">
            <!-- Session items will be injected here -->
        </div>

        <!-- Chat View -->
        <div id="ai-chat-area" class="flex flex-col flex-1 overflow-hidden bg-surface">
            <!-- Message Area -->
            <div id="ai-message-area" class="flex-1 overflow-y-auto p-4 space-y-3 ai-scrollbar"></div>

            <!-- Input Area -->
            <div class="p-3 bg-surface-alt border-t border-color flex-shrink-0">
                <form id="ai-chat-form" class="relative group flex items-center gap-2">
                    <input type="text" id="ai-user-input" 
                           placeholder="Ask something about SmartParking..." 
                           class="flex-1 bg-surface border border-color rounded-xl px-4 py-2.5 text-[13px] font-inter text-primary placeholder-secondary focus:outline-none focus:border-brand transition-colors"
                           autocomplete="off">
                    <button type="submit" class="w-9 h-9 text-white rounded-xl flex items-center justify-center transition-all flex-shrink-0 bg-brand">
                        <i class="fa-solid fa-paper-plane text-lg"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Cereza FAB: Fixed circular button at bottom-right -->
    <button id="cereza-fab" onclick="toggleAIChat()"
            title="Ask Cereza"
            style="position:fixed; bottom:28px; right:28px; z-index:999999; width:52px; height:52px; border-radius:50%; background:var(--brand); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; transition: transform 0.2s ease;"
            onmouseover="this.style.transform='scale(1.08)';"
            onmouseout="this.style.transform='scale(1)';">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="white" style="width:28px; height:28px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
        </svg>
    </button>

<script>
// Configure marked.js for safe rendering
marked.setOptions({
    breaks: true,
    gfm: true,
    sanitize: false
});

let aiChatVisible = false;
const ACTIVE_KEY = 'smartparking_active_session';
const HISTORY_KEY = 'smartparking_chat_history';

function formatWIB(dateString) {
    const date = new Date(dateString);
    return date.toLocaleTimeString('id-ID', { 
        hour: '2-digit', 
        minute: '2-digit', 
        hour12: false,
        timeZone: 'Asia/Jakarta' 
    }).replace('.', ':') + ' WIB';
}

function switchView(view) {
    const chatArea = document.getElementById('ai-chat-area');
    const historyArea = document.getElementById('ai-history-area');
    const title = document.getElementById('ai-view-title');
    const status = document.getElementById('ai-view-status');
    const btnBack = document.getElementById('ai-btn-back');
    const btnHist = document.getElementById('ai-btn-history');

    if (view === 'history') {
        renderHistory();
        chatArea.classList.add('hidden');
        historyArea.classList.remove('hidden');
        title.textContent = 'History';
        status.textContent = 'Past Conversations';
        btnBack.classList.remove('hidden');
        btnHist.classList.add('hidden');
    } else {
        chatArea.classList.remove('hidden');
        historyArea.classList.add('hidden');
        title.textContent = 'Cereza';
        status.textContent = 'SmartParking Assistant';
        btnBack.classList.add('hidden');
        btnHist.classList.remove('hidden');
    }
}

function createNewSession() {
    saveCurrentToHistory();
    localStorage.removeItem(ACTIVE_KEY);
    const area = document.getElementById('ai-message-area');
    area.innerHTML = '';
    switchView('chat');
    showInitialGreeting();
}

function showInitialGreeting() {
    const loadingId = 'ai-initial-loading';
    showTyping(loadingId);
    setTimeout(() => {
        const initialText = "Hello, I'm **Cereza**. I manage the unified parking data for your enterprise network. Currently analyzing live feeds from **Berserk Mall**. How can I assist you today?";
        appendMessage('bot', initialText, true, null, true, false, loadingId);
    }, 800);
}


function saveCurrentToHistory() {
    const active = localStorage.getItem(ACTIVE_KEY);
    if (!active) return;

    const messages = JSON.parse(active);
    if (messages.length <= 1) return; // Only bot greeting, don't save

    const history = JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]');
    
    const firstUserMsg = messages.find(m => m.role === 'user');
    const title = firstUserMsg ? (firstUserMsg.text.substring(0, 35) + '...') : 'New Conversation';
    
    const session = {
        id: 'sess_' + Date.now(),
        title: title,
        ts: messages[messages.length - 1].ts,
        messages: messages
    };

    history.unshift(session);
    if (history.length > 5) history.pop(); 

    localStorage.setItem(HISTORY_KEY, JSON.stringify(history));
}

function renderHistory() {
    const history = JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]');
    const container = document.getElementById('ai-history-area');
    container.innerHTML = '';

    if (history.length === 0) {
        container.innerHTML = `
            <div class="flex flex-col items-center justify-center h-full opacity-30 text-secondary gap-2">
                <i class="fa-solid fa-box-archive text-4xl"></i>
                <p class="text-xs font-medium">No history found</p>
            </div>
        `;
        return;
    }

    history.forEach(sess => {
        const item = document.createElement('div');
        item.className = 'bg-surface p-3 rounded-xl border border-color hover:border-brand transition-all cursor-pointer group flex items-center justify-between gap-3';
        item.onclick = () => loadSessionFromHistory(sess.id);
        
        item.innerHTML = `
            <div class="flex-1 overflow-hidden">
                <h4 class="text-[13px] font-bold text-primary truncate">${sess.title}</h4>
                <p class="text-[10px] text-secondary mt-0.5">${formatWIB(sess.ts)}</p>
            </div>
            <button onclick="deleteSession(event, '${sess.id}')" class="w-8 h-8 flex items-center justify-center rounded-lg text-secondary hover:text-red-500 hover:bg-red-500/10 transition-all opacity-0 group-hover:opacity-100">
                <i class="fa-solid fa-trash-can text-xs"></i>
            </button>
        `;
        container.appendChild(item);
    });
}

function loadSessionFromHistory(id) {
    const history = JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]');
    const sess = history.find(s => s.id === id);
    if (!sess) return;

    saveCurrentToHistory();
    localStorage.setItem(ACTIVE_KEY, JSON.stringify(sess.messages));
    
    const newHistory = history.filter(s => s.id !== id);
    localStorage.setItem(HISTORY_KEY, JSON.stringify(newHistory));

    loadAISession();
    switchView('chat');
}

function deleteSession(e, id) {
    e.stopPropagation();
    if (!confirm('Delete this conversation?')) return;
    
    const history = JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]');
    const newHistory = history.filter(s => s.id !== id);
    localStorage.setItem(HISTORY_KEY, JSON.stringify(newHistory));
    renderHistory();
}

function loadAISession() {
    const area = document.getElementById('ai-message-area');
    const session = localStorage.getItem(ACTIVE_KEY);
    
    if (session) {
        area.innerHTML = '';
        const messages = JSON.parse(session);
        messages.forEach(msg => appendMessage(msg.role, msg.text, false, msg.ts, false, false));
        
        // Restore scroll position after loading messages
        const savedScroll = localStorage.getItem('smartparking_scroll_pos');
        if (savedScroll !== null) {
            area.scrollTop = parseInt(savedScroll);
        }
    }
}

function saveAISession(role, text, ts) {
    const session = localStorage.getItem(ACTIVE_KEY);
    const messages = session ? JSON.parse(session) : [];
    messages.push({ role, text, ts });
    localStorage.setItem(ACTIVE_KEY, JSON.stringify(messages));
}

function toggleAIChat() {
    const win = document.getElementById('ai-chat-window');
    const input = document.getElementById('ai-user-input');
    const area = document.getElementById('ai-message-area');

    if (aiChatVisible) {
        // Save scroll position before closing
        localStorage.setItem('smartparking_scroll_pos', area.scrollTop);
        
        win.classList.add('translate-y-8', 'opacity-0');
        setTimeout(() => win.classList.add('hidden'), 280);
    } else {
        win.classList.remove('hidden');
        
        // Initial Greeting with typing animation for new sessions
        if (area.children.length === 0 && !localStorage.getItem(ACTIVE_KEY)) {
            // Wait for window animation (300ms) to finish before starting typing
            setTimeout(showInitialGreeting, 350);
        }

        // Restore scroll position before showing
        const savedScroll = localStorage.getItem('smartparking_scroll_pos');
        if (savedScroll !== null) {
            area.scrollTop = savedScroll;
        } else {
            area.scrollTop = area.scrollHeight;
        }
        
        requestAnimationFrame(() => {
            win.classList.remove('translate-y-8', 'opacity-0');
        });
        
        setTimeout(() => {
            if (input) input.focus();
        }, 100);
    }
    aiChatVisible = !aiChatVisible;
}

loadAISession();

document.getElementById('ai-chat-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const input = document.getElementById('ai-user-input');
    const query = input.value.trim();
    if (!query) return;

    appendMessage('user', query, true);
    input.value = '';

    setTimeout(async () => {
        const loadingId = 'ai-loading-' + Date.now();
        showTyping(loadingId);

        try {
            const response = await fetch('<?= BASE_URL ?>api/ai_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ query: query })
            });
            
            const data = await response.json();
            if (data.error) throw new Error(data.error);
            appendMessage('bot', data.response, true, null, true, false, loadingId);
        } catch (err) {
            appendMessage('bot', '⚠️ **An error occurred:**\n\n' + err.message, false, null, true, false, loadingId);
        }
    }, 1200);
});

function appendMessage(role, text, save = true, ts = null, shouldScroll = true, animate = true, replaceId = null) {
    const area = document.getElementById('ai-message-area');
    const wrapper = document.createElement('div');
    const animClass = animate ? 'ai-msg-anim' : '';
    wrapper.className = `flex flex-col ${role === 'user' ? 'items-end' : 'items-start'} gap-1 ${animClass}`;

    const bubble = document.createElement('div');
    const timestamp = ts || new Date().toISOString();

    if (role === 'user') {
        bubble.className = 'ai-bubble-user';
        bubble.textContent = text;
    } else {
        bubble.className = 'ai-bubble-bot';
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

    const tsEl = document.createElement('span');
    tsEl.className = 'ai-timestamp';
    tsEl.textContent = formatWIB(timestamp);

    wrapper.appendChild(bubble);
    wrapper.appendChild(tsEl);

    if (replaceId) {
        const oldEl = document.getElementById(replaceId);
        if (oldEl) oldEl.replaceWith(wrapper);
        else area.appendChild(wrapper);
    } else {
        area.appendChild(wrapper);
    }
    
    if (shouldScroll) {
        area.scrollTop = area.scrollHeight;
    }

    if (save) saveAISession(role, text, timestamp);
}

function showTyping(id) {
    const area = document.getElementById('ai-message-area');
    const div = document.createElement('div');
    div.id = id;
    div.className = 'flex flex-col items-start gap-1 ai-msg-anim';
    div.innerHTML = `
        <div class="ai-bubble-bot flex items-center gap-1.5" style="padding: 10px 12px; min-height: 35.5px;">
            <span class="ai-typing-dot"></span>
            <span class="ai-typing-dot" style="animation-delay:.15s"></span>
            <span class="ai-typing-dot" style="animation-delay:.3s"></span>
        </div>
        <span class="ai-timestamp" style="visibility: hidden;">00:00 WIB</span>
    `;
    area.appendChild(div);
    area.scrollTop = area.scrollHeight;
}

function removeTyping(id) {
    const el = document.getElementById(id);
    if (el) el.remove();
}

document.addEventListener('click', function(e) {
    const win = document.getElementById('ai-chat-window');
    const area = document.getElementById('ai-message-area');
    const isToggle = e.target.closest('#cereza-fab') || e.target.closest('button[onclick="toggleAIChat()"]');
    if (aiChatVisible && !win.contains(e.target) && !isToggle) {
        // Save scroll position before auto-closing
        localStorage.setItem('smartparking_scroll_pos', area.scrollTop);
        toggleAIChat();
    }
});

// Also save scroll position on window unload to be safe
window.addEventListener('beforeunload', () => {
    const area = document.getElementById('ai-message-area');
    if (area) {
        localStorage.setItem('smartparking_scroll_pos', area.scrollTop);
    }
});
</script>

<style>
/* ── Chat Window & Table Scrollbars ─────────── */
.ai-scrollbar::-webkit-scrollbar, .ai-table-scroll::-webkit-scrollbar { width: 3px; height: 3px; }
.ai-scrollbar::-webkit-scrollbar-track, .ai-table-scroll::-webkit-scrollbar-track { background: transparent; }

.ai-scrollbar::-webkit-scrollbar-thumb:vertical,
.ai-table-scroll::-webkit-scrollbar-thumb:vertical { 
    background: rgba(99, 102, 241, 0.4); 
    border-radius: 10px; 
    min-height: 32px;
    max-height: 32px;
    height: 32px;
}

.ai-scrollbar::-webkit-scrollbar-thumb:horizontal,
.ai-table-scroll::-webkit-scrollbar-thumb:horizontal { 
    background: rgba(99, 102, 241, 0.4); 
    border-radius: 10px; 
    min-width: 32px;
    max-width: 32px;
    width: 32px;
}

.ai-scrollbar::-webkit-scrollbar-thumb:hover, .ai-table-scroll::-webkit-scrollbar-thumb:hover { background: var(--brand); }

/* ── Bubble Base ───────────────────────────── */
.ai-bubble-bot {
    background: var(--surface-alt);
    color: var(--text-primary);
    border-radius: 0 16px 16px 16px;
    padding: 8px 12px;
    font-size: 13px;
    line-height: 1.5;
    max-width: 96%;
    min-width: 0;
    overflow: hidden;
    font-family: 'Inter', sans-serif;
    word-break: break-word;
}
.ai-table-scroll {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin: 6px 0;
    border-radius: 8px;
    box-shadow: 0 1px 3px var(--shadow-color);
}
.ai-bubble-user {
    background: var(--brand);
    color: white;
    border-radius: 16px 16px 0 16px;
    padding: 8px 12px;
    font-size: 13px;
    line-height: 1.5;
    max-width: 88%;
    font-family: 'Inter', sans-serif;
}
.ai-timestamp {
    font-size: 9px;
    color: var(--text-secondary);
    font-family: 'Inter', sans-serif;
    letter-spacing: .04em;
    padding: 0 2px;
}

/* ── Markdown Rendering ──── */
.ai-bubble-bot h1, .ai-bubble-bot h2, .ai-bubble-bot h3 {
    font-weight: 700;
    margin: 10px 0 4px;
    line-height: 1.3;
    color: var(--text-primary);
}
.ai-bubble-bot h1 { font-size: 13px; font-weight: 800; }
.ai-bubble-bot h2 { font-size: 13px; font-weight: 800; padding-bottom: 2px; }
.ai-bubble-bot h3 { font-size: 13px; color: var(--text-primary); font-weight: 800; }
.ai-bubble-bot p { margin-bottom: 0.75rem; }
.ai-bubble-bot em { font-style: italic; color: var(--text-secondary); }
.ai-bubble-bot ul, .ai-bubble-bot ol { margin-bottom: 1rem; padding-left: 1.25rem; }
.ai-bubble-bot li { margin: 3px 0; }
.ai-bubble-bot hr { border: none; border-top: 1px solid var(--border-color); margin: 10px 0; }
.ai-bubble-bot code { background: var(--border-color); border-radius: 4px; padding: 1px 5px; font-family: monospace; font-size: 11px; color: var(--text-primary); }
.ai-bubble-bot blockquote { border-left: 3px solid var(--text-secondary); padding-left: 10px; color: var(--text-secondary); margin: 6px 0; font-style: italic; }

.ai-table-scroll table { border-collapse: collapse; font-size: 11px; min-width: 100%; table-layout: auto; }
.ai-table-scroll thead { background: var(--brand); color: white; }
.ai-table-scroll thead th { padding: 6px 10px; text-align: left; font-weight: 800; white-space: nowrap; font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; }
.ai-table-scroll tbody tr { border-bottom: 1px solid var(--border-color); transition: background .15s; }
.ai-table-scroll tbody tr:hover { background: var(--surface-alt); }
.ai-table-scroll tbody td { padding: 5px 10px; color: var(--text-primary); vertical-align: middle; white-space: nowrap; }
.ai-table-scroll tbody tr:nth-child(even) { background: var(--bg-page); }

.ai-typing-dot { width: 4px; height: 4px; background: var(--brand); border-radius: 50%; opacity: 0.3; display: inline-block; animation: aiDotBounce .9s infinite ease-in-out; }
@keyframes aiDotBounce { 0%, 60%, 100% { transform: translateY(0); } 30% { transform: translateY(-6px); } }

.ai-msg-anim { 
    opacity: 0; 
    animation: aiBubbleIn 0.4s cubic-bezier(0.1, 0.8, 0.2, 1) forwards; 
    backface-visibility: hidden; 
    will-change: transform, opacity;
}
@keyframes aiBubbleIn { 
    from { opacity: 0; transform: translateY(8px); } 
    to { opacity: 1; transform: translateY(0); } 
}

/* Star icon — no animation */
.star-path { transform-origin: center; transform-box: fill-box; }
</style>
