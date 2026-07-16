<script setup lang="ts">
import type { ImportFormat, ExportFormat } from '@/types';
import { FORMAT_LABELS, FORMAT_COLORS } from '@/types';

const props = defineProps<{
    format: ImportFormat | ExportFormat;
    size?: 'sm' | 'md';
}>();

const label = FORMAT_LABELS[props.format] ?? props.format;
const color = FORMAT_COLORS[props.format] ?? '#888';
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full border font-semibold select-none"
        :class="
            size === 'sm' ? 'px-1.5 py-0.5 text-[10px]' : 'px-2 py-0.5 text-xs'
        "
        :style="{
            background: color + '18',
            color: color,
            borderColor: color + '40',
        }"
    >
        <span class="h-1.5 w-1.5 rounded-full" :style="{ background: color }" />
        {{ label }}
    </span>
</template>
