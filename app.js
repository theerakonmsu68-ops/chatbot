/**
 * พี่สารคาม AI - Render Production Edition
 * Secure Frontend Version
 */

document.addEventListener('DOMContentLoaded', () => {


    // =====================================================
    // Sidebar
    // =====================================================


    const sidebar =
        document.getElementById('sidebar');

    const overlay =
        document.getElementById('overlay');


    const openSidebarBtn =
        document.getElementById('open-sidebar');


    const closeSidebarBtn =
        document.getElementById('close-sidebar');



    const toggleSidebar = (show)=>{


        if(!sidebar) return;


        if(show){

            sidebar.classList.remove(
                '-translate-x-full'
            );

            overlay?.classList.remove(
                'hidden'
            );


        }else{


            sidebar.classList.add(
                '-translate-x-full'
            );


            overlay?.classList.add(
                'hidden'
            );

        }

    };



    openSidebarBtn?.addEventListener(
        'click',
        ()=>toggleSidebar(true)
    );


    closeSidebarBtn?.addEventListener(
        'click',
        ()=>toggleSidebar(false)
    );


    overlay?.addEventListener(
        'click',
        ()=>toggleSidebar(false)
    );





    // =====================================================
    // Chat Elements
    // =====================================================


    const chatBox =
        document.getElementById('chat-box');


    if(!chatBox) return;



    const msgContainer =
        document.getElementById('msg-container');


    const userInput =
        document.getElementById('user-input');


    const sendBtn =
        document.getElementById('send-btn');


    const welcome =
        document.getElementById('welcome');



    let currentChatId = null;



    let pendingDeleteChatId = null;





    // =====================================================
    // Security Escape HTML
    // =====================================================


    function escapeHTML(str){


        if(!str) return "";


        return String(str)

        .replace(/&/g,"&amp;")

        .replace(/</g,"&lt;")

        .replace(/>/g,"&gt;")

        .replace(/"/g,"&quot;")

        .replace(/'/g,"&#039;");


    }




    // =====================================================
    // New Chat
    // =====================================================


    window.newChat = function(){


        currentChatId = null;



        if(msgContainer){

            msgContainer.innerHTML = '';

        }



        if(welcome){

            welcome.style.display='block';

        }



        if(userInput){

            userInput.value='';

            userInput.style.height='auto';

            userInput.focus();

        }



        if(
            window.innerWidth < 768 &&
            sidebar &&
            !sidebar.classList.contains(
                '-translate-x-full'
            )
        ){

            toggleSidebar(false);

        }


    }


    // =====================================================
// Load Chat History
// =====================================================


window.loadChat = async function(chatId){


    currentChatId = chatId;



    if(welcome){

        welcome.style.display='none';

    }



    if(msgContainer){

        msgContainer.innerHTML = `

        <div class="flex flex-col items-center justify-center py-20 opacity-30">

            <div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mb-3"></div>

            <p class="text-xs font-medium">
                FETCHING CONVERSATION
            </p>

        </div>

        `;

    }



    if(window.innerWidth < 768){

        toggleSidebar(false);

    }




    try{


        const response = await fetch(
            'api.php',
            {

                method:'POST',

                headers:{
                    'Content-Type':'application/json'
                },


                body:JSON.stringify({

                    action:'fetch',

                    chat_id:chatId

                })

            }
        );



        const data = await response.json();




        if(data.error){

            throw new Error(data.error);

        }




        if(msgContainer){

            msgContainer.innerHTML='';

        }



        data.history.forEach(item=>{


            appendBubble(
                'user',
                item.message
            );


            appendBubble(
                'ai',
                item.reply
            );


        });



    }catch(error){


        console.error(
            "Load History Error:",
            error
        );


        if(msgContainer){

            msgContainer.innerHTML=

            `

            <p class="text-center text-red-400 py-10 text-sm">

            ไม่สามารถโหลดประวัติได้

            </p>

            `;

        }

    }


};






// =====================================================
// Delete Modal
// =====================================================


const deleteModal =
document.getElementById(
    'gemini-delete-modal'
);


const modalCancelBtn =
document.getElementById(
    'modal-cancel-btn'
);


const modalConfirmBtn =
document.getElementById(
    'modal-confirm-btn'
);


const geminiToast =
document.getElementById(
    'gemini-toast'
);






window.deleteChat=function(chatId){



    if(event){

        event.stopPropagation();

        event.preventDefault();

    }



    pendingDeleteChatId = chatId;



    if(deleteModal){

        deleteModal.classList.add(
            'active'
        );

    }


};






function closeDeleteModal(){


    if(deleteModal){

        deleteModal.classList.remove(
            'active'
        );

    }


    pendingDeleteChatId=null;


}





modalCancelBtn?.addEventListener(
    'click',
    closeDeleteModal
);




deleteModal?.addEventListener(
    'click',
    (e)=>{

        if(e.target===deleteModal){

            closeDeleteModal();

        }

    }
);







// =====================================================
// Confirm Delete
// =====================================================


modalConfirmBtn?.addEventListener(
'click',
async ()=>{


    if(!pendingDeleteChatId)
        return;



    const targetId =
    pendingDeleteChatId;



    closeDeleteModal();



    try{


        const response =
        await fetch(
            'remove_room.php',
            {

                method:'POST',

                headers:{

                    'Content-Type':'application/json'

                },


                body:JSON.stringify({

                    chat_id:targetId

                })

            }
        );




        const data =
        await response.json();




        if(data.status==="success"){


            const item =
            document.getElementById(
                `item-${targetId}`
            );



            if(item){


                item.style.opacity='0';

                item.style.transform=
                'translateX(-20px)';


                setTimeout(()=>{

                    item.remove();

                },300);


            }





            if(currentChatId===targetId){


                currentChatId=null;



                if(msgContainer){

                    msgContainer.innerHTML='';

                }



                if(welcome){

                    welcome.style.display='block';

                }


            }




            if(geminiToast){


                geminiToast.classList.remove(

                    'translate-y-20',

                    'opacity-0'

                );



                setTimeout(()=>{


                    geminiToast.classList.add(

                        'translate-y-20',

                        'opacity-0'

                    );


                },2500);


            }





        }else{


            alert(
                'ไม่สามารถลบข้อมูลห้องสนทนาได้'
            );


        }




    }catch(error){


        console.error(
            "Delete Error:",
            error
        );


        alert(
            'ไม่สามารถเชื่อมต่อ Server ได้'
        );


    }



});


// =====================================================
// Render Chat Bubble
// =====================================================


function appendBubble(sender, text, id=null){


    if(!msgContainer)
        return;



    const wrapper =
    document.createElement('div');



    wrapper.className =
    `flex w-full ${
        sender==='user'
        ? 'justify-end'
        : 'justify-start'
    } mb-8 msg-animate`;



    if(id){

        wrapper.id=id;

    }




    const avatar = sender==='user'


    ? `<img src="${userPic || ''}"
        referrerpolicy="no-referrer"
        class="w-8 h-8 rounded-full border border-gray-100 object-cover shadow-sm"
        onerror="this.src='https://ui-avatars.com/api/?name=User'">`


    :

    `<div class="
        w-8 h-8 rounded-full 
        bg-[#f8f9fa]
        flex items-center justify-center
        border border-gray-100
        text-[#1a73e8]
        font-bold text-[10px]
    ">
        AI
    </div>`;





    const bubbleClass =
    sender==='user'


    ?

    `
    bg-[#e8f0fe]
    text-[#1967d2]
    rounded-[20px_20px_4px_20px]
    px-5 py-3
    border border-[#d2e3fc]
    `



    :

    `
    text-[#3c4043]
    pt-1
    content-area
    w-full
    leading-relaxed
    text-[16px]
    `;




    wrapper.innerHTML = `


    <div class="
        flex
        ${
        sender==='user'
        ?
        'flex-row-reverse'
        :
        'flex-row'
        }
        gap-3
        max-w-[85%]
        items-start
    ">


        <div class="shrink-0 mt-1">

            ${avatar}

        </div>


        <div class="${bubbleClass}">

            ${
            sender==='ai'
            ?
            escapeHTML(text)
            :
            escapeHTML(text)
            }


        </div>


    </div>


    `;




    msgContainer.appendChild(wrapper);



    setTimeout(()=>{


        chatBox.scrollTo({

            top:chatBox.scrollHeight,

            behavior:'smooth'

        });


    },50);


}








// =====================================================
// SEND MESSAGE
// =====================================================


async function send(){



    if(!userInput)
        return;



    const text =
    userInput.value.trim();



    if(!text)
        return;




    if(welcome){

        welcome.style.display='none';

    }





    appendBubble(
        'user',
        text
    );





    userInput.value='';

    userInput.style.height='auto';





    const aiId =
    'ai-' + Date.now();





    const typingHTML = `


    <div class="
        flex gap-1.5
        items-center
        px-4 py-3
        bg-[#f8f9fa]
        rounded-2xl
        border border-gray-50
        w-max
    ">


        <div class="
        w-1 h-1
        bg-gray-400
        rounded-full
        animate-pulse">
        </div>


        <div class="
        w-1 h-1
        bg-gray-400
        rounded-full
        animate-pulse">
        </div>


        <div class="
        w-1 h-1
        bg-gray-400
        rounded-full
        animate-pulse">
        </div>


    </div>


    `;




    appendBubble(
        'ai',
        typingHTML,
        aiId
    );





    // =====================================================
    // Abort Controller Timeout
    // =====================================================


    const controller =
    new AbortController();



    const timeout =
    setTimeout(()=>{

        controller.abort();

    },60000);







    try{



        const response =
        await fetch(
            'api.php',
            {


                method:'POST',


                signal:controller.signal,


                headers:{


                    'Content-Type':
                    'application/json'


                },



                body:JSON.stringify({

                    action:'chat',

                    message:text,

                    chat_id:currentChatId


                })


            }
        );



        clearTimeout(timeout);





        const data =
        await response.json();






        // API Error จาก PHP


        if(data.error){


            throw new Error(
                data.error
            );


        }







        if(
            !currentChatId &&
            data.chat_id
        ){


            currentChatId =
            data.chat_id;



            updateSidebarRealtime(
                data.chat_id,
                text
            );


        }





        const aiBubble =
        document.getElementById(aiId);





        if(aiBubble){


            const contentArea =
            aiBubble.querySelector(
                '.content-area'
            );



            if(contentArea){



                contentArea.innerHTML = `

                <div class="
                    opacity-0
                    transition-opacity
                    duration-500
                "
                id="fade-${aiId}">

                ${
                escapeHTML(
                    data.reply || ''
                )
                .replace(/\n/g,'<br>')
                }

                </div>

                `;




                setTimeout(()=>{


                    const fadeEl =
                    document.getElementById(
                        `fade-${aiId}`
                    );



                    fadeEl?.classList.remove(
                        'opacity-0'
                    );


                },10);


            }


        }




    }catch(error){



        console.error(
            "Chat Error:",
            error
        );



        const aiBubble =
        document.getElementById(aiId);



        if(aiBubble){


            const area =
            aiBubble.querySelector(
                '.content-area'
            );



            if(area){


                area.innerHTML =
                `
                <span class="text-red-500">

                ขออภัยครับ ระบบเชื่อมต่อไม่ได้

                </span>
                `;


            }


        }


    }



}







// =====================================================
// Update Sidebar
// =====================================================


function updateSidebarRealtime(chatId,message){



    const historyList =
    document.getElementById(
        'history-list'
    );



    if(!historyList)
        return;





    const div =
    document.createElement('div');



    div.id =
    `item-${chatId}`;



    div.className =
    `
    sidebar-item
    group
    flex
    items-center
    justify-between
    p-3
    text-sm
    text-gray-600
    cursor-pointer
    rounded-xl
    transition-all
    hover:bg-gray-100
    `;





    div.innerHTML = `

    <span
    onclick="loadChat('${chatId}')"
    class="
    truncate
    flex-1
    font-medium
    pr-2
    ">

        ${escapeHTML(message)}

    </span>


    <button
    onclick="deleteChat('${chatId}')"
    class="
    opacity-0
    group-hover:opacity-100
    p-1
    text-gray-400
    hover:text-red-500
    ">

    🗑

    </button>


    `;



    historyList.prepend(div);


}







// =====================================================
// Events
// =====================================================


if(sendBtn){

    sendBtn.onclick = send;

}



if(userInput){


    userInput.onkeydown =
    (e)=>{


        if(
            e.key==="Enter"
            &&
            !e.shiftKey
        ){


            e.preventDefault();


            send();


        }


    };





    userInput.oninput=function(){


        this.style.height='auto';


        this.style.height =
        this.scrollHeight+'px';


    };



    userInput.focus();


}



});