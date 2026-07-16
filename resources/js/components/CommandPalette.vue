<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Search,
    Database,
    Terminal,
    Clock,
    Play,
    X,
    SlidersHorizontal,
    LayoutGrid,
    Sparkles,
    Github,
} from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';

const isOpen = ref(false);
const isMounted = ref(false);
const query = ref('');
const activeIndex = ref(0);

const inputRef = ref<any>(null);

watch(isOpen, async (newVal) => {
    if (newVal) {
        await nextTick();

        if (inputRef.value) {
            const el = inputRef.value.$el || inputRef.value;
            el.focus?.();
            el.select?.();
        }
    }
});

const searchResults = ref<any[]>([]);

const filteredItems = computed(() => {
    const term = query.value.trim().toLowerCase();

    // Group 1: Navigation Options / Quick Actions
    const quickActions = [
        {
            id: 'nav-dashboard',
            label: 'Go to Dashboard',
            icon: LayoutGrid,
            category: 'Navigation',
            action: () => navigateTo('dashboard'),
        },
        {
            id: 'nav-collections',
            label: 'Go to Collections',
            icon: Database,
            category: 'Navigation',
            action: () => navigateTo('collections'),
        },
        {
            id: 'nav-environments',
            label: 'Go to Environments',
            icon: SlidersHorizontal,
            category: 'Navigation',
            action: () => navigateTo('environments'),
        },
        {
            id: 'nav-history',
            label: 'Go to History Logs',
            icon: Clock,
            category: 'Navigation',
            action: () => navigateTo('history'),
        },
        {
            id: 'nav-github',
            label: 'GitHub Repository',
            subLabel: 'View source code on GitHub',
            icon: Github,
            category: 'Navigation',
            action: () => navigateTo('github'),
        },
    ].filter(
        (a) =>
            term === '' ||
            a.label.toLowerCase().includes(term) ||
            (a.id === 'nav-github' &&
                'github repo repository source star'.includes(term)),
    );

    return [...quickActions, ...searchResults.value];
});

const navigateTo = (routeKey: string) => {
    if (routeKey === 'dashboard') {
        router.visit('/dashboard');
    } else if (routeKey === 'collections') {
        router.visit('/collections');
    } else if (routeKey === 'environments') {
        router.visit('/environments');
    } else if (routeKey === 'history') {
        router.visit('/history');
    } else if (routeKey === 'github') {
        window.open(
            'https://github.com/janakkapadia/insta-request-ce',
            '_blank',
            'noopener,noreferrer',
        );
    }
};

const handleKeyDown = (e: KeyboardEvent) => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        isOpen.value = !isOpen.value;

        if (isOpen.value) {
            query.value = '';
            activeIndex.value = 0;
        }
    }

    if (!isOpen.value) {
        return;
    }

    if (e.key === 'Escape') {
        isOpen.value = false;
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIndex.value =
            (activeIndex.value + 1) % filteredItems.value.length;
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIndex.value =
            (activeIndex.value - 1 + filteredItems.value.length) %
            filteredItems.value.length;
    } else if (e.key === 'Enter') {
        e.preventDefault();

        if (filteredItems.value[activeIndex.value]) {
            filteredItems.value[activeIndex.value].action();
            isOpen.value = false;
        }
    }
};

onMounted(() => {
    isMounted.value = true;
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
});

let debounceTimer: any = null;

