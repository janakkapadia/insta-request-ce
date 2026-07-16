<script setup lang="ts">
import { AlertCircle, AlertTriangle, Info } from 'lucide-vue-next';
import type { ValidationMessage } from '@/types';

defineProps<{
    messages: ValidationMessage[];
}>();

const getIcon = (level: string) => {
    switch (level) {
        case 'error':
            return AlertCircle;
        case 'warning':
            return AlertTriangle;
        default:
            return Info;
    }
};

const getClasses = (level: string) => {
    switch (level) {
        case 'error':
            return 'text-red-500 bg-red-500/10 border-red-500/20';
        case 'warning':
            return 'text-amber-500 bg-amber-500/10 border-amber-500/20';
        default:
            return 'text-blue-500 bg-blue-500/10 border-blue-500/20';
    }
};
</script>

<template>
    <div v-if="messages.length > 0" class="space-y-1.5">
        <div
            v-for="(msg, i) in messages"
            :key="i"
            class="flex items-start gap-2 rounded-md border px-3 py-2 text-xs"
            :class="getClasses(msg.level)"
        >
            <component
                :is="getIcon(msg.level)"
                class="mt-0.5 h-3.5 w-3.5 shrink-0"
            />
            <div class="min-w-0 flex-1">
                <span>{{ msg.message }}</span>
                <span
                    v-if="msg.path"
                    class="ml-1.5 font-mono text-[10px] opacity-60"
                >
                    {{ msg.path }}
                </span>
            </div>
        </div>
    </div>
    <div
        v-else
        class="py-3 text-center text-xs text-muted-foreground opacity-60"
    >
        No validation issues found.
    </div>
</template>
