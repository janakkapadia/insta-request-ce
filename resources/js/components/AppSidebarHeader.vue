<script setup lang="ts">
import { usePage, Link } from '@inertiajs/vue3';
import {
    SlidersHorizontal,
    ChevronDown,
    Check,
    X,
    Pencil,
    Plus,
} from 'lucide-vue-next';
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
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { ScrollBar } from '@/components/ui/scroll-area';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { getMethodTextColor as getMethodColor } from '@/lib/method-colors';
import environments from '@/routes/environments';
import { useWorkspaceStore } from '@/stores/workspace';
import type { BreadcrumbItem } from '@/types';

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

const confirmDialog = ref({
    isOpen: false,
    reqIdToClose: null as string | null,
});

const isMac =
    typeof navigator !== 'undefined'
        ? navigator.userAgent.includes('Mac')
        : false;
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
    const isNewRequestShortcut =
        isMac && isDesktop ? e.metaKey && isT : e.altKey && isT;

    if (isNewRequestShortcut) {
        e.preventDefault();
        handleNewRequest();

        return;
    }

    if (isW && e.altKey) {
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
    if (editingTabId.value === req.id) {
        return;
    }

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
    const input = document.getElementById(
        `tab-rename-input-${req.id}`,
    ) as HTMLInputElement;

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
        result = result.replace(
            envRegex,
            (match: string, var1: string, var2: string) => {
                const varName = (var1 || var2).trim();
                const variable = store.activeEnvironment?.variables?.find(
                    (v: any) => v.key === varName && v.enabled,
                );

                return variable ? variable.value : match;
            },
        );

        // Replace Path Variables
        const pathRegex = /\/:([a-zA-Z0-9_-]+)/g;
        result = result.replace(pathRegex, (match: string, varName: string) => {
            const trimmed = varName.trim();
            const activePathVariables = req.path_variables || [];
            const variable = activePathVariables.find(
                (v: any) => v.key === trimmed && v.enabled && v.value,
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
            .map(
                (p: any) =>
                    `${encodeURIComponent(resolveText(p.key))}=${encodeURIComponent(resolveText(p.value || ''))}`,
            )
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
        class="flex shrink-0 items-center justify-between border-b border-sidebar-border/70 bg-card/10 transition-[width,height] ease-linear"
        :class="[
            page.url.startsWith('/collections')
                ? 'h-12 pr-6 pl-1 group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:pr-4 md:pl-1'
                : 'h-16 px-6 group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4',
        ]"
    >
        <div class="flex min-w-0 flex-1 self-stretch overflow-hidden">
            <template v-if="page.url.startsWith('/collections')">
                <ScrollAreaRoot
                    class="relative flex h-full w-full flex-col overflow-hidden pt-[11px]"
                >
                    <ScrollAreaViewport class="h-full w-full">
                        <div class="flex h-full w-max items-end space-x-1">
                            <ContextMenu
                                v-for="(req, index) in store.openRequests"
                                :key="req.id"
                            >
                                <ContextMenuTrigger as-child>
                                    <component
                                        :is="
                                            req.id.startsWith('new-')
                                                ? 'a'
                                                : Link
                                        "
                                        :href="
                                            req.id.startsWith('new-')
                                                ? undefined
                                                : `/collections/${req.collection_id}/requests/${req.id}`
                                        "
                                        preserve-state
                                        preserve-scroll
                                        :only="[
                                            'activeCollectionId',
                                            'activeRequestId',
                                        ]"
                                        draggable="true"
                                        @click="handleTabClick($event, req)"
                                        @dragstart="
                                            onTabDragStart($event, index)
                                        "
                                        @dragenter.prevent
                                        @dragover.prevent
                                        @drop="onTabDrop($event, index)"
                                        style="-webkit-user-drag: element"
                                        class="group flex h-9 max-w-[200px] min-w-[120px] shrink-0 items-center rounded-t-md border border-b-0 px-3 transition-colors select-none"
                                        :class="[
                                            store.selectedRequest?.id === req.id
                                                ? 'z-10 -mb-[1px] border-border bg-background text-foreground'
                                                : 'border-transparent bg-muted/30 text-muted-foreground hover:bg-muted/60',
                                        ]"
                                    >
                                        <span
                                            class="mr-2 text-[10px] font-bold"
                                            :class="getMethodColor(req.method)"
                                        >
                                            {{ req.method }}
                                        </span>
                                        <input
                                            v-if="editingTabId === req.id"
                                            :id="`tab-rename-input-${req.id}`"
                                            v-model="editingTabName"
                                            class="h-5 min-w-0 flex-1 rounded border border-primary bg-background px-1 text-xs text-foreground outline-none focus:ring-1 focus:ring-primary"
                                            @click.stop
                                            @mousedown.stop
                                            @blur="commitRenameTab(req)"
                                            @keyup.enter="commitRenameTab(req)"
                                            @keyup.esc="editingTabId = null"
                                        />
                                        <span
                                            v-else
                                            @dblclick="
                                                startRenameTab(req, $event)
                                            "
                                            class="flex-1 truncate text-xs font-medium"
                                            >{{ req.name }}</span
                                        >
                                        <span
                                            v-if="
                                                store.getIsRequestDirty(req.id)
                                            "
                                            class="mx-1 h-1.5 w-1.5 shrink-0 rounded-full bg-orange-500"
                                        ></span>
                                        <button
                                            @click.prevent="
                                                closeTab($event, req.id)
                                            "
                                            class="ml-2 shrink-0 rounded-sm p-0.5 opacity-0 transition-all group-hover:opacity-100 hover:bg-muted-foreground/20"
                                            :class="{
                                                'opacity-50':
                                                    store.selectedRequest
                                                        ?.id === req.id,
                                            }"
                                        >
                                            <X class="h-3 w-3" />
                                        </button>
                                    </component>
                                </ContextMenuTrigger>
                                <ContextMenuContent
                                    class="w-48 font-mono text-xs"
                                    @close-auto-focus="
                                        (e: Event) => e.preventDefault()
                                    "
                                >
                                    <ContextMenuItem
                                        @click="startRenameTab(req, $event)"
                                        class="cursor-pointer"
                                    >
                                        Rename
                                    </ContextMenuItem>
                                    <ContextMenuItem
                                        @click="store.duplicateRequest(req)"
                                        class="cursor-pointer"
                                    >
                                        Duplicate
                                    </ContextMenuItem>
                                    <ContextMenuItem
                                        @click="copyUrl(req)"
                                        class="cursor-pointer"
                                    >
                                        Copy URL
                                    </ContextMenuItem>
                                    <ContextMenuSeparator />
                                    <ContextMenuItem
                                        @click="closeTab($event, req.id)"
                                        class="cursor-pointer"
                                    >
                                        Close
                                        <ContextMenuShortcut>
                                            <span v-if="isMac"
                                                ><span
                                                    class="text-[14px] leading-none"
                                                    >⌥</span
                                                >
                                                + W</span
                                            >
                                            <span v-else>Alt + W</span>
                                        </ContextMenuShortcut>
                                    </ContextMenuItem>
                                    <ContextMenuItem
                                        @click="
                                            store.closeOtherRequests(req.id)
                                        "
                                        class="cursor-pointer"
                                    >
                                        Close Others
                                    </ContextMenuItem>
                                    <ContextMenuItem
                                        @click="
                                            store.closeRequestsToRight(req.id)
                                        "
                                        class="cursor-pointer"
                                    >
                                        Close to Right
                                    </ContextMenuItem>
                                </ContextMenuContent>
                            </ContextMenu>

                            <TooltipProvider>
                                <Tooltip :delay-duration="300">
                                    <TooltipTrigger as-child>
                                        <button
                                            @click="handleNewRequest"
                                            class="group flex h-9 min-w-[40px] shrink-0 items-center justify-center rounded-t-md border border-b-0 border-transparent bg-muted/30 px-3 text-muted-foreground transition-colors select-none hover:bg-muted/60"
                                        >
                                            <Plus class="h-4 w-4" />
                                            <span
                                                v-if="
                                                    store.openRequests
                                                        .length === 0
                                                "
                                                class="ml-1.5 text-xs font-medium"
                                                >New Request</span
                                            >
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent
                                        side="bottom"
                                        :side-offset="4"
                                        class="text-xs"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span>New Request</span>
                                            <span
                                                class="font-mono text-xs tracking-widest text-muted-foreground"
                                            >
                                                <span v-if="isMac && isDesktop"
                                                    ><span
                                                        class="text-[14px] leading-none"
                                                        >⌘</span
                                                    >
                                                    + T</span
                                                >
                                                <span v-else-if="isMac"
                                                    ><span
                                                        class="text-[14px] leading-none"
                                                        >⌥</span
                                                    >
                                                    + T</span
                                                >
                                                <span v-else>Alt + T</span>
                                            </span>
                                        </div>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                    </ScrollAreaViewport>
                    <ScrollBar orientation="horizontal" class="mb-0.5 h-1.5" />
                </ScrollAreaRoot>
            </template>
            <template v-else-if="breadcrumbs && breadcrumbs.length > 0">
                <div class="flex h-full items-center">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                </div>
            </template>
        </div>

        <div class="ml-2 flex shrink-0 items-center gap-2">
            <!-- Active Environment Dropdown -->
            <DropdownMenu v-if="page.url.startsWith('/collections')">
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-8 gap-1.5 border-input/60 bg-background/50 px-3 font-mono text-[11px] hover:bg-accent/50"
                    >
                        <SlidersHorizontal
                            class="h-3 w-3 text-muted-foreground"
                        />
                        <span class="max-w-[120px] truncate">
                            {{
                                store.activeEnvironment
                                    ? store.activeEnvironment.name
                                    : 'No Environment'
                            }}
                        </span>
                        <ChevronDown class="ml-0.5 h-3 w-3 opacity-60" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-48 p-1">
                    <DropdownMenuItem
                        @click="store.setActiveEnvironment(null)"
                        class="flex cursor-pointer items-center justify-between rounded px-2 py-1.5 font-mono text-xs"
                    >
                        <span>No Environment</span>
                        <Check
                            v-if="!store.activeEnvironment"
                            class="h-3.5 w-3.5 text-primary"
                        />
                    </DropdownMenuItem>

                    <DropdownMenuSeparator
                        v-if="store.environments.length > 0"
                        class="my-1"
                    />

                    <DropdownMenuItem
                        v-for="env in store.environments"
                        :key="env.id"
                        @click="store.setActiveEnvironment(env)"
                        class="flex cursor-pointer items-center justify-between rounded px-2 py-1.5 font-mono text-xs"
                    >
                        <span class="truncate">{{ env.name }}</span>
                        <Check
                            v-if="store.activeEnvironment?.id === env.id"
                            class="h-3.5 w-3.5 text-primary"
                        />
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <!-- Edit Environments Button -->
            <Link
                v-if="page.url.startsWith('/collections')"
                :href="
                    page.props.currentTeam
                        ? environments.index(
                              (page.props.currentTeam as any).slug,
                          ).url
                        : '#'
                "
            >
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
                    You have unsaved changes in this tab. Are you sure you want
                    to close it and discard them?
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="mt-4 flex justify-end gap-2">
                <Button variant="outline" @click="confirmDialog.isOpen = false"
                    >Cancel</Button
                >
                <Button variant="destructive" @click="confirmCloseTab"
                    >Discard</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
