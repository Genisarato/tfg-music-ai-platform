<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import musicAssistLogo from '@/assets/icons/musicAssist.svg';
import chatIcon from '@/assets/icons/chatIcon.svg';
import iconLik from '@/assets/icons/iconLik.svg';
import iconLogOut from '@/assets/icons/iconLogOut.svg';

const page = usePage();
const currentRoute = computed(() => page.url);

const menuItems = [
  { id: 'assistant', href: '/chatbot', icon: chatIcon, label: 'Asistente Musical' },
  { id: 'favorites', href: '/liked-songs', icon: iconLik, label: 'Canciones Favoritas' },
];

const isActive = (href: string) => currentRoute.value.startsWith(href);

const logout = () => {
    router.post('/logout');
};
</script>

<template>
  <aside class="hidden md:flex flex-col h-full border-r border-white/10 bg-[#020618]/95 backdrop-blur-xl z-20 transition-all duration-300 md:w-20 lg:w-64">
    
    <div class="flex items-center h-20 px-6 border-b border-white/10 lg:justify-start md:justify-center">
      <div class="flex items-center gap-3">
        <img :src="musicAssistLogo" alt="Logo" class="w-8 h-8" />
        <span class="font-bold text-xl text-white font-display tracking-wide hidden lg:inline">MusicAssist</span>
      </div>
    </div>

    <nav class="flex-1 py-6 flex flex-col gap-2 px-4">
      <Link 
        v-for="item in menuItems" 
        :key="item.id"
        :href="item.href"
        class="flex items-center gap-3 px-4 h-11 rounded-[12px] transition-all duration-300 lg:justify-start md:justify-center"
        :class="[
          isActive(item.href) 
            ? 'bg-[#8E51FF]/10 text-white border border-[#8E51FF]/50' 
            : 'text-[#99A1AF] hover:bg-white/5 hover:text-white border border-transparent'
        ]"
      >
        <img :src="item.icon" class="w-[18px] h-[18px]" :class="{'brightness-125': isActive(item.href)}" />
        <span class="font-medium text-[14px] hidden lg:inline">{{ item.label }}</span>
      </Link>
    </nav>

    <div class="p-4 border-t border-white/10">
      <button 
        @click="logout" 
        class="flex items-center gap-3 w-full px-4 h-11 rounded-[12px] transition-all hover:bg-red-500/10 text-[#99A1AF] hover:text-red-400 group"
      >
        <img :src="iconLogOut" class="w-[18px] h-[18px] opacity-60 group-hover:opacity-100 transition-all" alt="Logout" />
        <span class="font-medium text-[14px] hidden lg:inline">Cerrar Sesión</span>
      </button>
    </div>
  </aside>
</template>