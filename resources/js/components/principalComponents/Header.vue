<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth.user);

const pageTitle = computed(() => {
  const url = page.url;
  if (url.startsWith('/favorites')) return 'Canciones Favoritas';
  return 'Asistente Musical';
});
</script>

<template>
  <header class="flex items-center justify-between px-10 w-full h-20 bg-[#020618]/60 backdrop-blur-md border-b border-white/[0.03] z-20">
    
    <div>
      <h1 class="text-[22px] font-bold text-white tracking-wide font-display">
        {{ pageTitle }}
      </h1>
    </div>
    
    <div class="flex items-center gap-7">

      <div class="flex items-center gap-3 cursor-pointer hover:opacity-80 transition-opacity">
        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-violet-600 to-fuchsia-600 p-[2px]">
          <img :src="user?.avatar || 'https://ui-avatars.com/api/?name=Usuario&background=random'" class="w-full h-full rounded-full object-cover border border-[#1E1B29]" alt="Avatar" />
        </div>
        
        <div class="flex items-center gap-2">
          <div class="flex flex-col items-start">
            <span class="text-[14px] font-semibold text-white leading-tight">
              {{ user?.name || 'Usuario de Spotify' }}
            </span>
          </div>
        </div>
      </div>

    </div>
  </header>
</template>