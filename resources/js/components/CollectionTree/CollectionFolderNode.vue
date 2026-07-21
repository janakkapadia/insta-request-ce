<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    ChevronDown,
    Folder,
    MoreVertical,
    Pencil,
    FilePlus,
    FolderPlus,
    Trash2,
    Copy,
    ArrowRight,
} from 'lucide-vue-next';
import { ref, computed, nextTick } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { getMethodBadgeColors as getMethodColor } from '@/lib/method-colors';
import { useWorkspaceStore } from '@/stores/workspace';
import type { RequestItem } from '@/stores/workspace';

const props = defineProps<{
    folder: any;
    collection: any;
    depth?: number;
}>();

const emit = defineEmits<{
    (
        e: 'confirmDelete',
        title: string,
        description: string,
        onConfirm: () => void,
    ): void;
    (e: 'startMoveRequest', req: any): void;
}>();

const store = useWorkspaceStore();
const page = usePage();
const currentDepth = props.depth || 0;

const isSelectionMode = computed(
    () => store.selectionModeCollectionId === props.collection.id,
);

const editingFolderId = ref<string | null>(null);
const editingFolderName = ref('');
const editingRequestId = ref<string | null>(null);
const editingRequestName = ref('');
const newRequestName = ref('');
const newFolderName = ref('');

// Computed for child folders
const childFolders = computed(() => {
    if (!props.collection || !props.collection.folders) {
        return [];
    }

    return props.collection.folders.filter(
        (f: any) => f.parent_id === props.folder.id,
    );
});

const setFolderExpanded = (expanded: boolean) => {
    const f = props.folder as Record<string, any>;
    f.expanded = expanded;
};

const toggle = () => {
    setFolderExpanded(!props.folder.expanded);

    if (!page.url.startsWith('/collections')) {
        router.get(`/collections/${props.collection.id}`);
    }
};

const startRenameFolder = (e?: Event) => {
    e?.stopPropagation();
    editingFolderId.value = props.folder.id;
    editingFolderName.value = props.folder.name;
};

const commitRenameFolder = async () => {
    const trimmed = editingFolderName.value.trim();

    if (trimmed && trimmed !== props.folder.name) {
        await store.renameFolder(props.folder.id, trimmed);
    }

    editingFolderId.value = null;
};

const cancelRenameFolder = () => {
    editingFolderId.value = null;
};

const handleDeleteFolder = () => {
    const hasRequests =
        props.folder.requests && props.folder.requests.length > 0;
    const msg =
        hasRequests || childFolders.value.length > 0
            ? `Are you sure you want to delete "${props.folder.name}" and all of its contents? This action cannot be undone.`
            : `Are you sure you want to delete "${props.folder.name}"? This action cannot be undone.`;
    emit('confirmDelete', 'Delete Folder', msg, () =>
        store.deleteFolder(props.folder.id),
    );
};

const handleFolderDragStart = (e: DragEvent) => {
    store.draggedFolderId = props.folder.id;

    if (e.dataTransfer) {
        e.dataTransfer.setData(
            'text/plain',
            JSON.stringify({
                type: 'instarequest-folder',
                id: props.folder.id,
            }),
        );
        e.dataTransfer.effectAllowed = 'move';
    }
};

const handleDragOver = (e: DragEvent) => {
    if (e.dataTransfer) {
        e.dataTransfer.dropEffect = 'move';
    }
};

