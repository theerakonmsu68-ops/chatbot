<?php
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');

session_start();
require_once 'db_config.php';

// Google Login Config
$client_id = getenv("GOOGLE_CLIENT_ID");
$redirect_uri = getenv("GOOGLE_REDIRECT_URI");
$google_login_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'response_type' => 'code',
    'scope' => 'openid profile email',
    'prompt' => 'select_account'
]);

$is_logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? "User";
$user_picture = (isset($_SESSION['user_picture']) && $_SESSION['user_picture'] != "")
    ? $_SESSION['user_picture']
    : "https://ui-avatars.com/api/?name=" . urlencode($user_name) . "&background=0D8ABC&color=fff";
?>
<!DOCTYPE html>
<html lang="th" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="referrer" content="no-referrer">
    <title>พี่สารคาม AI - Mahasarakham University</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://upload.wikimedia.org/wikipedia/th/b/bb/Informatics_MSU_Logo.svg" rel="icon">

    <style>
        :root {
            --app-height: 100vh;
            --sidebar-width: 18rem;
            --page-gutter: clamp(1rem, 4vw, 3rem);
        }

        @supports (height: 100dvh) {
            :root {
                --app-height: 100dvh;
            }
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
            width: 100%;
            height: var(--app-height);
            margin: 0;
            font-family: 'Plus Jakarta Sans', 'Sarabun', sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* Gradient Texts */
        .gemini-gradient {
            background: linear-gradient(135deg, #2563eb 0%, #7c3aed 50%, #db2777 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .login-bg-glow {
            background: radial-gradient(circle at 50% 0%, rgba(37, 99, 235, 0.12) 0%, rgba(124, 58, 237, 0.06) 50%, transparent 100%);
        }

        /* Animations */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(16px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .msg-animate {
            animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        /* Custom Scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 99px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .chat-container {
            scroll-behavior: smooth;
            overflow-x: hidden;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        #sidebar {
            width: min(var(--sidebar-width), calc(100vw - 2.5rem));
            height: var(--app-height);
        }

        #chat-box {
            padding-left: var(--page-gutter);
            padding-right: var(--page-gutter);
        }

        #welcome, #msg-container, .composer-inner {
            width: 100%;
            max-width: 48rem;
        }

        /* Modal Styles */
        .custom-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background-color: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(4px);
            opacity: 0;
            pointer-events: none;
            transition: all 0.25s ease-in-out;
        }

        .custom-modal-backdrop.active {
            opacity: 1;
            pointer-events: auto;
        }

        .custom-modal-box {
            width: min(100%, 380px);
            padding: 24px;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            transform: scale(0.95) translateY(10px);
            transition: all 0.25s ease-in-out;
        }

        .custom-modal-backdrop.active .custom-modal-box {
            transform: scale(1) translateY(0);
        }

        @media (max-width: 767.98px) {
            .custom-modal-backdrop {
                align-items: flex-end;
                padding: 0;
            }

            .custom-modal-box {
                width: 100%;
                max-width: 100%;
                border-radius: 28px 28px 0 0;
                transform: translateY(100%);
            }

            .custom-modal-backdrop.active .custom-modal-box {
                transform: translateY(0);
            }
        }
    </style>
</head>

<body class="flex w-full overflow-hidden bg-white">

    <!-- Modal ยืนยันการลบ -->
    <div id="gemini-delete-modal" class="custom-modal-backdrop">
        <div class="custom-modal-box border border-slate-100">
            <div class="w-12 h-12 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-1">ลบการสนทนานี้ใช่หรือไม่?</h3>
            <p class="text-sm text-slate-500 leading-relaxed mb-6">ประวัติการแชททั้งหมดในห้องนี้จะถูกลบออกจากบัญชีของคุณอย่างถาวร ไม่สามารถกู้คืนได้</p>
            <div class="flex items-center justify-end gap-2">
                <button type="button" id="modal-cancel-btn" class="px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 rounded-full transition-colors">
                    ยกเลิก
                </button>
                <button type="button" id="modal-confirm-btn" class="px-5 py-2.5 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-full shadow-sm shadow-red-200 transition-colors">
                    ลบข้อมูล
                </button>
            </div>
        </div>
    </div>

    <!-- Toast แจ้งเตือน -->
    <div id="gemini-toast" class="fixed bottom-5 right-5 z-[110] bg-slate-900 text-white text-xs font-medium px-5 py-3 rounded-2xl shadow-2xl border border-slate-800 translate-y-20 opacity-0 transition-all duration-300 pointer-events-none flex items-center gap-2">
        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        ลบห้องสนทนาเรียบร้อยแล้ว
    </div>

    <?php if (!$is_logged_in): ?>
        <!-- Login Screen (Ultra Modern Design) -->
        <div class="login-screen login-bg-glow fixed inset-0 bg-white flex flex-col items-center justify-center px-4 sm:px-6 z-[999]">
            <div class="w-full max-w-md flex flex-col items-center text-center">
                <!-- Logo Frame -->
                <div class="w-20 h-20 p-4 bg-white border border-slate-100 rounded-3xl mb-6 flex items-center justify-center shadow-xl shadow-blue-500/10 ring-8 ring-blue-50/50">
                    <img src="https://upload.wikimedia.org/wikipedia/th/b/bb/Informatics_MSU_Logo.svg" alt="MSU Logo" class="w-full h-full object-contain">
                </div>

                <!-- Titles -->
                <span class="px-3 py-1 bg-blue-50 text-blue-600 text-xs font-bold rounded-full mb-3 tracking-wide uppercase border border-blue-100">
                    Mahasarakham University
                </span>
                <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight mb-2">
                    พี่สารคาม <span class="gemini-gradient">AI</span>
                </h1>
                <p class="text-slate-500 text-sm mb-8 max-w-xs leading-relaxed">
                    ผู้ช่วยอัจฉริยะระบบสารสนเทศและการเรียนรู้ มหาวิทยาลัยมหาสารคาม
                </p>

                <!-- Login Button -->
                <a href="<?= $google_login_url ?>"
                    class="w-full max-w-xs py-3.5 px-6 bg-white hover:bg-slate-50 border border-slate-200 hover:border-slate-300 rounded-full flex items-center justify-center gap-3 transition-all duration-200 shadow-sm hover:shadow-md active:scale-[0.98] text-slate-700 font-semibold text-sm">
                    <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                    <span>เข้าสู่ระบบด้วย Google</span>
                </a>

                <?php if (isset($_GET['error'])): ?>
                    <div class="mt-5 p-3.5 bg-red-50 text-red-600 rounded-2xl text-xs border border-red-100 max-w-xs text-center font-medium">
                        เข้าสู่ระบบไม่สำเร็จ โปรดลองใหม่อีกครั้ง
                    </div>
                <?php endif; ?>

                <p class="mt-12 text-xs text-slate-400">
                    &copy; <?= date('Y') ?> IT Mahasarakham University
                </p>
            </div>
        </div>
    <?php else: ?>
        <!-- Main Application Shell -->
        <div id="overlay" class="fixed inset-0 bg-slate-900/30 z-[55] hidden backdrop-blur-xs transition-opacity duration-300"></div>

        <!-- Sidebar -->
        <aside id="sidebar"
            class="bg-slate-50/90 backdrop-blur-md border-r border-slate-200/80 fixed inset-y-0 left-0 z-[60] transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-300 ease-in-out flex flex-col p-4 shrink-0">
            
            <!-- Sidebar Header -->
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2.5 px-2">
                    <div class="w-8 h-8 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold shadow-md shadow-blue-500/20">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <span class="font-bold text-slate-800 text-base">พี่สารคาม AI</span>
                </div>
                <button type="button" id="close-sidebar" aria-label="ปิดเมนู" class="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-200/60 rounded-xl md:hidden transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- New Chat Button -->
            <button type="button" onclick="newChat()"
                class="w-full py-3 px-4 mb-4 bg-white hover:bg-slate-100/80 border border-slate-200/80 rounded-2xl text-sm font-semibold text-slate-700 shadow-sm transition-all flex items-center justify-center gap-2 active:scale-[0.98]">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                <span>สร้างการสนทนาใหม่</span>
            </button>

            <!-- Chat History List -->
            <div class="flex-1 overflow-y-auto custom-scrollbar space-y-1 pr-1">
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-2 mb-2">ประวัติการแชท</p>
                <div id="history-list" class="space-y-1">
                    <?php
                    $stmt = $conn->prepare("SELECT chat_id, MAX(message) AS message, MAX(id) AS id FROM chat_history WHERE user_id = ? GROUP BY chat_id ORDER BY id DESC LIMIT 25");
                    $stmt->bind_param("s", $_SESSION['user_id']);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($row = $res->fetch_assoc()): ?>
                        <div id="item-<?= $row['chat_id'] ?>"
                            class="sidebar-item group flex items-center justify-between p-2.5 text-sm text-slate-600 cursor-pointer rounded-xl transition-all hover:bg-slate-200/60">
                            <span onclick="loadChat('<?= $row['chat_id'] ?>')" class="truncate flex-1 font-medium pr-2">
                                <?= htmlspecialchars($row['message']) ?>
                            </span>
                            <button type="button" aria-label="ลบการสนทนา" onclick="deleteChat('<?= $row['chat_id'] ?>', event)"
                                class="opacity-0 group-hover:opacity-100 p-1 text-slate-400 hover:text-red-500 rounded-lg hover:bg-red-50 transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    <?php endwhile;
                    $stmt->close();
                    $conn->close(); ?>
                </div>
            </div>

            <!-- User Footer Card -->
            <div class="mt-auto pt-3 border-t border-slate-200/80 flex items-center gap-3">
                <div class="relative shrink-0">
                    <img src="<?= htmlspecialchars($user_picture, ENT_QUOTES, 'UTF-8') ?>" referrerpolicy="no-referrer"
                        class="w-9 h-9 rounded-full border border-slate-200 object-cover shadow-sm"
                        onerror="this.src='https://ui-avatars.com/api/?name=User'">
                    <span class="w-2.5 h-2.5 bg-emerald-500 border-2 border-white rounded-full absolute bottom-0 right-0"></span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-bold text-slate-800 truncate"><?= htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') ?></p>
                    <a href="logout.php" class="text-[11px] text-red-500 font-medium hover:underline flex items-center gap-1">
                        <span>ออกจากระบบ</span>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Chat Area -->
        <main class="flex-1 w-full flex flex-col relative bg-white min-w-0 min-h-0 overflow-hidden">
            <!-- Mobile Header -->
            <header class="mobile-header md:hidden flex-none flex items-center justify-between px-4 py-3 border-b border-slate-100 bg-white/80 backdrop-blur-md">
                <button type="button" id="open-sidebar" aria-label="เปิดเมนู" class="p-2 text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <span class="font-extrabold gemini-gradient text-lg tracking-tight">พี่สารคาม AI</span>
                <button type="button" onclick="newChat()" class="p-2 text-blue-600 hover:bg-blue-50 rounded-xl transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                </button>
            </header>

            <!-- Chat Content Box -->
            <div id="chat-box" class="flex-1 min-h-0 chat-container custom-scrollbar">
                <!-- Welcome Screen -->
                <div id="welcome" class="mx-auto mt-16 md:mt-24 px-2 text-left">
                    <h1 class="text-3xl sm:text-5xl font-extrabold mb-3 tracking-tight">
                        <span class="gemini-gradient">สวัสดีครับ, คุณ<?= htmlspecialchars(explode(' ', trim($user_name))[0] ?? 'User', ENT_QUOTES, 'UTF-8') ?></span>
                    </h1>
                    <p class="text-lg sm:text-2xl text-slate-400 font-light leading-relaxed">
                        มีคำถามเกี่ยวกับตารางเรียน การใช้งานระบบ หรือข้อสงสัยใดๆ ให้พี่สารคามช่วยดูแลไหมครับ?
                    </p>
                </div>

                <!-- Dynamic Chat Bubbles -->
                <div id="msg-container" class="mx-auto space-y-6 pb-6 pt-4"></div>
            </div>

            <!-- Composer Floating Box -->
            <div class="composer-shell bg-gradient-to-t from-white via-white to-transparent pt-2">
                <div class="composer-inner mx-auto relative">
                    <div class="flex items-end gap-2 p-2 bg-slate-100/80 hover:bg-slate-100 focus-within:bg-white border border-slate-200/80 focus-within:border-blue-400 rounded-[28px] focus-within:ring-4 focus-within:ring-blue-500/10 transition-all duration-200 shadow-sm">
                        <textarea id="user-input" rows="1" placeholder="พิมพ์ข้อความคำถามของคุณที่นี่..."
                            class="flex-1 min-w-0 bg-transparent border-none outline-none py-2.5 px-3 sm:px-4 resize-none max-h-36 text-slate-800 placeholder-slate-400 text-base leading-relaxed custom-scrollbar" style="height: auto;"></textarea>
                        
                        <button type="button" id="send-btn" aria-label="ส่งข้อความ"
                            class="p-3 bg-blue-600 text-white rounded-full hover:bg-blue-700 active:scale-95 transition-all shadow-md shadow-blue-500/20 shrink-0 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="w-5 h-5 transform rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19V5m0 0l-7 7m7-7l7 7"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </main>

        <script>
            const userPic = <?= json_encode($user_picture, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        </script>
        <script src="app.js?v=<?= time() ?>"></script>
    <?php endif; ?>

    <script>
        (() => {
            const updateViewportHeight = () => {
                const viewportHeight = window.visualViewport?.height || window.innerHeight;
                document.documentElement.style.setProperty('--app-height', `${viewportHeight}px`);
            };

            updateViewportHeight();
            window.addEventListener('resize', updateViewportHeight, { passive: true });
            window.addEventListener('orientationchange', updateViewportHeight, { passive: true });
            window.visualViewport?.addEventListener('resize', updateViewportHeight, { passive: true });
            window.visualViewport?.addEventListener('scroll', updateViewportHeight, { passive: true });

            window.addEventListener('load', () => {
                updateViewportHeight();
                document.body.classList.add('loaded');
            });
        })();
    </script>
</body>

</html>
