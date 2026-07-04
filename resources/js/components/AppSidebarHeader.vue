<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { SlidersHorizontal, ChevronDown, Check, X, Pencil, Plus } from 'lucide-vue-next';
import { ScrollAreaRoot, ScrollAreaViewport } from 'reka-ui';
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSeparator,
    ContextMenuShortcut,
    ContextMenuTrigger,
} from '@/components/ui/context-menu';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { 
    DropdownMenu, 
    DropdownMenuTrigger, 
    DropdownMenuContent, 
    DropdownMenuItem,
    DropdownMenuSeparator
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { ScrollBar } from '@/components/ui/scroll-area';
import environments from '@/routes/environments';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const store = useWorkspaceStore();
const page = usePage();

import { getMethodTextColor as getMethodColor } from '@/lib/method-colors';
import { useWorkspaceStore } from '@/stores/workspace';

const confirmDialog = ref({
    isOpen: false,
    reqIdToClose: null as string | null,
});

const isMac = typeof navigator !== 'undefined' ? navigator.userAgent.includes('Mac') : false;
const isDesktop = typeof window !== 'undefined' && !!(window as any).__TAURI__;

const closeTab = (e: Event | null, reqId: string) => {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }

    if (store.getIsRequestDirty(reqId)) {
        confirmDialog.value = {
            isOpen: true,
            reqIdToClose: reqId,
        };

        return;
    }

    store.closeRequest(reqId);
};

const handleKeyDown = (e: KeyboardEvent) => {
    const isW = e.code === 'KeyW' || e.key.toLowerCase() === 'w';
    const isT = e.code === 'KeyT' || e.key.toLowerCase() === 't';
    
    // Command + T for Mac Desktop, Option + T for Mac Web, Alt + T for Windows
    const isNewRequestShortcut = isMac && isDesktop 
        ? (e.metaKey && isT)
        : (e.altKey && isT);

    if (isNewRequestShortcut) {
        e.preventDefault();
        handleNewRequest();
        return;
    }

    if (isW && (e.metaKey || e.ctrlKey || e.altKey)) {
        if (confirmDialog.value.isOpen) {
            e.preventDefault();
            return;
        }
        if (store.selectedRequest?.id) {
            e.preventDefault();
            closeTab(null, store.selectedRequest.id);
        }
    }
};

const confirmCloseTab = () => {
    if (confirmDialog.value.reqIdToClose) {
        store.closeRequest(confirmDialog.value.reqIdToClose);
        confirmDialog.value.isOpen = false;
        confirmDialog.value.reqIdToClose = null;
    }
};

const handleNewRequest = async () => {
    if (store.collections.length === 0) {
        store.showNewCollectionModal = true;
    } else {
        await store.createRequest('', 'New Request');
    }
};

const handleTabClick = (e: MouseEvent, req: any) => {
    if (editingTabId.value === req.id) return;

    if (req.id.startsWith('new-')) {
        e.preventDefault();
    }
    store.selectRequest(req, false);
};

const editingTabId = ref<string | null>(null);
const editingTabName = ref('');

const startRenameTab = async (req: any, e?: Event) => {
    e?.preventDefault();
    e?.stopPropagation();
    editingTabName.value = req.name;
    editingTabId.value = req.id;
    
    await nextTick();
    const input = document.getElementById(`tab-rename-input-${req.id}`) as HTMLInputElement;
    if (input) {
        input.focus();
        input.select();
    }
};

const commitRenameTab = async (req: any) => {
    const trimmed = editingTabName.value.trim();
    if (trimmed && trimmed !== req.name) {
        await store.renameRequest(req.id, trimmed);
    }
    editingTabId.value = null;
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
});

const onTabDragStart = (e: DragEvent, index: number) => {
    if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', JSON.stringify({ index }));
    }
};