const handleDropOnFolder = async (e: DragEvent) => {
    try {
        // Primary: use store state (set during dragstart)
        // Fallback: parse dataTransfer payload
        let type: string | null = null;
        let id: string | null = null;

        if (store.draggedRequestId) {
            type = 'instarequest-request';
            id = store.draggedRequestId;
        } else if (store.draggedFolderId) {
            type = 'instarequest-folder';
            id = store.draggedFolderId;
        } else {
            // Fallback to dataTransfer
            const dataStr = e.dataTransfer?.getData('text/plain');

            if (dataStr) {
                try {
                    const parsed = JSON.parse(dataStr);
                    type = parsed.type;
                    id = parsed.id;
                } catch {
                    /* ignore parse errors */
                }
            }
        }

        if (!type || !id) {
            return;
        }

        if (type === 'instarequest-request') {
            // Find request to make sure it's not already in this folder
            let req: any = null;

            for (const f of props.collection.folders || []) {
                const found = f.requests?.find((r: any) => r.id === id);

                if (found) {
                    req = found;
                    break;
                }
            }

            if (!req) {
                req = props.collection.requests?.find((r: any) => r.id === id);
            }

            if (!req || req.folder_id !== props.folder.id) {
                await store.moveRequest(
                    id,
                    props.collection.id,
                    props.folder.id,
                );
            }
        } else if (type === 'instarequest-folder') {
            if (id !== props.folder.id) {
                await store.moveFolder(
                    id,
                    props.collection.id,
                    props.folder.id,
                );
            }
        }
    } catch (err) {
        console.error('Drop error', err);
    } finally {
        store.draggedRequestId = null;
        store.draggedFolderId = null;
    }
};

// Requests handling
const toggleSelectRequest = (id: string) => {
    const idx = store.selectedRequestIds.indexOf(id);

    if (idx === -1) {
        store.selectedRequestIds.push(id);
    } else {
        store.selectedRequestIds.splice(idx, 1);
    }
};

const handleSelectRequest = (req: RequestItem, forceNewTab = false) => {
    store.selectRequest(req, forceNewTab);
    router.get(
        `/collections/${req.collection_id}/requests/${req.id}`,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            only: ['activeCollectionId', 'activeRequestId'],
        },
    );
};

const startRenameRequest = (req: any, e?: Event) => {
    e?.stopPropagation();
    editingRequestId.value = req.id;
    editingRequestName.value = req.name;
};

const commitRenameRequest = async (req: any) => {
    const trimmed = editingRequestName.value.trim();

    if (trimmed && trimmed !== req.name) {
        await store.renameRequest(req.id, trimmed);
    }

    editingRequestId.value = null;
};

const cancelRenameRequest = () => {
    editingRequestId.value = null;
};

const handleDeleteRequest = (requestId: string) => {
    emit(
        'confirmDelete',
        'Delete Request',
        'Are you sure you want to delete this request? This action cannot be undone.',
        () => store.deleteRequest(requestId),
    );
};

const handleDragStart = (e: DragEvent, req: any) => {
    store.draggedRequestId = req.id;

    if (e.dataTransfer) {
        e.dataTransfer.setData(
            'text/plain',
            JSON.stringify({ type: 'instarequest-request', id: req.id }),
        );
        e.dataTransfer.effectAllowed = 'move';
    }
};

const handleDragEnd = () => {
    store.draggedRequestId = null;
};

const handleCreateRequest = async () => {
    if (newRequestName.value.trim() === '') {
        store.activeNewRequest = null;
        newRequestName.value = '';

        return;
    }

    const name = newRequestName.value.trim();
    store.activeNewRequest = null;
    newRequestName.value = '';

    await store.createRequest(
        props.collection.id,
        name,
        props.folder.id,
    );
    setFolderExpanded(true);
};

const handleCreateFolder = async () => {
    if (newFolderName.value.trim() === '') {
        store.activeNewFolder = null;
        newFolderName.value = '';

        return;
    }

    const name = newFolderName.value.trim();
    store.activeNewFolder = null;
    newFolderName.value = '';

    await store.createFolder(
        props.collection.id,
        name,
        props.folder.id,
    );
    setFolderExpanded(true);
};
const vFocus = {
    mounted: (el: HTMLElement) => {
        nextTick(() => {
            const input =
                el.tagName === 'INPUT' ? el : el.querySelector('input');

            if (input) {
                input.focus();
            }
        });
    },
};
</script>

