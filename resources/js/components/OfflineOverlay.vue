<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';

const isOffline = ref(false);

const handleOffline = () => {
    isOffline.value = true;
};

const handleOnline = () => {
    isOffline.value = false;
};

onMounted(() => {

    // Check initial state
    if (typeof window !== 'undefined' && !navigator.onLine) {
        isOffline.value = true;
    }

    // Add event listeners
    window.addEventListener('offline', handleOffline);
    window.addEventListener('online', handleOnline);
});

onUnmounted(() => {
    window.removeEventListener('offline', handleOffline);
    window.removeEventListener('online', handleOnline);
});
</script>

<template>
    <div v-if="isOffline" class="fixed inset-0 z-[9999] bg-background/95 backdrop-blur-sm flex flex-col items-center justify-center pointer-events-auto">
        <div class="offline-container">
            <div class="w-16 h-16 rounded-2xl bg-muted/20 flex items-center justify-center text-primary mb-2 icon-pulse">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 12h4l2-9 5 18 4-10h5"/>
                </svg>
            </div>
            <h1 class="text-2xl font-semibold tracking-tight text-foreground text-center">Waiting for connection</h1>
            <p class="text-muted-foreground text-center max-w-[280px] mt-2">An active internet connection is required to sync your data.</p>
            <div class="w-5 h-5 border-2 border-border border-t-primary rounded-full spinner-spin mt-6"></div>
        </div>
    </div>
</template>

<style scoped>
.offline-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    animation: fadeIn 0.5s ease-out forwards;
}

.icon-pulse {
    animation: pulseIcon 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.spinner-spin {
    animation: spinIcon 1s linear infinite;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes pulseIcon {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.95); }
}

@keyframes spinIcon {
    to { transform: rotate(360deg); }
}
</style>
