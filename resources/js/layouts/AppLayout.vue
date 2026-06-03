<script setup lang="ts">
import Sidebar from '@/components/principalComponents/SideBar.vue';
import Header from '@/components/principalComponents/Header.vue';
import BottomNav from '@/components/principalComponents/BottomNav.vue'; // Nou

defineProps<{ title?: string }>();
</script>

<template>
  <div class="flex h-screen w-full overflow-hidden bg-[#0f172a] text-white relative font-sans">
    
    <div class="absolute top-[-20%] right-[-10%] w-[80vw] h-[80vh] bg-violet-600/20 blur-[120px] rounded-full pointer-events-none z-0"></div>
    <div class="absolute bottom-[-10%] left-[-10%] w-[50vw] h-[50vh] bg-fuchsia-600/10 blur-[100px] rounded-full pointer-events-none z-0"></div>

    <Sidebar />

    <div class="flex-1 flex flex-col min-w-0 relative z-10 pb-16 md:pb-0"> <div class="absolute inset-0 bg-gradient-design z-0"></div>
      
      <Header :title="title" class="w-full h-16 md:h-20 z-20 relative shrink-0" />
      
      <main class="flex-1 overflow-hidden relative flex flex-col z-10">
        <slot />
      </main>
    </div>

    <BottomNav />
  </div>
</template>