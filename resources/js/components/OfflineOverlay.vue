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
    <div
        v-if="isOffline"
        class="pointer-events-auto fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-background/95 backdrop-blur-sm"
    >
        <div class="offline-container">
            <div
                class="icon-pulse mb-2 flex h-16 w-16 items-center justify-center rounded-2xl bg-muted/20 text-primary"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="32"
                    height="32"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M2 12h4l2-9 5 18 4-10h5" />
                </svg>
            </div>
            <h1
                class="text-center text-2xl font-semibold tracking-tight text-foreground"
            >
                Waiting for connection
            </h1>
            <p class="mt-2 max-w-[280px] text-center text-muted-foreground">
                An active internet connection is required to sync your data.
            </p>
            <div
                class="spinner-spin mt-6 h-5 w-5 rounded-full border-2 border-border border-t-primary"
            ></div>
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
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes pulseIcon {
    0%,
    100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.5;
        transform: scale(0.95);
    }
}

@keyframes spinIcon {
    to {
        transform: rotate(360deg);
    }
}
</style>
