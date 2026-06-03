<script setup lang="ts">
defineProps<{ icon1: string, icon2: string }>();
</script>

<template>
  <div class="flex justify-center gap-6 md:gap-12 opacity-40 pointer-events-none pb-20">
    <div class="w-12 h-12 md:w-16 md:h-16 rounded-2xl bg-[#8E51FF]/10 border border-[#8E51FF]/20 flex items-center justify-center animate-bounce">
      <img :src="icon1" class="w-6 h-6 md:w-8 md:h-8" />
    </div>
    <div class="w-12 h-12 md:w-16 md:h-16 rounded-2xl bg-[#ED6AFF]/10 border border-[#ED6AFF]/20 flex items-center justify-center animate-pulse" style="animation-delay: 1s;">
      <img :src="icon2" class="w-6 h-6 md:w-8 md:h-8" />
    </div>
  </div>
</template>