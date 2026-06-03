<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import chatIcon from '@/assets/icons/chatIcon.svg';
import iconLik from '@/assets/icons/iconLik.svg';
import iconLogOut from '@/assets/icons/iconLogOut.svg';

const page = usePage();
const currentRoute = computed(() => page.url);

const menuItems = [
  { id: 'assistant', href: '/chatbot', icon: chatIcon, label: 'Chat' },
  { id: 'favorites', href: '/liked-songs', icon: iconLik, label: 'Favoritos' },
];

const isActive = (href: string) => currentRoute.value.startsWith(href);

const logout = () => {
    router.post('/logout');
};
</script>

<template>
  <nav class="md:hidden fixed bottom-0 left-0 right-0 h-18 bg-[#020618]/90 backdrop-blur-xl border-t border-white/10 z-50 flex items-center justify-around px-4 pb-2 pt-1">
    
    <Link 
      v-for="item in menuItems" 
      :key="item.id"
      :href="item.href"
      class="flex flex-col items-center justify-center gap-1 w-24 h-14 rounded-[12px] transition-all duration-300 border"
      :class="[
        isActive(item.href) 
          ? 'bg-[#8E51FF]/10 text-white border-[#8E51FF]/50 shadow-[0_0_15px_rgba(142,81,255,0.1)]' 
          : 'text-[#99A1AF] border-transparent hover:bg-white/5'
      ]"
    >
      <img 
        :src="item.icon" 
        class="w-5 h-5 transition-all" 
        :class="[isActive(item.href) ? 'opacity-100 brightness-125' : 'opacity-60']" 
      />
      <span class="text-[10px] font-bold tracking-tight">{{ item.label }}</span>
    </Link>

    <button 
      @click="logout" 
      class="flex flex-col items-center justify-center gap-1 w-24 h-14 rounded-[12px] text-[#99A1AF] border border-transparent hover:bg-red-500/10 hover:text-red-400 transition-all"
    >
      <img :src="iconLogOut" class="w-5 h-5 opacity-60" alt="Logout" />
      <span class="text-[10px] font-bold tracking-tight">Salir</span>
    </button>
  </nav>
</template>