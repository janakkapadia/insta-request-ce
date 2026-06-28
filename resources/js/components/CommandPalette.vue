<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Search, Database, Terminal, Clock, Settings, FilePlus, Play, X, SlidersHorizontal, LayoutGrid, Sparkles } from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';

const isOpen = ref(false);
const isMounted = ref(false);
const query = ref('');
const activeIndex = ref(0);
const store = useWorkspaceStore();
const page = usePage();

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
        { id: 'nav-dashboard', label: 'Go to Dashboard', icon: LayoutGrid, category: 'Navigation', action: () => navigateTo('dashboard') },
        { id: 'nav-collections', label: 'Go to Collections', icon: Database, category: 'Navigation', action: () => navigateTo('collections') },
        { id: 'nav-environments', label: 'Go to Environments', icon: SlidersHorizontal, category: 'Navigation', action: () => navigateTo('environments') },
        { id: 'nav-history', label: 'Go to History Logs', icon: Clock, category: 'Navigation', action: () => navigateTo('history') },
    ].filter(a => term === '' || a.label.toLowerCase().includes(term));

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
        activeIndex.value = (activeIndex.value + 1) % filteredItems.value.length;
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIndex.value = (activeIndex.value - 1 + filteredItems.value.length) % filteredItems.value.length;
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
            const response = await fetch(`/api/search?q=${encodeURIComponent(term)}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                const data = await response.json();
                
                const formattedRequests = data.requests.slice(0, 10).map((r: any) => ({
                    id: `req-${r.id}`,
                    label: r.name,
                    subLabel: `${r.method} • ${r.url || 'No URL'} (${r.collection?.name || ''}${r.folder?.name ? ' / ' + r.folder.name : ''})`,
                    icon: Terminal,
                    category: 'Requests',
                    action: () => {
                        router.visit(`/collections/${r.collection_id}/requests/${r.id}`);
                    }
                }));
                
                searchResults.value = formattedRequests;
            }
        } catch (e) {
            console.error('Search error', e);
        }
    }, 300);
});
import { getMethodBadgeColors as getMethodColor } from '@/lib/method-colors';
import { useWorkspaceStore } from '@/stores/workspace';
</script>

<template>
    <div>
        <!-- Keyboard trigger badge in main search bar or globally visible -->
        <button 
            @click="isOpen = true" 
            class="flex items-center gap-2 px-3 py-1.5 text-xs text-muted-foreground border rounded-lg bg-muted/30 hover:bg-muted/60 transition-colors w-full text-left shrink-0"
        >
            <Search class="w-3.5 h-3.5 opacity-60" />
            <span class="flex-1 truncate">Search workspace...</span>
            <kbd class="pointer-events-none inline-flex h-5 select-none items-center gap-1 rounded border bg-muted px-1.5 font-mono text-[10px] font-medium text-muted-foreground opacity-100">
                <span class="text-xs">⌘</span>K
            </kbd>
        </button>

        <!-- Command Palette Modal -->
        <Teleport to="body" v-if="isMounted">
            <div 
                v-if="isOpen" 
                class="fixed inset-0 z-[9999] flex items-start justify-center pt-[15vh] px-4"
            >
            <!-- Backdrop -->
            <div 
                class="absolute inset-0 bg-background/80 backdrop-blur-sm transition-opacity duration-300"
                @click="isOpen = false"
            ></div>

            <!-- Panel -->
            <div 
                class="relative w-full max-w-lg overflow-hidden rounded-xl border bg-background/95 shadow-2xl backdrop-blur-md flex flex-col max-h-[480px] animate-in fade-in zoom-in-95 duration-200"
            >
                <!-- Search Box -->
                <div class="flex items-center gap-3 px-4 py-3 border-b">
                    <Search class="w-4 h-4 text-muted-foreground shrink-0" />
                    <Input 
                        ref="inputRef"
                        v-model="query" 
                        placeholder="Search collections, requests, history, or actions..." 
                        class="flex-1 border-0 bg-transparent p-0 text-sm focus-visible:ring-0 focus-visible:ring-offset-0 h-7"
                        autofocus
                    />
                    <button 
                        @click="isOpen = false" 
                        class="p-1 rounded hover:bg-muted text-muted-foreground hover:text-foreground shrink-0 transition-colors"
                    >
                        <X class="w-3.5 h-3.5" />
                    </button>
                </div>

                <!-- Match List -->
                <div class="flex-1 overflow-hidden flex flex-col min-h-0">
                    <ScrollArea class="flex-1 max-h-[340px] overflow-y-auto p-2">
                        <div v-if="filteredItems.length > 0" class="space-y-4">
                            <!-- Group items by Category -->
                            <div 
                                v-for="category in ['Navigation', 'Requests']" 
                                :key="category"
                                class="space-y-1"
                            >
                                <div 
                                    v-if="filteredItems.some(i => i.category === category)"
                                    class="px-3 py-1.5 text-[10px] font-bold text-muted-foreground/80 tracking-wider uppercase flex items-center gap-1.5"
                                >
                                    <Sparkles v-if="category === 'Requests'" class="w-3 h-3 text-amber-500" />
                                    {{ category }}
                                </div>
                                
                                <div 
                                    v-for="(item, idx) in filteredItems.map((it, absoluteIdx) => ({ ...it, absoluteIdx })).filter(i => i.category === category)" 
                                    :key="item.id"
                                    @click="item.action(); isOpen = false;"
                                    @mouseenter="activeIndex = item.absoluteIdx"
                                    :class="[
                                        'flex items-center gap-3 rounded-lg px-3 py-2.5 cursor-pointer transition-colors text-xs',
                                        activeIndex === item.absoluteIdx 
                                            ? 'bg-primary/10 text-primary font-medium' 
                                            : 'hover:bg-muted/50 text-foreground/80'
                                    ]"
                                >
                                    <component :is="item.icon" class="w-4 h-4 shrink-0 text-muted-foreground opacity-80" />
                                    
                                    <div class="flex-1 min-w-0 flex flex-col gap-0.5">
                                        <div class="font-semibold truncate">{{ item.label }}</div>
                                        <div 
                                            v-if="item.subLabel" 
                                            class="text-[10px] text-muted-foreground truncate font-mono"
                                        >
                                            {{ item.subLabel }}
                                        </div>
                                    </div>
                                    
                                    <span 
                                        v-if="activeIndex === item.absoluteIdx" 
                                        class="text-[10px] font-bold bg-primary/20 text-primary px-1.5 py-0.5 rounded flex items-center gap-1 shrink-0 animate-pulse"
                                    >
                                        Jump <Play class="w-2.5 h-2.5 fill-current" />
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="py-12 text-center text-xs text-muted-foreground">
                            No matching items or actions found. Try typing another term.
                        </div>
                    </ScrollArea>
                </div>

                <!-- Footer help text -->
                <div class="p-3 bg-muted/30 border-t flex justify-between items-center text-[10px] text-muted-foreground font-medium px-4 shrink-0">
                    <div class="flex items-center gap-1">
                        Use <kbd class="border rounded bg-background px-1 font-mono">↑</kbd> <kbd class="border rounded bg-background px-1 font-mono">↓</kbd> to navigate
                    </div>
                    <div class="flex items-center gap-1">
                        Press <kbd class="border rounded bg-background px-1 font-mono">Enter</kbd> to select
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</div>
</template>
