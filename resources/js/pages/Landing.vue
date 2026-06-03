<script setup lang="ts">
import FloatingDecor from '@/components/Landing/FloatingDecor.vue';
import HeroSection from '@/components/Landing/HeroSection.vue';
import Navbar from '@/components/Landing/Navbar.vue';

const imgIconPetit = "/images/iconIAPetit.svg";
const imgNota = "/images/iconNota.svg";
const imgAuriculars = "/images/iconAuriculars.svg";
</script>

<template>
  <div class="min-h-screen bg-black relative overflow-hidden font-sans flex flex-col">
    <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-[#8E51FF]/20 blur-[120px] rounded-full -mr-64 -mt-32"></div>
    <div class="absolute bottom-0 left-0 w-[400px] h-[400px] bg-[#ED6AFF]/10 blur-[100px] rounded-full -ml-32 -mb-32"></div>

    <Navbar class="relative z-50" />

    <main class="relative z-10 flex-1 flex flex-col items-center justify-center min-h-[90vh]">
      <HeroSection :imgBadge="imgIconPetit" />
      <FloatingDecor :icon1="imgNota" :icon2="imgAuriculars" />
    </main>
  </div>
</template>