const onTabDrop = (e: DragEvent, dropIndex: number) => {
    if (e.dataTransfer) {
        try {
            const data = JSON.parse(e.dataTransfer.getData('text/plain'));

            if (data && typeof data.index === 'number') {
                const dragIndex = data.index;

                if (dragIndex !== dropIndex) {
                    store.reorderRequests(dragIndex, dropIndex);
                }
            }
        } catch (err) {
            console.error('Failed to parse drag data', err);
        }
    }
};

const resolveUrl = (req: any) => {
    const baseUrl = (req.url || '').split('?')[0];

    const resolveText = (text: string) => {
        let result = text || '';
        // Replace Environment Variables
        const envRegex = /\{\{([^}]+)\}\}|\{([^}]+)\}/g;
        result = result.replace(envRegex, (match: string, var1: string, var2: string) => {
            const varName = (var1 || var2).trim();
            const variable = store.activeEnvironment?.variables?.find(
                (v: any) => v.key === varName && v.enabled
            );

            return variable ? variable.value : match;
        });

        // Replace Path Variables
        const pathRegex = /\/:([a-zA-Z0-9_-]+)/g;
        result = result.replace(pathRegex, (match: string, varName: string) => {
            const trimmed = varName.trim();
            const activePathVariables = req.path_variables || [];
            const variable = activePathVariables.find(
                (v: any) => v.key === trimmed && v.enabled && v.value
            );

            return variable ? `/${variable.value}` : match;
        });

        return result;
    };

    let finalUrl = resolveText(baseUrl);

    // Append active query parameters
    if (Array.isArray(req.query_params) && req.query_params.length > 0) {
        const queryParams = req.query_params
            .filter((p: any) => p.key && p.enabled !== false)
            .map((p: any) => `${encodeURIComponent(resolveText(p.key))}=${encodeURIComponent(resolveText(p.value || ''))}`)
            .join('&');
            
        if (queryParams) {
            finalUrl = `${finalUrl}?${queryParams}`;
        }
    } else if ((req.url || '').includes('?')) {
        // Fallback to the raw query string if no query_params array exists
        const rawQuery = (req.url || '').split('?').slice(1).join('?');

        if (rawQuery) {
            finalUrl = `${finalUrl}?${resolveText(rawQuery)}`;
        }
    }

    return finalUrl;
};

const copyUrl = async (req: any) => {
    try {
        const actualUrl = resolveUrl(req);
        await navigator.clipboard.writeText(actualUrl || '');
    } catch (err) {
        console.error('Failed to copy URL', err);
    }
};
</script>

