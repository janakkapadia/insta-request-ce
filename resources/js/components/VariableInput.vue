<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useWorkspaceStore } from '@/stores/workspace';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: '',
    },
    type: {
        type: String,
        default: 'text',
    },
    inputClass: {
        type: String,
        default: '',
    },
    pathVariables: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue', 'focus', 'blur', 'input']);

const store = useWorkspaceStore();

const localValue = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val),
});

const urlInputEl = ref<HTMLInputElement | null>(null);
const urlBackgroundEl = ref<HTMLDivElement | null>(null);

const syncScroll = () => {
    if (urlInputEl.value && urlBackgroundEl.value) {
        urlBackgroundEl.value.scrollLeft = urlInputEl.value.scrollLeft;
    }
};

watch(localValue, () => {
    nextTick(() => {
        syncScroll();
    });
});

const urlVariables = computed(() => {
    if (!localValue.value) {
        return [];
    }

    // Environment Variables
    const envMatches: string[] = [];
    const envRegex = /\{\{([^}]+)\}\}|\{([^}]+)\}/g;
    let envMatch;

    while ((envMatch = envRegex.exec(localValue.value)) !== null) {
        const varName = (envMatch[1] || envMatch[2]).trim();

        if (!envMatches.includes(varName)) {
            envMatches.push(varName);
        }
    }

    const variables = envMatches.map((name) => {
        const variable = store.activeEnvironment?.variables?.find(
            (v: any) => v.key === name && v.enabled,
        );

        if (variable) {
            return {
                name,
                type: 'env',
                display: `{${name}}`,
                resolved: true,
                value: variable.value,
                scope: store.activeEnvironment?.name || 'Active Environment',
            };
        }

        return {
            name,
            type: 'env',
            display: `{${name}}`,
            resolved: false,
            value: 'N/A (Variable is disabled or not found)',
            scope: store.activeEnvironment
                ? `Unresolved in ${store.activeEnvironment.name}`
                : 'No active environment selected',
        };
    });

    // Path Variables
    const pathMatches: string[] = [];
    const pathRegex = /\/:([a-zA-Z0-9_-]+)/g;
    let pathMatch;

    while ((pathMatch = pathRegex.exec(localValue.value)) !== null) {
        const varName = pathMatch[1].trim();

        if (!pathMatches.includes(varName)) {
            pathMatches.push(varName);
        }
    }

    pathMatches.forEach((name) => {
        // Fallback to store if prop is not passed (or empty array)
        const activePathVariables =
            props.pathVariables && props.pathVariables.length > 0
                ? props.pathVariables
                : store.selectedRequest?.path_variables || [];

        const variable = activePathVariables.find(
            (v: any) => v.key === name && v.enabled,
        ) as any;

        if (variable && variable.value) {
            variables.push({
                name,
                type: 'path',
                display: `:${name}`,
                resolved: true,
                value: variable.value,
                scope: 'Path Variable',
            });
        } else {
            variables.push({
                name,
                type: 'path',
                display: `:${name}`,
                resolved: false,
                value: variable
                    ? 'N/A (Value is empty)'
                    : 'N/A (Disabled or not found)',
                scope: 'Path Variable',
            });
        }
    });

    return variables;
});

const urlHighlightedHtml = computed(() => {
    if (!localValue.value) {
        return `<span class="text-muted-foreground/50">${props.placeholder}</span>`;
    }

    let escaped = localValue.value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    const envRegex = /\{\{([^}]+)\}\}|\{([^}]+)\}/g;
    escaped = escaped.replace(envRegex, (match, var1, var2) => {
        const trimmed = (var1 || var2).trim();
        const resolved = store.activeEnvironment?.variables?.some(
            (v: any) => v.key === trimmed && v.enabled,
        );
        const colorClass = resolved
            ? 'text-amber-500 bg-amber-500/10 ring-1 ring-inset ring-amber-500/30 py-0.5 rounded font-mono font-medium'
            : 'text-destructive bg-destructive/10 ring-1 ring-inset ring-destructive/30 py-0.5 rounded font-mono font-medium';

        return `<span class="${colorClass}">${match}</span>`;
    });

    const pathRegex = /\/:([a-zA-Z0-9_-]+)/g;
    escaped = escaped.replace(pathRegex, (match, varName) => {
        const trimmed = varName.trim();
        const activePathVariables =
            props.pathVariables && props.pathVariables.length > 0
                ? props.pathVariables
                : store.selectedRequest?.path_variables || [];

        const resolved = activePathVariables.some(
            (v: any) => v.key === trimmed && v.enabled && v.value,
        );
        const colorClass = resolved
            ? 'text-sky-500 bg-sky-500/10 ring-1 ring-inset ring-sky-500/30 py-0.5 rounded font-mono font-medium'
            : 'text-destructive bg-destructive/10 ring-1 ring-inset ring-destructive/30 py-0.5 rounded font-mono font-medium';

        return `/<span class="${colorClass}">:${varName}</span>`;
    });

    return escaped;
});

