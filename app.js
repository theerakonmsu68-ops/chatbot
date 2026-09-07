/**
 * พี่สารคาม AI - Render Production Edition
 * Secure Frontend Version
 */

document.addEventListener('DOMContentLoaded', () => {

    // =====================================================
    // SVG Icons Reference (ใช้ SVG Vector แทน Emoji ทั้งหมด)
    // =====================================================
    const ICONS = {
        delete: `<svg class="w-4 h-4 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>`,
        loader: `<svg class="w-5 h-5 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>`,
        aiLogo: `<svg class="w-4 h-4 text-[#1a73e8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>`
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
    // Security Escape HTML & YouTube Embed Parser
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

        // Regular Expression สำหรับดึง ID ของ YouTube
        const youtubeRegex = /(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})(?:[^\s]*)/g;
        
        let videoCards = '';
        let match;
        const videoIds = new Set();

        // สะสมคลิป YouTube (ล็อกความกว้างบาลานซ์ที่ 280px - 320px พอดีกับ UI)
        while ((match = youtubeRegex.exec(text)) !== null) {
            if (match[1] && !videoIds.has(match[1])) {
                videoIds.add(match[1]);
                const videoId = match[1];
                videoCards += `
                    <div class="my-2.5 overflow-hidden rounded-2xl border border-slate-200/80 bg-slate-900 shadow-sm w-full max-w-[280px] sm:max-w-[320px]">
                        <div class="relative w-full aspect-video">
                            <iframe class="absolute top-0 left-0 w-full h-full rounded-2xl" 
                                src="https://www.youtube-nocookie.com/embed/${videoId}?autoplay=0&rel=0" 
                                title="YouTube video player" 
                                frameborder="0" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                                referrerpolicy="strict-origin-when-cross-origin"
                                allowfullscreen>
                            </iframe>
                        </div>
                    </div>
                `;
            }
        }

        // ลบลิงก์ YouTube ออกจากตัวข้อความ เพื่อไม่ให้ข้อความยาวหรือซ้ำซ้อน
        const cleanText = text.replace(youtubeRegex, '').trim();

        // Escape HTML สำหรับข้อความปกติ
        let htmlContent = escapeHTML(cleanText);

        // แปลง URL ทั่วไปเป็น Hyperlink
        const urlRegex = /(https?:\/\/[^\s]+)/g;
        htmlContent = htmlContent
            .replace(urlRegex, '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 underline font-medium break-all">$1</a>')
            .replace(/\n/g, '<br>');

        return (htmlContent ? `<div>${htmlContent}</div>` : '') + videoCards;
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
                <div class="flex flex-col items-center justify-center py-20 opacity-40">
                    ${ICONS.loader}
                    <p class="text-xs font-medium text-slate-500 mt-3 tracking-wider">FETCHING CONVERSATION</p>
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
                    <p class="text-center text-red-400 py-10 text-sm">ไม่สามารถโหลดประวัติการสนทนาได้</p>
                `;
            }
        }
    };

    // =====================================================
    // Delete Modal
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

    // =====================================================
    // Confirm Delete
    // =====================================================
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
                alert('ไม่สามารถลบข้อมูลห้องสนทนาได้');
            }
        } catch (error) {
            console.error("Delete Error:", error);
            alert('ไม่สามารถเชื่อมต่อ Server ได้');
        }
    });

    // =====================================================
    // Render Chat Bubble
    // =====================================================
    function appendBubble(sender, text, id = null) {
        if (!msgContainer) return;

        const wrapper = document.createElement('div');
        wrapper.className = `flex w-full ${sender === 'user' ? 'justify-end' : 'justify-start'} mb-8 msg-animate`;

        if (id) wrapper.id = id;

        const avatar = sender === 'user'
            ? `<img src="${typeof userPic !== 'undefined' ? userPic : ''}" referrerpolicy="no-referrer" class="w-8 h-8 rounded-full border border-slate-100 object-cover shadow-sm" onerror="this.src='https://ui-avatars.com/api/?name=User'">`
            : `<div class="w-8 h-8 rounded-full bg-[#f8f9fa] flex items-center justify-center border border-slate-100 shadow-sm">${ICONS.aiLogo}</div>`;

        const bubbleClass = sender === 'user'
            ? `bg-[#e8f0fe] text-[#1967d2] rounded-[20px_20px_4px_20px] px-5 py-3 border border-[#d2e3fc] max-w-[85%] break-words`
            : `text-[#3c4043] pt-1 content-area w-full leading-relaxed text-[16px] max-w-[85%] break-words`;

        const parsedContent = sender === 'user' ? escapeHTML(text).replace(/\n/g, '<br>') : linkify(text);

        wrapper.innerHTML = `
            <div class="flex ${sender === 'user' ? 'flex-row-reverse' : 'flex-row'} gap-3 items-start">
                <div class="shrink-0 mt-1">
                    ${avatar}
                </div>
                <div class="${bubbleClass}">
                    ${parsedContent}
                </div>
            </div>
        `;

        msgContainer.appendChild(wrapper);
        scrollToBottom();
    }

    // =====================================================
    // SEND MESSAGE
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
            <div class="flex gap-1.5 items-center px-4 py-3 bg-[#f8f9fa] rounded-2xl border border-slate-100 w-max">
                <div class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce"></div>
                <div class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce [animation-delay:0.2s]"></div>
                <div class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce [animation-delay:0.4s]"></div>
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
                        <div class="opacity-0 transition-opacity duration-500" id="fade-${aiId}">
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
                    area.innerHTML = `<span class="text-red-500">ขออภัยครับ ระบบเชื่อมต่อไม่ได้</span>`;
                }
            }
        } finally {
            isGenerating = false;
            if (sendBtn) sendBtn.disabled = false;
            scrollToBottom();
        }
    }

    // =====================================================
    // Update Sidebar Realtime (แสดงปุ่มลบตลอดเวลา)
    // =====================================================
    function updateSidebarRealtime(chatId, message) {
        const historyList = document.getElementById('history-list');
        if (!historyList) return;

        const div = document.createElement('div');
        div.id = `item-${chatId}`;
        div.className = `sidebar-item group flex items-center justify-between p-3 text-sm text-slate-600 cursor-pointer rounded-xl transition-all`;

        div.innerHTML = `
            <span onclick="loadChat('${chatId}')" class="truncate flex-1 font-medium pr-2">
                ${escapeHTML(message)}
            </span>
            <button onclick="deleteChat('${chatId}', event)" class="p-1 text-slate-400 hover:text-red-500 transition-colors rounded-lg hover:bg-red-50">
                ${ICONS.delete}
            </button>
        `;

        historyList.prepend(div);
    }

    // =====================================================
    // Events Listeners
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
            this.style.height = this.scrollHeight + 'px';
        };

        userInput.focus();
    }
});