<template>
    <div
        @dragenter.prevent.stop
        @dragover.prevent.stop="handleDragOver"
        @drop.prevent.stop="handleDropOnFolder"
    >
        <div
            class="group flex cursor-pointer items-center justify-between rounded-md px-2 py-0.5 text-xs select-none hover:bg-muted/50"
            :class="{
                'bg-muted text-foreground':
                    store.selectedRequest?.folder_id === folder.id,
            }"
            draggable="true"
            style="-webkit-user-drag: element"
            @dragstart="handleFolderDragStart"
            @dragend="store.draggedFolderId = null"
        >
            <div
                class="flex min-w-0 flex-1 items-center gap-2"
                @click="editingFolderId !== folder.id && toggle()"
            >
                <div class="flex h-4 w-4 shrink-0 items-center justify-center">
                    <ChevronDown
                        v-if="folder.requests?.length || childFolders.length"
                        class="h-3.5 w-3.5 text-muted-foreground transition-transform"
                        :class="{ 'rotate-[-90deg]': !folder.expanded }"
                    />
                </div>
                <Folder class="h-4 w-4 shrink-0 text-blue-500/80" />
                <input
                    v-if="editingFolderId === folder.id"
                    v-model="editingFolderName"
                    class="h-6 min-w-0 flex-1 rounded border border-primary bg-background px-1 text-xs outline-none focus:ring-1 focus:ring-primary"
                    @click.stop
                    @keyup.enter="commitRenameFolder"
                    @keyup.escape="cancelRenameFolder"
                    @blur="commitRenameFolder"
                    v-focus
                />
                <span
                    v-else
                    class="truncate"
                    :class="{
                        'font-medium':
                            store.selectedRequest?.folder_id === folder.id,
                    }"
                    @dblclick.stop="startRenameFolder()"
                    >{{ folder.name }}</span
                >
            </div>

            <div
                v-if="!isSelectionMode"
                class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100"
            >
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                            title="More Options"
                            @click.stop
                        >
                            <MoreVertical class="h-3.5 w-3.5" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        class="w-48"
                        @close-auto-focus="(e) => e.preventDefault()"
                    >
                        <DropdownMenuItem @click.stop="startRenameFolder()">
                            <Pencil class="mr-2 h-4 w-4" />
                            <span>Rename</span>
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            @click.stop="store.activeNewFolder = folder.id"
                        >
                            <FolderPlus class="mr-2 h-4 w-4" />
                            <span>Add Subfolder</span>
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            @click.stop="store.activeNewRequest = folder.id"
                        >
                            <FilePlus class="mr-2 h-4 w-4" />
                            <span>Add Request</span>
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            @click.stop="handleDeleteFolder"
                            class="text-red-600 focus:text-red-600"
                        >
                            <Trash2 class="mr-2 h-4 w-4" />
                            <span>Delete</span>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <div v-if="store.activeNewFolder === folder.id" class="py-1 pr-2 pl-2">
            <Input
                v-model="newFolderName"
                placeholder="Subfolder name..."
                class="h-7 text-xs"
                @keyup.enter="handleCreateFolder"
                @blur="handleCreateFolder"
                v-focus
            />
        </div>

        <div v-if="store.activeNewRequest === folder.id" class="py-1 pr-2 pl-2">
            <Input
                v-model="newRequestName"
                placeholder="Request name..."
                class="h-7 text-xs"
                @keyup.enter="handleCreateRequest"
                @blur="handleCreateRequest"
                v-focus
            />
        </div>

        <div
            v-if="folder.expanded"
            class="ml-2 space-y-0.5 border-l border-sidebar-border/50 pl-2"
            @dragenter.prevent
            @dragover.prevent
            @drop.prevent.stop="handleDropOnFolder"
        >
            <!-- Nested Folders -->
            <CollectionFolderNode
                v-for="childFolder in childFolders"
                :key="childFolder.id"
                :folder="childFolder"
                :collection="collection"
                :depth="currentDepth + 1"
                @confirmDelete="
                    (title, desc, confirmFn) =>
                        emit('confirmDelete', title, desc, confirmFn)
                "
                @startMoveRequest="(req) => emit('startMoveRequest', req)"
            />

            <!-- Requests -->
            <div
                v-for="req in folder.requests"
                :key="req.id"
                class="group/req mt-0.5 flex cursor-pointer items-center justify-between rounded-md px-2 py-0.5 text-xs select-none hover:bg-muted/50"
                :class="{
                    'bg-muted text-foreground':
                        store.selectedRequest?.id === req.id,
                }"
                draggable="true"
                style="-webkit-user-drag: element"
                @dragstart="handleDragStart($event, req)"
                @dragend="handleDragEnd"
                @dragenter.prevent.stop
                @dragover.prevent.stop="handleDragOver"
                @drop.prevent.stop="handleDropOnFolder"
                @pointerdown.middle.stop.prevent
                @auxclick.middle.prevent="
                    !isSelectionMode && handleSelectRequest(req, true)
                "
                @click="
                    (e) =>
                        isSelectionMode
                            ? toggleSelectRequest(req.id)
                            : handleSelectRequest(req, e.metaKey || e.ctrlKey)
                "
            >
                <div class="flex min-w-0 flex-1 items-center gap-2">
                    <div
                        v-if="isSelectionMode"
                        @click.stop
                        class="flex shrink-0 items-center"
                    >
                        <Checkbox
                            :model-value="
                                store.selectedRequestIds.includes(req.id)
                            "
                            @update:model-value="toggleSelectRequest(req.id)"
                            class="h-3.5 w-3.5 shrink-0 rounded border-muted-foreground/30"
                        />
                    </div>
                    <span
                        v-else
                        :class="[
                            'shrink-0 rounded px-1.5 py-0.5 text-[9px] font-bold',
                            getMethodColor(req.method),
                        ]"
                    >
                        {{ req.method }}
                    </span>
                    <input
                        v-if="editingRequestId === req.id"
                        v-model="editingRequestName"
                        class="h-6 min-w-0 flex-1 rounded border border-primary bg-background px-1 text-xs outline-none focus:ring-1 focus:ring-primary"
                        @click.stop
                        @keyup.enter="commitRenameRequest(req)"
                        @keyup.escape="cancelRenameRequest"
                        @blur="commitRenameRequest(req)"
                        v-focus
                    />
                    <span
                        v-else
                        class="truncate"
                        @dblclick.stop="startRenameRequest(req, $event)"
                        >{{ req.name }}</span
                    >
                </div>
                <div
                    v-if="!isSelectionMode"
                    class="flex items-center gap-1 opacity-0 transition-opacity group-hover/req:opacity-100"
                >
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                title="More Options"
                                @click.stop
                            >
                                <MoreVertical class="h-3.5 w-3.5" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            class="w-48"
                            @close-auto-focus="(e) => e.preventDefault()"
                        >
                            <DropdownMenuItem
                                @click.stop="startRenameRequest(req, $event)"
                            >
                                <Pencil class="mr-2 h-4 w-4" />
                                <span>Rename</span>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                @click.stop="store.cloneRequest(req.id)"
                            >
                                <Copy class="mr-2 h-4 w-4" />
                                <span>Duplicate</span>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                @click.stop="emit('startMoveRequest', req)"
                            >
                                <ArrowRight class="mr-2 h-4 w-4" />
                                <span>Move...</span>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                @click.stop="handleDeleteRequest(req.id)"
                                class="text-red-600 focus:text-red-600"
                            >
                                <Trash2 class="mr-2 h-4 w-4" />
                                <span>Delete</span>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </div>
    </div>
</template>