const isHovered = ref(false);
const isFocused = ref(false);

const isTooltipOpen = computed(() => {
    return isHovered.value && !isFocused.value && urlVariables.value.length > 0;
});
</script>

<template>
    <TooltipProvider>
        <Tooltip :delay-duration="150" :open="isTooltipOpen">
            <TooltipTrigger as-child>
                <div
                    class="relative flex w-full items-center rounded-md border border-input bg-background shadow-sm transition-colors focus-within:border-ring focus-within:ring-1 focus-within:ring-ring"
                    v-bind="$attrs"
                    @mouseenter="isHovered = true"
                    @mouseleave="isHovered = false"
                >
                    <!-- Styled Highlighted Overlay -->
                    <div
                        v-if="type !== 'password'"
                        ref="urlBackgroundEl"
                        class="pointer-events-none absolute inset-0 z-0 flex items-center overflow-hidden px-2 font-mono text-xs whitespace-pre select-none"
                        style="
                            font-family:
                                ui-monospace, SFMono-Regular, Menlo, Monaco,
                                Consolas, monospace !important;
                            letter-spacing: 0px !important;
                        "
                        v-html="urlHighlightedHtml"
                    ></div>

                    <!-- Transparent/Masked Interactive Input -->
                    <input
                        ref="urlInputEl"
                        v-model="localValue"
                        :type="type"
                        @scroll="syncScroll"
                        @input="
                            emit('input', $event);
                            syncScroll();
                        "
                        @focus="
                            isFocused = true;
                            emit('focus', $event);
                        "
                        @blur="
                            isFocused = false;
                            emit('blur', $event);
                        "
                        :placeholder="placeholder"
                        class="relative z-10 h-full w-full border-none bg-transparent px-2 font-mono text-xs caret-foreground focus:ring-0 focus:outline-none"
                        :class="[
                            inputClass,
                            type === 'password'
                                ? 'text-foreground'
                                : 'text-transparent',
                        ]"
                        style="
                            font-family:
                                ui-monospace, SFMono-Regular, Menlo, Monaco,
                                Consolas, monospace !important;
                            letter-spacing: 0px !important;
                        "
                    />
                </div>
            </TooltipTrigger>
            <TooltipContent
                v-if="urlVariables.length > 0"
                side="bottom"
                align="start"
                :side-offset="4"
                class="pointer-events-none z-[100] max-w-sm rounded-lg border bg-popover p-3 text-xs text-popover-foreground shadow-md"
            >
                <div class="space-y-2">
                    <div
                        class="mb-1 flex items-center justify-between border-b border-border pb-1.5 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                    >
                        <span>Variables in Input</span>
                        <span class="font-mono text-[8px] opacity-75"
                            >Hover to view values</span
                        >
                    </div>
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr
                                class="text-[9px] text-muted-foreground uppercase"
                            >
                                <th class="pr-4 pb-1 font-semibold">
                                    Variable
                                </th>
                                <th class="pr-4 pb-1 font-semibold">Value</th>
                                <th class="pb-1 font-semibold">Scope</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="v in urlVariables"
                                :key="v.name + '-' + v.type"
                                class="border-t border-border/50 text-[10px]"
                            >
                                <td
                                    class="max-w-[100px] truncate py-1.5 pr-4 font-mono font-medium"
                                    :class="
                                        v.resolved
                                            ? v.type === 'path'
                                                ? 'text-sky-500'
                                                : 'text-amber-500'
                                            : 'text-rose-500'
                                    "
                                >
                                    {{ v.display }}
                                </td>
                                <td
                                    class="max-w-[150px] truncate py-1.5 pr-4 font-mono text-muted-foreground"
                                    :title="v.value"
                                >
                                    {{ v.value }}
                                </td>
                                <td
                                    class="max-w-[100px] truncate py-1.5 font-mono text-muted-foreground"
                                >
                                    {{ v.scope }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
