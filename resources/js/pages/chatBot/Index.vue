<script setup lang="ts">
import axios from 'axios';
import { ref, nextTick, onMounted, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

const newMessage = ref('');
const isLoading = ref(false);
const scrollContainer = ref<HTMLElement | null>(null);

const defaultMessages = [
  {
    role: 'bot',
    text: '¡Hola! 👋 Soy tu asistente musical. Puedo ayudarte a descubrir nueva música, crear playlists y analizar tus gustos. ¿Qué te apetece escuchar?',
    songs: [],
    timestamp: new Date()
  }
];

const messages = ref<any[]>([]);

const formatTime = (date: Date) => {
  return new Intl.DateTimeFormat('es-ES', {
    hour: '2-digit',
    minute: '2-digit'
  }).format(date);
};

const scrollToBottom = async (smooth = true) => {
  await nextTick();
  
  if (!scrollContainer.value) return;

  setTimeout(() => {
    if (!scrollContainer.value) return;

    const container = scrollContainer.value;
    const targetPosition = container.scrollHeight - container.clientHeight;
    
    if (!smooth || Math.abs(container.scrollTop - targetPosition) < 10) {
      container.scrollTop = targetPosition;
      return;
    }

    const startPosition = container.scrollTop;
    const distance = targetPosition - startPosition;
    const duration = 400;
    let startTimestamp: number | null = null;

    const easeOutCubic = (t: number) => 1 - Math.pow(1 - t, 3);

    const step = (timestamp: number) => {
      if (!startTimestamp) startTimestamp = timestamp;
      const progress = Math.min((timestamp - startTimestamp) / duration, 1);
      
      const easeProgress = easeOutCubic(progress);
      container.scrollTop = startPosition + distance * easeProgress;

      if (progress < 1) {
        window.requestAnimationFrame(step);
      } else {
        container.scrollTop = targetPosition;
      }
    };

    window.requestAnimationFrame(step);
  }, 50);
};

const sendMessage = async (textOverride?: string) => {
  const textToSend = textOverride || newMessage.value;
  if (!textToSend.trim() || isLoading.value) return;

  messages.value.push({
    role: 'user',
    text: textToSend,
    songs: [],
    timestamp: new Date()
  });

  const messageBackup = textToSend;
  newMessage.value = '';
  isLoading.value = true;
  await scrollToBottom(true);

  try {
    const response = await axios.post('/chatbot/ask', {
      message: messageBackup
    });
 
    const recommendedSongs = (response.data.songs || []).map((song: any) => ({
      ...song,
      is_liked: false
    }));

    messages.value.push({
      role: 'bot',
      text: response.data.reply,
      songs: recommendedSongs,
      timestamp: new Date()
    });

  } catch (error) {
    messages.value.push({
      role: 'bot',
      text: 'Lo siento, ha habido un error en la conexión. ¿Podemos intentarlo de nuevo?',
      songs: [],
      timestamp: new Date()
    });
  } finally {
    isLoading.value = false;
    await scrollToBottom(true);
  }
};

const toggleLike = async (song: any) => {
  if (song.is_liked || isLoading.value) return;

  song.is_liked = true;

  try {
    await axios.post('/chatbot/like', {
      song_id: song.id
    });
  } catch (error) {
    song.is_liked = false;
    alert("No se ha podido guardar en tus favoritos. Revisa tu conexión.");
  }
};

onMounted(() => {
  const savedChat = sessionStorage.getItem('music_assist_chat');
  
  if (savedChat) {
    try {
      const parsedChat = JSON.parse(savedChat);
      messages.value = parsedChat.map((msg: any) => ({
        ...msg,
        timestamp: new Date(msg.timestamp) 
      }));
    } catch (e) {
      messages.value = [...defaultMessages];
    }
  } else {
    messages.value = [...defaultMessages];
  }

  scrollToBottom(false);
});

watch(messages, (newMessages) => {
  sessionStorage.setItem('music_assist_chat', JSON.stringify(newMessages));
}, { deep: true });
</script>

<template>
  <AppLayout>
    <div class="flex flex-col h-full w-full relative overflow-hidden bg-[#1A1921]">
      <div class="absolute inset-0 bg-gradient-design z-0"></div>

      <div ref="scrollContainer" class="flex-1 overflow-y-auto p-3 md:p-6 pb-20 custom-scrollbar z-10 relative">
        
        <div class="max-w-4xl mx-auto w-full flex flex-col gap-6">
          
          <div v-for="(msg, index) in messages" :key="'msg-' + index" 
               :class="['flex w-full chat-message-enter', msg.role === 'user' ? 'justify-end' : 'justify-start']">
            
            <div :class="['flex items-start gap-2 md:gap-4 max-w-[90%] md:max-w-[75%]', msg.role === 'user' ? 'flex-row-reverse' : '']">
              
              <div :class="['flex-none w-8 h-8 md:w-10 md:h-10 rounded-full flex items-center justify-center shadow-lg border border-white/10 mt-1', 
                            msg.role === 'bot' ? 'bg-[#A855F7]' : 'bg-blue-600']">
                <svg v-if="msg.role === 'bot'" class="w-4 h-4 md:w-5 md:h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
                <span v-else class="text-white text-[10px] md:text-xs font-bold">TU</span>
              </div>

              <div :class="['flex flex-col gap-1 md:gap-2 w-full', msg.role === 'user' ? 'items-end' : 'items-start']">
                
                <div :class="['rounded-2xl p-3 md:p-5 backdrop-blur-md border border-white/10 shadow-sm w-full', 
                              msg.role === 'bot' ? 'bg-white/10 rounded-tl-sm' : 'bg-blue-600/20 rounded-tr-sm']">
                  <p class="text-[14px] md:text-[15px] leading-relaxed text-white font-medium whitespace-pre-wrap">
                    {{ msg.text }}
                  </p>
                </div>
                
                <div v-if="msg.songs && msg.songs.length > 0" class="grid grid-cols-1 gap-2 mt-1 w-full">
                  <div v-for="song in msg.songs" :key="song.id" 
                      class="flex items-center gap-3 md:gap-4 p-2 md:p-3 bg-white/5 border border-white/10 rounded-xl hover:bg-white/[0.08] transition-all group w-full relative">
                    
                    <a :href="'https://open.spotify.com/track/' + song.spotify_track_id" 
                      target="_blank" class="flex flex-1 items-center gap-3 min-w-0">
                      
                      <div class="flex-none w-10 h-10 md:w-11 md:h-11 overflow-hidden rounded-lg shadow-lg border border-white/10 bg-[#1A1921] aspect-square relative">
                        <img :src="song.image" 
                            class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300" 
                            :alt="song.title">
                        <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity">
                          <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                          </svg>
                        </div>
                      </div>

                      <div class="flex-1 min-w-0">
                        <h4 class="text-white text-xs md:text-sm font-bold truncate group-hover:text-white transition-colors">
                          {{ song.title }}
                        </h4>
                        <p class="text-white/40 text-[10px] md:text-xs truncate">{{ song.artist }}</p>
                      </div>
                    </a>

                    <button @click.stop="toggleLike(song)" 
                            class="flex-shrink-0 p-2 rounded-full transition-all active:scale-125"
                            :class="song.is_liked ? 'text-[#FF2DF7]' : 'text-white/20 hover:text-white/50'">
                      <svg class="w-5 h-5 md:w-6 md:h-6" 
                          :fill="song.is_liked ? 'currentColor' : 'none'" 
                          viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" 
                              d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                      </svg>
                    </button>
                  </div>
                </div>

                <div v-if="index === 0 && messages.length === 1 && msg.role === 'bot'" class="flex flex-wrap gap-2 mt-1">
                  <button @click="sendMessage('Recomiéndame música similar a Coldplay')" 
                          class="px-3 md:px-4 py-1.5 md:py-2 text-[11px] rounded-full bg-white/10 border border-white/5 hover:bg-white/20 transition-all text-white font-medium">
                    Similar a Coldplay
                  </button>
                  <button @click="sendMessage('Crea una playlist para entrenar')"
                          class="px-3 md:px-4 py-1.5 md:py-2 text-[11px] rounded-full bg-white/10 border border-white/5 hover:bg-white/20 transition-all text-white font-medium">
                    Playlist para entrenar
                  </button>
                </div>

                <span class="text-[9px] md:text-[10px] text-white/30 px-1">{{ formatTime(msg.timestamp) }}</span>
              </div>
            </div>
          </div>

          <div v-if="isLoading" key="loader" class="flex justify-start items-start gap-2 md:gap-4 pb-4 chat-message-enter">
            <div class="flex-none w-8 h-8 md:w-10 md:h-10 rounded-full bg-white/5 flex items-center justify-center border border-white/10 mt-1">
              <div class="w-1.5 h-1.5 bg-[#A855F7] rounded-full animate-pulse"></div>
            </div>
            <div class="text-[11px] md:text-[12px] text-white/40 italic mt-2 md:mt-3">L'assistent està buscant...</div>
          </div>
          
        </div>
      </div>

      <div class="w-full bg-[#1A1921]/95 backdrop-blur-xl p-3 md:p-6 z-20 border-t border-white/5 shadow-[0_-10px_40px_rgba(0,0,0,0.5)]">
        <div class="max-w-4xl mx-auto w-full relative">
          <div class="flex gap-2 md:gap-3 items-center">
            <div class="flex-1 bg-white/5 rounded-xl md:rounded-2xl px-4 md:px-5 h-[48px] md:h-[56px] flex items-center border border-white/5 focus-within:border-[#A855F7]/40 transition-all">
              <input 
                v-model="newMessage"
                @keyup.enter="sendMessage()"
                type="text" 
                placeholder="Pregúntame sobre música, artistas, playlists..." 
                class="w-full bg-transparent border-none outline-none text-white text-sm md:text-base placeholder-[#818091] h-full" 
                :disabled="isLoading"
              />
            </div>

            <button @click="sendMessage()"
                    :disabled="isLoading || !newMessage.trim()"
                    class="w-[48px] h-[48px] md:w-[56px] md:h-[56px] flex-shrink-0 bg-[#A855F7] rounded-xl md:rounded-2xl flex items-center justify-center text-white hover:bg-[#9333EA] transition-all disabled:opacity-30">
                <svg v-if="!isLoading" class="w-5 h-5 md:w-6 md:h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <line x1="22" y1="2" x2="11" y2="13"></line>
                  <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
                <div v-else class="w-4 h-4 md:w-5 md:h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
@keyframes slideUpFade {
  0% {
    opacity: 0;
    transform: translateY(20px);
  }
  100% {
    opacity: 1;
    transform: translateY(0);
  }
}

.chat-message-enter {
  animation: slideUpFade 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.2);
}
</style>