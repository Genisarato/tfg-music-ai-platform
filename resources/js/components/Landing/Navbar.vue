<script setup lang="ts">
const imgLogo = "/images/musicAssist.svg";

function logIn(){
  window.location.href = '/auth/spotify';
}
</script>

<template>
  <header class="absolute top-0 w-full h-[70px] md:h-[90px] flex items-center justify-between px-6 md:px-20 bg-black/40 border-b border-white/5 z-50 backdrop-blur-xl">
    
    <div class="flex items-center gap-2 md:gap-3">
      <img :src="imgLogo" alt="Logo" class="w-8 h-8 md:w-10 md:h-10" />
      <span class="text-white font-bold text-lg md:text-[22px] tracking-wide font-display">MusicAssist</span>
    </div>

    <button class="bg-gradient-to-r from-[#8E51FF] to-[#E12AFB] text-white px-5 md:px-8 py-2 md:py-2.5 rounded-full font-bold text-[13px] md:text-[15px] 
                   shadow-[0_10px_25px_-5px_rgba(225,42,251,0.4)] 
                   hover:shadow-[0_15px_30px_-5px_rgba(225,42,251,0.6)] 
                   transition-all duration-300 active:scale-95"
            @click="logIn">
      <span class="hidden sm:inline">Empieza con Spotify</span>
      <span class="sm:hidden">Entrar</span>
    </button>
    
  </header>
</template>