watch(query, async (newQuery) => {
    activeIndex.value = 0;
    const term = newQuery.trim();

    if (term.length === 0) {
        searchResults.value = [];

        return;
    }

    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(async () => {
        try {
            const response = await fetch(
                `/api/search?q=${encodeURIComponent(term)}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );

            if (response.ok) {
                const data = await response.json();

                const formattedRequests = data.requests
                    .slice(0, 10)
                    .map((r: any) => ({
                        id: `req-${r.id}`,
                        label: r.name,
                        subLabel: `${r.method} • ${r.url || 'No URL'} (${r.collection?.name || ''}${r.folder?.name ? ' / ' + r.folder.name : ''})`,
                        icon: Terminal,
                        category: 'Requests',
                        action: () => {
                            router.visit(
                                `/collections/${r.collection_id}/requests/${r.id}`,
                            );
                        },
                    }));

                searchResults.value = formattedRequests;
            }
        } catch (e) {
            console.error('Search error', e);
        }
    }, 300);
});
</script>

<template>
    <div>
        <!-- Keyboard trigger badge in main search bar or globally visible -->
        <button
            @click="isOpen = true"
            class="flex w-full shrink-0 items-center gap-2 rounded-lg border bg-muted/30 px-3 py-1.5 text-left text-xs text-muted-foreground transition-colors hover:bg-muted/60"
        >
            <Search class="h-3.5 w-3.5 opacity-60" />
            <span class="flex-1 truncate">Search workspace...</span>
            <kbd
                class="pointer-events-none inline-flex h-5 items-center gap-1 rounded border bg-muted px-1.5 font-mono text-[10px] font-medium text-muted-foreground opacity-100 select-none"
            >
                <span class="text-xs">⌘</span>K
            </kbd>
        </button>

        <!-- Command Palette Modal -->
        <Teleport to="body" v-if="isMounted">
            <div
                v-if="isOpen"
                class="fixed inset-0 z-[9999] flex items-start justify-center px-4 pt-[15vh]"
            >
                <!-- Backdrop -->
                <div
                    class="absolute inset-0 bg-background/80 backdrop-blur-sm transition-opacity duration-300"
                    @click="isOpen = false"
                ></div>

                <!-- Panel -->
                <div
                    class="relative flex max-h-[480px] w-full max-w-lg animate-in flex-col overflow-hidden rounded-xl border bg-background/95 shadow-2xl backdrop-blur-md duration-200 zoom-in-95 fade-in"
                >
                    <!-- Search Box -->
                    <div class="flex items-center gap-3 border-b px-4 py-3">
                        <Search
                            class="h-4 w-4 shrink-0 text-muted-foreground"
                        />
                        <Input
                            ref="inputRef"
                            v-model="query"
                            placeholder="Search collections, requests, history, or actions..."
                            class="h-7 flex-1 border-0 bg-transparent p-0 text-sm focus-visible:ring-0 focus-visible:ring-offset-0"
                            autofocus
                        />
                        <button
                            @click="isOpen = false"
                            class="shrink-0 rounded p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        >
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <!-- Match List -->
                    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                        <ScrollArea
                            class="max-h-[340px] flex-1 overflow-y-auto p-2"
                        >
                            <div
                                v-if="filteredItems.length > 0"
                                class="space-y-4"
                            >
                                <!-- Group items by Category -->
                                <div
                                    v-for="category in [
                                        'Navigation',
                                        'Requests',
                                    ]"
                                    :key="category"
                                    class="space-y-1"
                                >
                                    <div
                                        v-if="
                                            filteredItems.some(
                                                (i) => i.category === category,
                                            )
                                        "
                                        class="flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold tracking-wider text-muted-foreground/80 uppercase"
                                    >
                                        <Sparkles
                                            v-if="category === 'Requests'"
                                            class="h-3 w-3 text-amber-500"
                                        />
                                        {{ category }}
                                    </div>

                                    <div
                                        v-for="item in filteredItems
                                            .map((it, absoluteIdx) => ({
                                                ...it,
                                                absoluteIdx,
                                            }))
                                            .filter(
                                                (i) => i.category === category,
                                            )"
                                        :key="item.id"
                                        @click="
                                            item.action();
                                            isOpen = false;
                                        "
                                        @mouseenter="
                                            activeIndex = item.absoluteIdx
                                        "
                                        :class="[
                                            'flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-xs transition-colors',
                                            activeIndex === item.absoluteIdx
                                                ? 'bg-primary/10 text-primary'
                                                : 'text-foreground/80 hover:bg-muted/50',
                                        ]"
                                    >
                                        <component
                                            :is="item.icon"
                                            class="h-4 w-4 shrink-0 text-muted-foreground opacity-80"
                                        />

                                        <div
                                            class="flex min-w-0 flex-1 flex-col gap-0.5"
                                        >
                                            <div class="truncate font-semibold">
                                                {{ item.label }}
                                            </div>
                                            <div
                                                v-if="item.subLabel"
                                                class="truncate font-mono text-[10px] text-muted-foreground"
                                            >
                                                {{ item.subLabel }}
                                            </div>
                                        </div>

                                        <span
                                            :class="[
                                                'flex shrink-0 items-center gap-1 rounded bg-primary/20 px-1.5 py-0.5 text-[10px] font-bold text-primary transition-opacity',
                                                activeIndex === item.absoluteIdx
                                                    ? 'animate-pulse opacity-100'
                                                    : 'pointer-events-none invisible opacity-0',
                                            ]"
                                        >
                                            Jump
                                            <Play
                                                class="h-2.5 w-2.5 fill-current"
                                            />
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-else
                                class="py-12 text-center text-xs text-muted-foreground"
                            >
                                No matching items or actions found. Try typing
                                another term.
                            </div>
                        </ScrollArea>
                    </div>

                    <!-- Footer help text -->
                    <div
                        class="flex shrink-0 items-center justify-between border-t bg-muted/30 p-3 px-4 text-[10px] font-medium text-muted-foreground"
                    >
                        <div class="flex items-center gap-1">
                            Use
                            <kbd
                                class="rounded border bg-background px-1 font-mono"
                                >↑</kbd
                            >
                            <kbd
                                class="rounded border bg-background px-1 font-mono"
                                >↓</kbd
                            >
                            to navigate
                        </div>
                        <div class="flex items-center gap-1">
                            Press
                            <kbd
                                class="rounded border bg-background px-1 font-mono"
                                >Enter</kbd
                            >
                            to select
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