<template>
    <header
        class="flex shrink-0 items-center justify-between border-b border-sidebar-border/70 transition-[width,height] ease-linear bg-card/10"
        :class="[
            page.url.startsWith('/collections') 
                ? 'h-12 pt-2 pr-6 pl-1 md:pr-4 md:pl-1 group-has-data-[collapsible=icon]/sidebar-wrapper:h-12' 
                : 'h-16 px-6 md:px-4 group-has-data-[collapsible=icon]/sidebar-wrapper:h-12'
        ]"
    >
        <div class="flex self-stretch flex-1 min-w-0 overflow-hidden">
            
            <template v-if="page.url.startsWith('/collections')">
                <ScrollAreaRoot class="relative overflow-hidden w-full h-full flex flex-col">
                    <ScrollAreaViewport class="w-full h-full">
                        <div class="flex items-end space-x-1 w-max h-full">
                            <ContextMenu v-for="(req, index) in store.openRequests" :key="req.id">
                                <ContextMenuTrigger as-child>
                                    <component
                                        :is="req.id.startsWith('new-') ? 'a' : Link"
                                        :href="req.id.startsWith('new-') ? undefined : `/collections/${req.collection_id}/requests/${req.id}`"
                                        preserve-state
                                        preserve-scroll
                                        :only="['activeCollectionId', 'activeRequestId']"
                                        draggable="true"
                                        @click="handleTabClick($event, req)"
                                        @dragstart="onTabDragStart($event, index)"
                                        @dragenter.prevent
                                        @dragover.prevent
                                        @drop="onTabDrop($event, index)"
                                        style="-webkit-user-drag: element;"
                                        class="group flex items-center h-9 px-3 min-w-[120px] max-w-[200px] rounded-t-md border border-b-0 transition-colors shrink-0 select-none"
                                        :class="[
                                            store.selectedRequest?.id === req.id 
                                                ? 'bg-background border-border text-foreground z-10 -mb-[1px]' 
                                                : 'bg-muted/30 border-transparent text-muted-foreground hover:bg-muted/60'
                                        ]"
                                    >
                                        <span class="text-[10px] font-bold mr-2" :class="getMethodColor(req.method)">
                                            {{ req.method }}
                                        </span>
                                        <input
                                            v-if="editingTabId === req.id"
                                            :id="`tab-rename-input-${req.id}`"
                                            v-model="editingTabName"
                                            class="flex-1 min-w-0 h-5 text-xs bg-background border border-primary rounded px-1 outline-none focus:ring-1 focus:ring-primary text-foreground"
                                            @click.stop
                                            @mousedown.stop
                                            @blur="commitRenameTab(req)"
                                            @keyup.enter="commitRenameTab(req)"
                                            @keyup.esc="editingTabId = null"
                                        />
                                        <span v-else @dblclick="startRenameTab(req, $event)" class="text-xs truncate flex-1 font-medium">{{ req.name }}</span>
                                        <span v-if="store.getIsRequestDirty(req.id)" class="w-1.5 h-1.5 bg-orange-500 rounded-full shrink-0 mx-1"></span>
                                        <button 
                                            @click.prevent="closeTab($event, req.id)"
                                            class="ml-2 p-0.5 rounded-sm opacity-0 group-hover:opacity-100 hover:bg-muted-foreground/20 transition-all shrink-0"
                                            :class="{ 'opacity-50': store.selectedRequest?.id === req.id }"
                                        >
                                            <X class="h-3 w-3" />
                                        </button>
                                    </component>
                                </ContextMenuTrigger>
                                <ContextMenuContent class="w-48 text-xs font-mono" @close-auto-focus="(e: Event) => e.preventDefault()">
                                    <ContextMenuItem @click="startRenameTab(req, $event)" class="cursor-pointer">
                                        Rename
                                    </ContextMenuItem>
                                    <ContextMenuItem @click="store.duplicateRequest(req)" class="cursor-pointer">
                                        Duplicate
                                    </ContextMenuItem>
                                    <ContextMenuItem @click="copyUrl(req)" class="cursor-pointer">
                                        Copy URL
                                    </ContextMenuItem>
                                    <ContextMenuSeparator />
                                    <ContextMenuItem @click="closeTab($event, req.id)" class="cursor-pointer">
                                        Close
                                        <ContextMenuShortcut>
                                            <span v-if="isMac && isDesktop"><span class="text-[14px] leading-none">⌘</span> + W</span>
                                            <span v-else-if="isMac"><span class="text-[14px] leading-none">⌥</span> + W</span>
                                            <span v-else>Alt + W</span>
                                        </ContextMenuShortcut>
                                    </ContextMenuItem>
                                    <ContextMenuItem @click="store.closeOtherRequests(req.id)" class="cursor-pointer">
                                        Close Others
                                    </ContextMenuItem>
                                    <ContextMenuItem @click="store.closeRequestsToRight(req.id)" class="cursor-pointer">
                                        Close to Right
                                    </ContextMenuItem>
                                </ContextMenuContent>
                            </ContextMenu>

                            <TooltipProvider>
                                <Tooltip :delay-duration="300">
                                    <TooltipTrigger as-child>
                                        <button
                                            @click="handleNewRequest"
                                            class="group flex items-center justify-center h-9 px-3 min-w-[40px] rounded-t-md border border-b-0 border-transparent bg-muted/30 text-muted-foreground hover:bg-muted/60 transition-colors shrink-0 select-none"
                                        >
                                            <Plus class="h-4 w-4" />
                                            <span v-if="store.openRequests.length === 0" class="text-xs font-medium ml-1.5">New Request</span>
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent side="bottom" :side-offset="4" class="text-xs">
                                        <div class="flex items-center gap-2">
                                            <span>New Request</span>
                                            <span class="text-xs text-muted-foreground tracking-widest font-mono">
                                                <span v-if="isMac && isDesktop"><span class="text-[14px] leading-none">⌘</span> + T</span>
                                                <span v-else-if="isMac"><span class="text-[14px] leading-none">⌥</span> + T</span>
                                                <span v-else>Alt + T</span>
                                            </span>
                                        </div>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                    </ScrollAreaViewport>
                    <ScrollBar orientation="horizontal" class="h-1.5 mb-0.5" />
                </ScrollAreaRoot>
            </template>
            <template v-else-if="breadcrumbs && breadcrumbs.length > 0">
                <div class="flex items-center h-full">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                </div>
            </template>
        </div>

        <div class="flex items-center gap-2 shrink-0 ml-2">
            <!-- Active Environment Dropdown -->
            <DropdownMenu v-if="page.url.startsWith('/collections')">
                <DropdownMenuTrigger as-child>
                    <Button 
                        variant="outline" 
                        size="sm" 
                        class="h-8 text-[11px] gap-1.5 font-mono px-3 bg-background/50 border-input/60 hover:bg-accent/50"
                    >
                        <SlidersHorizontal class="h-3 w-3 text-muted-foreground" />
                        <span class="truncate max-w-[120px]">
                            {{ store.activeEnvironment ? store.activeEnvironment.name : 'No Environment' }}
                        </span>
                        <ChevronDown class="h-3 w-3 opacity-60 ml-0.5" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-48 p-1">
                    <DropdownMenuItem 
                        @click="store.setActiveEnvironment(null)"
                        class="text-xs px-2 py-1.5 cursor-pointer rounded flex items-center justify-between font-mono"
                    >
                        <span>No Environment</span>
                        <Check v-if="!store.activeEnvironment" class="h-3.5 w-3.5 text-primary" />
                    </DropdownMenuItem>
                    
                    <DropdownMenuSeparator v-if="store.environments.length > 0" class="my-1" />
                    
                    <DropdownMenuItem 
                        v-for="env in store.environments"
                        :key="env.id"
                        @click="store.setActiveEnvironment(env)"
                        class="text-xs px-2 py-1.5 cursor-pointer rounded flex items-center justify-between font-mono"
                    >
                        <span class="truncate">{{ env.name }}</span>
                        <Check v-if="store.activeEnvironment?.id === env.id" class="h-3.5 w-3.5 text-primary" />
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <!-- Edit Environments Button -->
            <Link v-if="page.url.startsWith('/collections')" :href="page.props.currentTeam ? environments.index((page.props.currentTeam as any).slug).url : '#'">
                <Button
                    variant="ghost"
                    size="icon"
                    class="h-8 w-8 text-muted-foreground hover:text-foreground"
                    title="Edit Environments"
                    @mousedown.stop
                >
                    <Pencil class="h-4 w-4" />
                </Button>
            </Link>
        </div>
    </header>

    <!-- Confirm Close Tab Dialog -->
    <Dialog v-model:open="confirmDialog.isOpen">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>Unsaved Changes</DialogTitle>
                <DialogDescription>
                    You have unsaved changes in this tab. Are you sure you want to close it and discard them?
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="flex gap-2 justify-end mt-4">
                <Button variant="outline" @click="confirmDialog.isOpen = false">Cancel</Button>
                <Button variant="destructive" @click="confirmCloseTab">Discard</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
