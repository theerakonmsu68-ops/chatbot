/**
 * พี่สารคาม AI - Render Production Edition
 * Enhanced & Secure Frontend Version
 */

document.addEventListener('DOMContentLoaded', () => {

    // =====================================================
    // SVG Icons Reference
    // =====================================================
    const ICONS = {
        delete: `<svg class="w-4 h-4 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>`,
        loader: `<svg class="w-5 h-5 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>`,
        aiLogo: `<svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>`
    };

    // =====================================================
    // Sidebar Management
    // =====================================================
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const openSidebarBtn = document.getElementById('open-sidebar');
    const closeSidebarBtn = document.getElementById('close-sidebar');

    const toggleSidebar = (show) => {
        if (!sidebar) return;
        if (show) {
            sidebar.classList.remove('-translate-x-full');
            overlay?.classList.remove('hidden');
        } else {
            sidebar.classList.add('-translate-x-full');
            overlay?.classList.add('hidden');
        }
    };

    openSidebarBtn?.addEventListener('click', () => toggleSidebar(true));
    closeSidebarBtn?.addEventListener('click', () => toggleSidebar(false));
    overlay?.addEventListener('click', () => toggleSidebar(false));

    // =====================================================
    // Chat Elements & State
    // =====================================================
    const chatBox = document.getElementById('chat-box');
    if (!chatBox) return;

    const msgContainer = document.getElementById('msg-container');
    const userInput = document.getElementById('user-input');
    const sendBtn = document.getElementById('send-btn');
    const welcome = document.getElementById('welcome');

    let currentChatId = null;
    let pendingDeleteChatId = null;
    let isGenerating = false;

    // =====================================================
    // Security & Parsing Utilities
    // =====================================================
    function escapeHTML(str) {
        if (!str) return "";
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function linkify(text) {
        if (!text) return "";
        const escaped = escapeHTML(text);
        const urlRegex = /(https?:\/\/[^\s]+)/g;
        return escaped
            .replace(urlRegex, '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 underline font-medium break-all">$1</a>')
            .replace(/\n/g, '<br>');
    }

    function scrollToBottom() {
        if (!chatBox) return;
        setTimeout(() => {
            chatBox.scrollTo({
                top: chatBox.scrollHeight,
                behavior: 'smooth'
            });
        }, 50);
    }

    // =====================================================
    // New Chat
    // =====================================================
    window.newChat = function () {
        if (isGenerating) return;
        
        currentChatId = null;

        if (msgContainer) msgContainer.innerHTML = '';
        if (welcome) welcome.style.display = 'block';

        if (userInput) {
            userInput.value = '';
            userInput.style.height = 'auto';
            userInput.focus();
        }

        if (window.innerWidth < 768 && sidebar && !sidebar.classList.contains('-translate-x-full')) {
            toggleSidebar(false);
        }
    };

    // =====================================================
    // Load Chat History
    // =====================================================
    window.loadChat = async function (chatId) {
        if (isGenerating) return;
        currentChatId = chatId;

        if (welcome) welcome.style.display = 'none';

        if (msgContainer) {
            msgContainer.innerHTML = `
                <div class="flex flex-col items-center justify-center py-20 opacity-60">
                    ${ICONS.loader}
                    <p class="text-xs font-medium text-gray-500 mt-3 tracking-wider">กำลังโหลดการสนทนา...</p>
                </div>
            `;
        }

        if (window.innerWidth < 768) toggleSidebar(false);

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'fetch', chat_id: chatId })
            });

            const data = await response.json();
            if (data.error) throw new Error(data.error);

            if (msgContainer) msgContainer.innerHTML = '';

            data.history.forEach(item => {
                appendBubble('user', item.message);
                appendBubble('ai', item.reply);
            });

            scrollToBottom();

        } catch (error) {
            console.error("Load History Error:", error);
            if (msgContainer) {
                msgContainer.innerHTML = `
                    <div class="p-4 my-4 bg-red-50 rounded-xl text-center">
                        <p class="text-red-500 text-sm font-medium">ไม่สามารถโหลดประวัติการสนทนาได้</p>
                    </div>
                `;
            }
        }
    };

    // =====================================================
    // Delete Modal & Operations
    // =====================================================
    const deleteModal = document.getElementById('gemini-delete-modal');
    const modalCancelBtn = document.getElementById('modal-cancel-btn');
    const modalConfirmBtn = document.getElementById('modal-confirm-btn');
    const geminiToast = document.getElementById('gemini-toast');

    window.deleteChat = function (chatId, event) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }

        pendingDeleteChatId = chatId;
        if (deleteModal) deleteModal.classList.add('active');
    };

    function closeDeleteModal() {
        if (deleteModal) deleteModal.classList.remove('active');
        pendingDeleteChatId = null;
    }

    modalCancelBtn?.addEventListener('click', closeDeleteModal);

    deleteModal?.addEventListener('click', (e) => {
        if (e.target === deleteModal) closeDeleteModal();
    });

    modalConfirmBtn?.addEventListener('click', async () => {
        if (!pendingDeleteChatId) return;

        const targetId = pendingDeleteChatId;
        closeDeleteModal();

        try {
            const response = await fetch('remove_room.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ chat_id: targetId })
            });

            const data = await response.json();

            if (data.status === "success") {
                const item = document.getElementById(`item-${targetId}`);
                if (item) {
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(-20px)';
                    setTimeout(() => item.remove(), 300);
                }

                if (currentChatId === targetId) {
                    currentChatId = null;
                    if (msgContainer) msgContainer.innerHTML = '';
                    if (welcome) welcome.style.display = 'block';
                }

                if (geminiToast) {
                    geminiToast.classList.remove('translate-y-20', 'opacity-0');
                    setTimeout(() => {
                        geminiToast.classList.add('translate-y-20', 'opacity-0');
                    }, 2500);
                }
            } else {
                alert('ไม่สามารถลบห้องสนทนาได้');
            }
        } catch (error) {
            console.error("Delete Error:", error);
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
        }
    });

    // =====================================================
    // Render Chat Bubble
    // =====================================================
    function appendBubble(sender, text, id = null) {
        if (!msgContainer) return;

        const wrapper = document.createElement('div');
        wrapper.className = `flex w-full ${sender === 'user' ? 'justify-end' : 'justify-start'} mb-6 msg-animate`;

        if (id) wrapper.id = id;

        const avatar = sender === 'user'
            ? `<img src="${window.userPic || ''}" referrerpolicy="no-referrer" class="w-8 h-8 rounded-full border border-gray-200 object-cover shadow-sm" onerror="this.src='https://ui-avatars.com/api/?name=User&background=0D8ABC&color=fff'">`
            : `<div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center border border-blue-100 text-blue-600 font-bold shadow-sm">${ICONS.aiLogo}</div>`;

        const bubbleClass = sender === 'user'
            ? `bg-blue-600 text-white rounded-[20px_20px_4px_20px] px-5 py-3 shadow-sm max-w-[85%] break-words`
            : `text-gray-800 pt-1 content-area w-full leading-relaxed text-[16px] max-w-[85%] break-words`;

        const parsedContent = sender === 'user' ? escapeHTML(text).replace(/\n/g, '<br>') : linkify(text);

        wrapper.innerHTML = `
            <div class="flex ${sender === 'user' ? 'flex-row-reverse' : 'flex-row'} gap-3 items-start w-full">
                <div class="shrink-0 mt-1">${avatar}</div>
                <div class="${bubbleClass}">${parsedContent}</div>
            </div>
        `;

        msgContainer.appendChild(wrapper);
        scrollToBottom();
    }

    // =====================================================
    // Send Message Logic
    // =====================================================
    async function send() {
        if (!userInput || isGenerating) return;

        const text = userInput.value.trim();
        if (!text) return;

        isGenerating = true;
        if (sendBtn) sendBtn.disabled = true;

        if (welcome) welcome.style.display = 'none';

        appendBubble('user', text);

        userInput.value = '';
        userInput.style.height = 'auto';

        const aiId = 'ai-' + Date.now();

        const typingHTML = `
            <div class="flex gap-1.5 items-center px-4 py-3 bg-gray-100 rounded-2xl border border-gray-200/50 w-max">
                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce [animation-delay:0.2s]"></div>
                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce [animation-delay:0.4s]"></div>
            </div>
        `;

        appendBubble('ai', '', aiId);
        const typingBubble = document.getElementById(aiId);
        if (typingBubble) {
            const contentArea = typingBubble.querySelector('.content-area');
            if (contentArea) contentArea.innerHTML = typingHTML;
        }

        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 60000);

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                signal: controller.signal,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'chat',
                    message: text,
                    chat_id: currentChatId
                })
            });

            clearTimeout(timeout);
            const data = await response.json();

            if (data.error) throw new Error(data.error);

            if (!currentChatId && data.chat_id) {
                currentChatId = data.chat_id;
                updateSidebarRealtime(data.chat_id, text);
            }

            const aiBubble = document.getElementById(aiId);
            if (aiBubble) {
                const contentArea = aiBubble.querySelector('.content-area');
                if (contentArea) {
                    contentArea.innerHTML = `
                        <div class="opacity-0 transition-opacity duration-300" id="fade-${aiId}">
                            ${linkify(data.reply || '')}
                        </div>
                    `;
                    setTimeout(() => {
                        document.getElementById(`fade-${aiId}`)?.classList.remove('opacity-0');
                    }, 10);
                }
            }

        } catch (error) {
            console.error("Chat Error:", error);
            const aiBubble = document.getElementById(aiId);
            if (aiBubble) {
                const area = aiBubble.querySelector('.content-area');
                if (area) {
                    area.innerHTML = `<span class="text-red-500 font-medium">ขออภัยครับ ระบบเกิดข้อผิดพลาดในการเชื่อมต่อ</span>`;
                }
            }
        } finally {
            isGenerating = false;
            if (sendBtn) sendBtn.disabled = false;
            scrollToBottom();
        }
    }

    // =====================================================
    // Update Sidebar
    // =====================================================
    function updateSidebarRealtime(chatId, message) {
        const historyList = document.getElementById('history-list');
        if (!historyList) return;

        const div = document.createElement('div');
        div.id = `item-${chatId}`;
        div.className = `sidebar-item group flex items-center justify-between p-3 text-sm text-gray-700 cursor-pointer rounded-xl transition-all hover:bg-gray-100/80 mb-1`;

        div.innerHTML = `
            <span onclick="loadChat('${chatId}')" class="truncate flex-1 font-medium pr-2">
                ${escapeHTML(message)}
            </span>
            <button onclick="deleteChat('${chatId}', event)" class="opacity-0 group-hover:opacity-100 p-1 text-gray-400 hover:text-red-500 transition-all rounded-md hover:bg-red-50" title="ลบการสนทนา">
                ${ICONS.delete}
            </button>
        `;

        historyList.prepend(div);
    }

    // =====================================================
    // Event Listeners
    // =====================================================
    if (sendBtn) sendBtn.onclick = send;

    if (userInput) {
        userInput.onkeydown = (e) => {
            if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                send();
            }
        };

        userInput.oninput = function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 150) + 'px';
        };

        userInput.focus();
    }
});
