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
    : "https://ui-avatars.com/api/?name=" . urlencode($user_name);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="referrer" content="no-referrer">
    <title>Chatbot IT - Mahasarakham University</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://upload.wikimedia.org/wikipedia/th/b/bb/Informatics_MSU_Logo.svg" rel="icon">

    <style>
        :root {
            --app-height: 100vh;
            --sidebar-width: 18rem;
            --page-gutter: clamp(0.75rem, 3vw, 3rem);
        }

        @supports (height: 100dvh) {
            :root {
                --app-height: 100dvh;
            }
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            width: 100%;
            height: 100%;
            -webkit-text-size-adjust: 100%;
            text-size-adjust: 100%;
        }

        body {
            width: 100%;
            min-width: 0;
            height: var(--app-height);
            min-height: var(--app-height);
            margin: 0;
            font-family: 'Inter', 'Sarabun', sans-serif;
            background: #fff;
            color: #1f1f1f;
            opacity: 1;
            overflow: hidden;
            transition: opacity 0.4s ease-in-out;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body.loaded {
            opacity: 1;
        }

        button,
        textarea,
        a {
            -webkit-tap-highlight-color: transparent;
        }

        button,
        a {
            touch-action: manipulation;
        }

        img,
        video,
        canvas,
        svg {
            max-width: 100%;
        }

        .gemini-gradient {
            background: linear-gradient(70deg, #4285f4, #9b72cb, #d96570);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .msg-animate {
            animation: slideUp 0.35s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }

        .sidebar-item {
            min-width: 0;
            transition: background-color 0.2s ease, transform 0.2s ease;
            border-radius: 12px;
        }

        .sidebar-item:hover {
            background: #f0f4f9;
        }

        .custom-scrollbar {
            scrollbar-width: thin;
            scrollbar-color: #d8dee8 transparent;
            overscroll-behavior: contain;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #d8dee8;
            border-radius: 10px;
        }

        .chat-container {
            height: auto;
            min-height: 0;
            scroll-behavior: smooth;
            overflow-x: hidden;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        #sidebar {
            width: min(var(--sidebar-width), calc(100vw - 3rem));
            max-width: 100%;
            height: var(--app-height);
            padding-top: max(1rem, env(safe-area-inset-top));
            padding-bottom: max(1rem, env(safe-area-inset-bottom));
        }

        #chat-box {
            padding-left: var(--page-gutter);
            padding-right: var(--page-gutter);
        }

        #welcome,
        #msg-container,
        .composer-inner {
            width: 100%;
            max-width: 48rem;
        }

        #welcome h1 {
            font-size: clamp(1.75rem, 7vw, 3rem);
            line-height: 1.12;
            overflow-wrap: anywhere;
        }

        #welcome p {
            font-size: clamp(1rem, 4.2vw, 1.5rem);
        }

        #msg-container,
        #msg-container * {
            min-width: 0;
        }

        #msg-container p,
        #msg-container li,
        #msg-container div,
        #msg-container span {
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        #msg-container pre {
            max-width: 100%;
            overflow-x: auto;
            white-space: pre;
            -webkit-overflow-scrolling: touch;
        }

        #msg-container code {
            overflow-wrap: normal;
            word-break: normal;
        }

        #msg-container table {
            display: block;
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            border-collapse: collapse;
            -webkit-overflow-scrolling: touch;
        }

        #msg-container img,
        #msg-container video,
        #msg-container iframe {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
        }

        .mobile-header {
            padding-top: max(0.75rem, env(safe-area-inset-top));
        }

        .composer-shell {
            flex: 0 0 auto;
            padding: 0.75rem var(--page-gutter) max(0.75rem, env(safe-area-inset-bottom));
        }

        #user-input {
            min-width: 0;
            width: 100%;
            min-height: 48px;
            font-size: 16px;
            line-height: 1.5;
        }

        #send-btn {
            width: 48px;
            height: 48px;
            min-width: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* Modal */
        .custom-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: max(1rem, env(safe-area-inset-top)) max(1rem, env(safe-area-inset-right)) max(1rem, env(safe-area-inset-bottom)) max(1rem, env(safe-area-inset-left));
            background-color: rgba(0, 0, 0, 0.4);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s cubic-bezier(0.2, 0, 0, 1);
        }

        .custom-modal-backdrop.active {
            opacity: 1;
            pointer-events: auto;
        }

        .custom-modal-box {
            width: min(100%, 360px);
            max-height: calc(var(--app-height) - 2rem);
            overflow-y: auto;
            padding: 24px;
            background: #fff;
            border-radius: 28px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
            transform: scale(0.9) translateY(10px);
            transition: transform 0.25s cubic-bezier(0.2, 0, 0, 1);
        }

        .custom-modal-backdrop.active .custom-modal-box {
            transform: scale(1) translateY(0);
        }

        #gemini-toast {
            max-width: min(28rem, calc(100vw - 2rem));
            right: max(1rem, env(safe-area-inset-right));
            bottom: max(1rem, env(safe-area-inset-bottom));
            overflow-wrap: anywhere;
        }

        /* Style ปรับแต่งพิเศษสำหรับหน้า Login ให้สวยหรู */
        .login-screen {
            min-height: var(--app-height);
            padding-top: max(1.5rem, env(safe-area-inset-top));
            padding-bottom: max(1.5rem, env(safe-area-inset-bottom));
            overflow-y: auto;
            background: radial-gradient(circle at 50% 0%, rgba(66, 133, 244, 0.12) 0%, rgba(155, 114, 203, 0.05) 50%, #ffffff 100%);
        }

        @media (min-width: 768px) {
            #sidebar {
                width: var(--sidebar-width);
                min-width: var(--sidebar-width);
            }

            #chat-box {
                padding-top: clamp(1.5rem, 5vh, 3rem);
                padding-bottom: 1.5rem;
            }

            .composer-shell {
                padding-top: 0.5rem;
                padding-bottom: max(2rem, env(safe-area-inset-bottom));
            }
        }

        @media (max-width: 767.98px) {
            .custom-modal-backdrop {
                align-items: flex-end;
                padding: 0;
            }

            .custom-modal-box {
                width: 100%;
                max-width: 100%;
                max-height: min(85dvh, calc(var(--app-height) - 1rem));
                padding: 24px 20px max(24px, calc(env(safe-area-inset-bottom) + 16px));
                border-radius: 28px 28px 0 0;
                transform: translateY(100%);
            }

            .custom-modal-backdrop.active .custom-modal-box {
                transform: translateY(0);
            }

            #chat-box {
                padding-top: 1rem;
                padding-bottom: 1rem;
            }

            #welcome {
                margin-top: clamp(1.5rem, 8vh, 4rem);
            }

            #msg-container {
                padding-bottom: 0.5rem;
            }

            #gemini-toast {
                left: 50%;
                right: auto;
                bottom: max(0.75rem, env(safe-area-inset-bottom));
                width: max-content;
                transform: translate(-50%, 0);
            }

            #gemini-toast.translate-y-20 {
                transform: translate(-50%, 5rem);
            }
        }

        @media (max-width: 380px) {
            :root {
                --page-gutter: 0.65rem;
            }

            #sidebar {
                width: calc(100vw - 1.5rem);
            }

            .composer-shell {
                padding-top: 0.5rem;
            }

            #user-input {
                padding-left: 0.75rem;
                padding-right: 0.5rem;
            }

            #send-btn {
                width: 44px;
                height: 44px;
                min-width: 44px;
                padding: 0.75rem;
            }

            .custom-modal-box {
                border-radius: 22px 22px 0 0;
            }
        }

        @media (max-height: 500px) and (orientation: landscape) {
            #welcome {
                margin-top: 0.5rem;
            }

            #welcome h1 {
                margin-bottom: 0.5rem;
            }

            .mobile-header {
                padding-top: max(0.35rem, env(safe-area-inset-top));
                padding-bottom: 0.35rem;
            }

            .composer-shell {
                padding-top: 0.35rem;
                padding-bottom: max(0.35rem, env(safe-area-inset-bottom));
            }
        }

        @media (hover: none),
        (pointer: coarse) {
            .sidebar-item button {
                opacity: 1 !important;
                min-width: 36px;
                min-height: 36px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body class="flex w-full overflow-hidden bg-white">

    <div id="gemini-delete-modal" class="custom-modal-backdrop">
        <div class="custom-modal-box">
            <h3 class="text-xl font-medium text-[#1f1f1f] mb-3 tracking-tight">ลบการสนทนานี้ใช่หรือไม่?</h3>
            <p class="text-sm text-[#444746] leading-relaxed mb-6">ประวัติการแชททั้งหมดในห้องนี้จะถูกลบออกจากบัญชีของคุณอย่างถาวรและไม่สามารถเรียกคืนได้</p>
            <div class="flex flex-wrap justify-end gap-1.5">
                <button type="button" id="modal-cancel-btn" class="px-5 py-2.5 text-sm font-medium text-[#0b57d0] rounded-full hover:bg-[#f1f3f4] active:bg-[#e8eaed] transition-colors duration-200">
                    ยกเลิก
                </button>
                <button type="button" id="modal-confirm-btn" class="px-6 py-2.5 text-sm font-semibold text-[#062e6f] bg-[#a8c7fa] rounded-full hover:bg-[#9bc1f9] active:bg-[#7ca9f4] transition-colors duration-200 shadow-sm">
                    ลบ
                </button>
            </div>
        </div>
    </div>

    <div id="gemini-toast" class="fixed bottom-4 right-4 z-[110] bg-gray-900 text-white text-xs px-5 py-3 rounded-xl shadow-xl translate-y-20 opacity-0 transition-all duration-300 pointer-events-none">
        ลบการสนทนาเรียบร้อยแล้ว
    </div>

    <?php if (!$is_logged_in): ?>
        <!-- หน้าเข้าสู่ระบบ (ระบุคณะวิทยาการสารสนเทศ และ ลิขสิทธิ์ Theerakon Chuenchom) -->
        <div class="login-screen fixed inset-0 flex flex-col items-center justify-center px-4 sm:px-6 z-[999]">
            <div class="w-full max-w-sm sm:max-w-md flex flex-col items-center text-center my-auto py-6">
                <!-- โลโก้คณะ -->
                <div class="w-20 h-20 sm:w-24 sm:h-24 p-4 bg-white/90 border border-slate-100 rounded-[2.2rem] mb-6 flex items-center justify-center shadow-xl shadow-blue-500/10 ring-8 ring-blue-50/60 backdrop-blur-md">
                    <img src="https://upload.wikimedia.org/wikipedia/th/b/bb/Informatics_MSU_Logo.svg" alt="MSU Informatics Logo" class="w-full h-full object-contain">
                </div>

                <!-- ป้ายระบุมหาวิทยาลัย -->
                <span class="px-3.5 py-1 bg-blue-50 text-blue-600 text-xs font-bold rounded-full mb-3 tracking-wider uppercase border border-blue-100/80 shadow-xs">
                    Mahasarakham University
                </span>

                <!-- ชื่อระบบ และ คณะวิทยาการสารสนเทศ -->
                <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight mb-2">
                    พี่สารคาม <span class="gemini-gradient">AI</span>
                </h1>
                <p class="text-slate-500 text-xs sm:text-sm mb-8 max-w-xs leading-relaxed font-normal">
                    ระบบผู้ช่วยอัจฉริยะประมวลผลข้อมูล คณะวิทยาการสารสนเทศ มหาวิทยาลัยมหาสารคาม
                </p>

                <!-- ปุ่ม Google Login -->
                <a href="<?= $google_login_url ?>"
                    class="w-full py-3.5 px-6 bg-white hover:bg-slate-50 border border-slate-200/90 hover:border-slate-300 rounded-full flex items-center justify-center gap-3 transition-all duration-200 shadow-sm hover:shadow-md active:scale-[0.98] text-slate-700 font-semibold text-sm sm:text-base">
                    <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                    <span>เข้าสู่ระบบด้วย Google Account</span>
                </a>

                <?php if (isset($_GET['error'])): ?>
                    <div class="mt-4 p-3.5 bg-red-50 text-red-600 rounded-2xl text-xs border border-red-100 max-w-xs text-center font-medium">
                        <strong>เข้าสู่ระบบไม่สำเร็จ:</strong> โปรดตรวจสอบบัญชี Google แล้วลองใหม่อีกครั้ง
                    </div>
                <?php endif; ?>

                <!-- ลิขสิทธิ์ Theerakon Chuenchom -->
                <div class="mt-12 text-center">
                    <p class="text-[11px] text-slate-400 leading-relaxed">
                        &copy; <?= date('Y') ?> คณะวิทยาการสารสนเทศ มหาวิทยาลัยมหาสารคาม
                    </p>
                    <p class="text-[10px] text-slate-400 font-medium tracking-wide mt-0.5">
                        Developed & Copyrighted by <span class="text-slate-600 font-semibold">Theerakon Chuenchom</span>
                    </p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div id="overlay" class="fixed inset-0 bg-black/20 z-[55] hidden backdrop-blur-sm transition-opacity duration-300"></div>

        <aside id="sidebar"
            class="w-72 bg-[#f8fafc] border-r border-gray-200 fixed inset-y-0 left-0 z-[60] transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-300 ease-in-out flex flex-col p-4">
            <div class="flex items-center justify-between mb-5 md:hidden">
                <span class="font-bold gemini-gradient text-lg">เมนูระบบ</span>
                <button type="button" id="close-sidebar" aria-label="ปิดเมนู" class="p-2 text-gray-500 hover:bg-gray-200 rounded-full transition-colors">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <button type="button" onclick="newChat()"
                class="w-full py-3 mb-4 bg-white border border-gray-200 rounded-2xl text-sm font-medium shadow-sm hover:shadow-md hover:border-gray-300 transition-all flex items-center justify-center gap-2 active:scale-95 text-gray-700">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                การสนทนาใหม่
            </button>

            <div class="flex-1 overflow-y-auto space-y-1 custom-scrollbar pr-1">
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest px-2 mb-2">ประวัติการสนทนาล่าสุด</p>
                <div id="history-list" class="space-y-1">
                    <?php
                    $stmt = $conn->prepare("SELECT 
    chat_id,
    MAX(message) AS message,
    MAX(id) AS id
FROM chat_history
WHERE user_id = ?
GROUP BY chat_id
ORDER BY id DESC
LIMIT 20");
                    $stmt->bind_param("s", $_SESSION['user_id']);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($row = $res->fetch_assoc()): ?>
                        <div id="item-<?= $row['chat_id'] ?>"
                            class="sidebar-item group flex items-center justify-between p-3 text-sm text-gray-600 cursor-pointer rounded-xl transition-all hover:bg-gray-100">
                            <span onclick="loadChat('<?= $row['chat_id'] ?>')" class="truncate flex-1 font-medium pr-2">
                                <?= htmlspecialchars($row['message']) ?>
                            </span>
                            <button type="button" aria-label="ลบการสนทนา" onclick="deleteChat('<?= $row['chat_id'] ?>')"
                                class="opacity-0 group-hover:opacity-100 p-1 text-gray-400 hover:text-red-500 transition-opacity">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
                            </button>
                        </div>
                    <?php endwhile;
                    $stmt->close();
                    $conn->close(); ?>
                </div>
            </div>

            <div class="mt-auto pt-4 border-t border-gray-200 flex items-center gap-3">
                <img src="<?= htmlspecialchars($user_picture, ENT_QUOTES, 'UTF-8') ?>" referrerpolicy="no-referrer"
                    class="w-10 h-10 rounded-full border border-gray-200 shadow-sm object-cover"
                    onerror="this.src='https://ui-avatars.com/api/?name=User'">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold truncate text-gray-700"><?= htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') ?></p>
                    <a href="logout.php" class="text-[10px] text-red-500 font-medium hover:underline">ออกจากระบบ</a>
                </div>
            </div>
        </aside>

        <main class="flex-1 w-full flex flex-col relative bg-white min-w-0 min-h-0 overflow-hidden">
            <header class="mobile-header md:hidden flex-none flex items-center justify-between px-3 py-2.5 border-b bg-white">
                <button type="button" id="open-sidebar" aria-label="เปิดเมนู" class="p-2 text-gray-600 hover:bg-gray-100 rounded-full transition-colors">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                <span class="font-bold gemini-gradient text-lg">Chatbot IT</span>
                <div class="w-10"></div>
            </header>

            <div id="chat-box" class="flex-1 min-h-0 chat-container custom-scrollbar">
                <div id="welcome" class="mx-auto mt-12 md:mt-20 px-1 sm:px-2">
                    <h1 class="text-4xl md:text-5xl font-medium mb-4 tracking-tight">
                        <span class="gemini-gradient font-bold">สวัสดีครับคุณ <?= htmlspecialchars(explode(' ', trim($user_name))[0] ?? 'User', ENT_QUOTES, 'UTF-8') ?></span>
                    </h1>
                    <p class="text-xl md:text-2xl text-gray-300 font-light leading-relaxed">
                        มีเรื่องอะไรให้พี่สารคามช่วยดูแลหรือแนะนำในวันนี้ไหมครับ?
                    </p>
                </div>
                <div id="msg-container" class="mx-auto space-y-6 sm:space-y-8 pb-4 sm:pb-8"></div>
            </div>

            <div class="composer-shell bg-white border-t md:border-t-0 border-gray-100">
                <div class="composer-inner mx-auto relative">
                    <div class="flex min-w-0 items-end gap-1.5 sm:gap-2 p-1.5 bg-[#f0f4f9] rounded-[28px] focus-within:bg-white focus-within:ring-1 focus-within:ring-gray-200 focus-within:shadow-lg transition-all duration-300">
                        <textarea id="user-input" rows="1" placeholder="ถามพี่สารคามได้เลย..."
                            class="flex-1 min-w-0 bg-transparent border-none outline-none py-3 px-3 sm:px-4 resize-none max-h-36 text-gray-700 custom-scrollbar" style="height: auto;"></textarea>
                        <button type="button" id="send-btn" aria-label="ส่งข้อความ"
                            class="mb-0.5 p-3 bg-blue-600 text-white rounded-full hover:bg-blue-700 active:scale-90 transition-all shadow-sm shrink-0">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" />
                            </svg>
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
            window.addEventListener('resize', updateViewportHeight, {
                passive: true
            });
            window.addEventListener('orientationchange', updateViewportHeight, {
                passive: true
            });
            window.visualViewport?.addEventListener('resize', updateViewportHeight, {
                passive: true
            });
            window.visualViewport?.addEventListener('scroll', updateViewportHeight, {
                passive: true
            });

            window.addEventListener('load', () => {
                updateViewportHeight();
                document.body.classList.add('loaded');
            });
        })();
    </script>
</body>

</html>
