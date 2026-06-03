<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
defineProps<{ imgBadge: string }>();

function logIn(){
  window.location.href = '/auth/spotify';
}
</script>

<template>
  <section class="relative z-10 pt-32 md:pt-40 text-center px-6 w-full flex flex-col items-center">
    
    <div class="inline-flex items-center gap-2 bg-[#7F22FE]/10 border border-[#7F22FE]/30 px-4 md:px-5 py-1.5 rounded-full mb-8 animate-fade-in">
      <img :src="imgBadge" alt="icon" class="w-3.5 h-3.5 md:w-4 md:h-4" />
      <span class="text-[#A684FF] text-[11px] md:text-[13px] font-medium tracking-wide uppercase">Tu asistente musical inteligente</span>
    </div>

    <h1 class="text-4xl sm:text-5xl md:text-[84px] font-bold leading-[1.1] tracking-tight mb-6 font-display max-w-5xl mx-auto">
      <span class="text-white">Descubre Música</span> <br />
      <span class="bg-gradient-to-r from-white via-[#ED6AFF] to-[#8E51FF] bg-clip-text text-transparent">
        Como Nunca Antes
      </span>
    </h1>

    <p class="text-[#99A1AF] text-base md:text-[19px] max-w-[700px] mx-auto mb-10 leading-relaxed">
      Conecta tu cuenta de Spotify i deja que nuestra IA<br class="hidden md:block" />
      te ayude a explorar y disfrutar de tu música<br class="hidden md:block" />
      favorita de formas que nunca imaginaste.
    </p>

    <div class="flex flex-col sm:flex-row justify-center gap-4 mb-16 w-full sm:w-auto">
      
      <button @click="logIn"
            class="bg-gradient-to-r from-[#8E51FF] to-[#E12AFB] text-white px-10 py-4 rounded-full font-bold text-base 
                   shadow-[0_0_30px_rgba(142,81,255,0.4)] 
                   hover:shadow-[0_0_50px_rgba(142,81,255,0.6)] 
                   hover:-translate-y-1 transition-all duration-300 active:scale-95">
        Comenzar Ahora
      </button>
      
      <button class="bg-white/5 border border-white/10 text-white px-10 py-4 rounded-full font-bold text-base hover:bg-white/10 transition-all duration-300">
        Saber Más
      </button>

    </div>
  </section>
</template>