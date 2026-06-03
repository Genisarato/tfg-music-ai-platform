<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';

const props = defineProps({
  songs: Object, // Paginador de Laravel
  isSyncing: Boolean,
  filters: Object
});

// --- ESTAT ---
const llistaCancons = ref<any[]>([]);
const total = ref(0);
const scrollContainer = ref<HTMLElement | null>(null);
const loadingMore = ref(false);
const currentPage = ref(1);

// Filtres reactius
const search = ref(props.filters?.search || '');
const sort = ref(props.filters?.sort || 'recent');

watch(() => props.filters, (newFilters) => {
    search.value = newFilters?.search || '';
    sort.value = newFilters?.sort || 'recent';
}, { deep: true });

const syncLocalState = () => {
  if (props.songs) {
    llistaCancons.value = props.songs.data;
    total.value = props.songs.total;
    currentPage.value = props.songs.current_page;
  }
};

onMounted(() => {
  syncLocalState();
});

// --- FILTRES I CERCADOR ---
const applyFilters = debounce(() => {
  router.get(
    '/liked-songs',
    { search: search.value, sort: sort.value },
    {
      preserveState: true,
      preserveScroll: true,
      only: ['songs', 'filters'],
      onSuccess: () => {
        syncLocalState(); 
      }
    }
  );
}, 300);

watch(search, () => applyFilters());

const updateSort = (newSort: string) => {
  sort.value = newSort;
  applyFilters();
};

const refreshSongs = () => {
  router.reload({ only: ['songs', 'isSyncing'], onSuccess: () => syncLocalState() });
};

// --- SCROLL INFINIT ---
const handleScroll = () => {
  const element = scrollContainer.value;
  if (!element || loadingMore.value) return;

  // Si som a 100px del final, carreguem més
  if (element.scrollTop + element.clientHeight >= element.scrollHeight - 100) {
    loadMore();
  }
};

const loadMore = () => {
  if (llistaCancons.value.length >= total.value) return;

  loadingMore.value = true;
  const nextPage = currentPage.value + 1;

  router.get(
    '/liked-songs',
    { page: nextPage, search: search.value, sort: sort.value },
    {
      preserveScroll: true,
      preserveState: true,
      only: ['songs'],
      onSuccess: (page: any) => {
        const noves = page.props.songs.data;
        llistaCancons.value = [...llistaCancons.value, ...noves];
        currentPage.value = nextPage;
        loadingMore.value = false;
      },
    }
  );
};

// --- HELPERS DE FORMAT ---
const formatDuration = (ms: number) => {
  if (!ms) return "--:--";
  const minutes = Math.floor(ms / 60000);
  const seconds = ((ms % 60000) / 1000).toFixed(0);
  return `${minutes}:${Number(seconds) < 10 ? '0' : ''}${seconds}`;
};

const formatDate = (dateString: string) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  return new Intl.DateTimeFormat('es-ES', { 
    day: '2-digit', 
    month: 'short', 
    year: 'numeric' 
  }).format(date);
};

const syncSongs = () => {
  router.post('/liked-songs/sync-songs', {}, {
    preserveScroll: true,
    onSuccess: () => {
    }
  });
};
</script>

