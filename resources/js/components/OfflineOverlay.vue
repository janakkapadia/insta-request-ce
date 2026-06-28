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
            <div class="icon-wrapper">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 12h4l2-9 5 18 4-10h5"/>
                </svg>
            </div>
            <h1 class="text-2xl font-semibold tracking-tight text-foreground">Waiting for connection</h1>
            <p class="text-muted-foreground text-center max-w-[280px] mt-2">Jackman requires an active internet connection to sync your data.</p>
            <div class="spinner"></div>
        </div>
    </div>
</template>

<style scoped>
.offline-overlay {
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background-color: hsl(var(--background));
    color: hsl(var(--foreground));
    user-select: none;
    -webkit-user-select: none;
}

.offline-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    animation: fadeIn 0.5s ease-out forwards;
}

.icon-wrapper {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    background: rgba(245, 158, 11, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #f59e0b;
    margin-bottom: 8px;
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.icon-wrapper svg {
    width: 32px;
    height: 32px;
}

.spinner {
    width: 20px;
    height: 20px;
    border: 2px solid rgba(245, 158, 11, 0.3);
    border-radius: 50%;
    border-top-color: #f59e0b;
    animation: spin 1s linear infinite;
    margin-top: 24px;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.95); }
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>