<template>
  <AppLayout title="Canciones Favoritas">
    <div class="flex flex-col h-full w-full relative overflow-hidden">
      
      <div class="absolute inset-0 bg-gradient-design z-0"></div>

      <div 
        ref="scrollContainer" 
        @scroll="handleScroll"
        class="flex-1 overflow-y-auto p-4 md:p-8 custom-scrollbar z-10 relative min-h-0"
      >
        
        <div v-if="isSyncing" class="mb-6 p-4 bg-white/5 backdrop-blur-md border border-white/10 rounded-2xl flex items-center gap-3 animate-pulse max-w-2xl">
          <div class="w-2 h-2 bg-[#A855F7] rounded-full shadow-[0_0_10px_#A855F7]"></div>
          <p class="text-white/80 text-xs md:text-sm font-medium">Sincronizando tu biblioteca de Spotify...</p>
        </div>

        <div class="flex flex-col md:flex-row items-center md:items-end gap-6 md:gap-10 mb-10 animate-fade-in text-center md:text-left">
          <div class="w-32 h-32 md:w-56 md:h-56 bg-gradient-to-br from-[#FF2DF7] to-[#8E51FF] rounded-[30px] md:rounded-[40px] shadow-[0_20px_50px_rgba(142,81,255,0.3)] flex items-center justify-center flex-shrink-0">
             <svg class="text-white w-16 h-16 md:w-28 md:h-28" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
          </div>

          <div class="flex flex-col gap-2 md:gap-3">
            <span class="text-white/50 uppercase text-[10px] md:text-xs font-bold tracking-[0.2em]">Mi Colección</span>
            <h1 class="text-4xl md:text-7xl font-black text-white tracking-tighter leading-tight md:leading-[0.8]">Favoritos</h1>
            
            <div class="flex flex-col md:flex-row items-center gap-4 md:gap-6 mt-2 md:mt-4">
              <div class="flex items-center gap-2 text-white text-sm font-medium">
                <span>{{ total }} canciones</span>
                <span class="w-1 h-1 bg-white/20 rounded-full"></span>
                <span class="text-white/40">Spotify Sync</span>
              </div>

              <button 
                @click="syncSongs" 
                :disabled="isSyncing"
                class="flex items-center gap-2 px-4 py-1.5 bg-white/5 border border-white/10 rounded-full text-[11px] font-bold text-white transition-all active:scale-95 disabled:opacity-50 hover:bg-white/10"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" :class="{'animate-spin': isSyncing}"><path d="M23 4v6h-6M1 20v-6h6M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"></path></svg>
                Actualizar lista
              </button>
            </div>
          </div>
        </div>

        <div class="flex flex-col md:flex-row gap-4 mb-8 items-center justify-between">
          <div class="flex gap-2 w-full md:w-auto justify-center md:justify-start">
            <button 
              @click="updateSort('recent')"
              class="flex-1 md:flex-none px-6 py-2 rounded-full text-[11px] md:text-xs font-bold transition-all border border-white/5"
              :class="sort === 'recent' ? 'bg-[#A855F7] text-white' : 'bg-white/5 text-white/60'"
            >
              Recientes
            </button>
            <button 
              @click="updateSort('az')"
              class="flex-1 md:flex-none px-6 py-2 rounded-full text-[11px] md:text-xs font-bold transition-all border border-white/5"
              :class="sort === 'az' ? 'bg-[#A855F7] text-white' : 'bg-white/5 text-white/60'"
            >
              A-Z
            </button>
          </div>

          <div class="relative w-full md:w-80">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
              <svg class="h-4 w-4 text-white/30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </div>
            <input 
              v-model="search"
              type="text" 
              placeholder="Buscar..." 
              class="w-full bg-[#1D293D]/40 border border-white/10 rounded-full py-2 md:py-2.5 pl-11 pr-4 text-sm text-white placeholder-white/20 outline-none focus:ring-1 focus:ring-[#A855F7]"
            />
          </div>
        </div>

        <div class="flex flex-col gap-2 md:gap-3 pb-20">
          <div v-for="(song, index) in llistaCancons" :key="song.id" 
               class="flex items-center gap-3 md:gap-6 p-3 md:p-4 bg-[#1D293D]/40 backdrop-blur-sm rounded-xl md:rounded-[14px] border border-white/5 hover:bg-[#1D293D]/60 transition-all group">
            
            <div class="w-6 md:w-10 flex items-center justify-center relative flex-shrink-0">
              <span class="text-[#D1D5DC]/40 font-bold text-xs md:text-sm group-hover:opacity-0 transition-opacity">
                {{ index + 1 }}
              </span>
              <svg class="absolute opacity-0 group-hover:opacity-100 text-[#FF2DF7] w-4 h-4 md:w-5 md:h-5 cursor-pointer" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
            </div>
            
            <img :src="song.image" class="w-10 h-10 md:w-14 md:h-14 rounded-lg md:rounded-xl object-cover flex-shrink-0" alt="cover" />
            
            <div class="flex-1 min-w-0">
              <h4 class="text-white font-bold text-sm md:text-[15px] truncate group-hover:text-[#FF2DF7] transition-colors">{{ song.title }}</h4>
              <p class="text-[#D1D5DC]/60 text-[10px] md:text-xs truncate">{{ song.artist }}</p>
            </div>
            
            <div class="flex-1 text-[#D1D5DC]/40 text-[13px] hidden lg:block truncate">
              {{ song.album_name }}
            </div>

            <div class="w-24 md:w-32 text-[#D1D5DC]/40 text-[11px] md:text-[13px] hidden sm:block text-right">
              {{ formatDate(song.liked_at) }}
            </div>
            
            <div class="w-12 md:w-16 text-[#D1D5DC]/40 text-[11px] md:text-[13px] font-medium text-right flex-shrink-0">
              {{ formatDuration(song.duration_ms) }}
            </div>
          </div>
          <div v-if="llistaCancons.length === 0 && !loadingMore" 
              class="flex flex-col items-center justify-center py-20 animate-fade-in">
            
            <div class="w-24 h-24 bg-white/5 rounded-full flex items-center justify-center mb-6 border border-white/10 shadow-xl shadow-purple-500/5">
              <svg class="w-12 h-12 text-white/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
              </svg>
            </div>

            <h3 class="text-xl font-bold text-white mb-2">Tu biblioteca está vacía</h3>
            <p class="text-white/40 text-center max-w-xs mb-8 text-sm px-4">
              Parece que aún no tienes favoritos o los filtros son demasiado estrictos. ¡Sincroniza o descubre algo nuevo!
            </p>

            <Link 
              href="/chatbot" 
              class="px-8 py-3 bg-[#8E51FF] hover:bg-[#7a3ff2] text-white rounded-full font-bold text-sm transition-all shadow-lg shadow-purple-500/20 active:scale-95"
            >
              Descubrir música con la IA
            </Link>
          </div>

          <div v-if="loadingMore" class="flex justify-center py-6">
             <div class="w-6 h-6 border-2 border-white/20 border-t-[#A855F7] rounded-full animate-spin"></div>
          </div>
        </div>

      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.8s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.custom-scrollbar::-webkit-scrollbar {
  width: 6px; 
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent; 
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 20px; 
  border: 1px solid rgba(255, 255, 255, 0.05); 
  transition: all 0.3s ease;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(168, 85, 247, 0.4);
}

.custom-scrollbar {
  scrollbar-width: thin;
  scrollbar-color: rgba(255, 255, 255, 0.1) transparent;
}
</style>