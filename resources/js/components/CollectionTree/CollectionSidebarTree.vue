<script setup lang="ts">
import { usePage, router } from '@inertiajs/vue3';
import { ChevronRight, ChevronDown, FileJson, Plus, FolderPlus, FilePlus, Trash2, Pencil, MoreVertical, Copy, ArrowRight, Folder, CheckSquare, Loader2 } from 'lucide-vue-next';
import { ref, watch, computed, nextTick } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { getMethodBadgeColors as getMethodColor } from '@/lib/method-colors';
import collections from '@/routes/collections';
import type { RequestItem } from '@/stores/workspace';
import { useWorkspaceStore } from '@/stores/workspace';
import CollectionFolderNode from './CollectionFolderNode.vue';
import MoveRequestModal from './MoveRequestModal.vue';

const vFocus = {
    mounted: (el: HTMLElement) => {
        nextTick(() => {
            const input = el.tagName === 'INPUT' ? el : el.querySelector('input');

            if (input) {
input.focus();
}
        });
    }
};

const store = useWorkspaceStore();
const page = usePage();

const props = defineProps<{
    collection: CollectionItem;
}>();

const newFolderName = ref('');
const newRequestName = ref('');

const isSelectionMode = computed(() => store.selectionModeCollectionId === props.collection.id);

const rootFolders = computed(() => {
    if (!props.collection || !props.collection.folders) {
return [];
}

    return props.collection.folders.filter((f: any) => !f.parent_id);
});

const confirmDialog = ref({
    isOpen: false,
    title: '',
    description: '',
    confirmText: 'Delete',
    onConfirm: () => {},
});

const confirmDelete = (title: string, description: string, onConfirm: () => void) => {
    confirmDialog.value = {
        isOpen: true,
        title,
        description,
        confirmText: 'Delete',
        onConfirm: () => {
            onConfirm();
            confirmDialog.value.isOpen = false;
        }
    };
};

// ── Selection Mode state ───────────────────────────────────────────────

const exitSelectionMode = () => {
    if (store.selectionModeCollectionId === props.collection.id) {
        store.selectionModeCollectionId = null;
        store.selectedRequestIds = [];
    }
};

const toggleSelectRequest = (id: string) => {
    const idx = store.selectedRequestIds.indexOf(id);

    if (idx === -1) {
        store.selectedRequestIds.push(id);
    } else {
        store.selectedRequestIds.splice(idx, 1);
    }
};

const handleDeleteSelectedRequests = () => {
    if (store.selectedRequestIds.length === 0) {
return;
}
    
    confirmDialog.value = {
        isOpen: true,
        title: 'Delete Requests',
        description: `Are you sure you want to delete ${store.selectedRequestIds.length} request(s)? This action cannot be undone.`,
        confirmText: 'Delete',
        onConfirm: async () => {
            await store.deleteRequestsBatch(store.selectedRequestIds);
            exitSelectionMode();
            confirmDialog.value.isOpen = false;
        }
    };
};

watch(() => props.collection.id, () => {
    exitSelectionMode();
});

const toggle = async (item: any) => {
    item.expanded = !item.expanded;

    if (item.expanded && item.team_id && !item.has_loaded_details) {
        await store.fetchCollectionDetails(item.id);
    }
};

const handleSelectRequest = (req: RequestItem, forceNewTab = false) => {
    store.selectRequest(req, forceNewTab);
    router.get(`/collections/${req.collection_id}/requests/${req.id}`, {}, {
        preserveState: true,
        preserveScroll: true,
        only: ['activeCollectionId', 'activeRequestId']
    });
};

const handleCreateFolder = async (collectionId: string, parentId: string | null = null) => {
    if (newFolderName.value.trim() === '') {
        store.activeNewFolder = null;
        newFolderName.value = '';

        return;
    }
    
    await store.createFolder(collectionId, newFolderName.value.trim(), parentId);
    
    store.activeNewFolder = null;
    newFolderName.value = '';
};

const handleCreateRequest = async (collectionId: string, folderId: string | null = null) => {
    if (newRequestName.value.trim() === '') {
        store.activeNewRequest = null;
        newRequestName.value = '';

        return;
    }
    
    await store.createRequest(collectionId, newRequestName.value.trim(), folderId);
    
    store.activeNewRequest = null;
    newRequestName.value = '';
};

const handleDeleteFolder = (folder: any) => {
    const hasRequests = folder.requests && folder.requests.length > 0;
    const msg = hasRequests
        ? `Are you sure you want to delete "${folder.name}" and all of its requests? This action cannot be undone.`
        : `Are you sure you want to delete "${folder.name}"? This action cannot be undone.`;
    confirmDelete(
        'Delete Folder',
        msg,
        () => store.deleteFolder(folder.id)
    );
};

const handleDeleteRequest = (requestId: string) => {
    confirmDelete(
        'Delete Request',
        'Are you sure you want to delete this request? This action cannot be undone.',
        () => store.deleteRequest(requestId)
    );
};


const editingFolderId = ref<string | null>(null);
const editingFolderName = ref('');
const editingRequestId = ref<string | null>(null);
const editingRequestName = ref('');

const startRenameFolder = (folder: any, e?: Event) => {
    e?.stopPropagation();
    editingFolderId.value = folder.id;
    editingFolderName.value = folder.name;
};

const commitRenameFolder = async (folder: any) => {
    const trimmed = editingFolderName.value.trim();

    if (trimmed && trimmed !== folder.name) {
        await store.renameFolder(folder.id, trimmed);
    }

    editingFolderId.value = null;
};

const cancelRenameFolder = () => {
 editingFolderId.value = null; 
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

const movingRequest = ref<RequestItem | null>(null);
const isMoveModalOpen = ref(false);

const startMoveRequest = (req: any, e?: Event) => {
    e?.stopPropagation();
    movingRequest.value = req;
    isMoveModalOpen.value = true;
};

const handleDragStart = (e: DragEvent, req: any) => {
    store.draggedRequestId = req.id;

    if (e.dataTransfer) {
        e.dataTransfer.setData('text/plain', JSON.stringify({ type: 'jackman-request', id: req.id }));
        e.dataTransfer.effectAllowed = 'move';
    }
};

const handleDragEnd = () => {
    store.draggedRequestId = null;
};

const handleDragOver = (e: DragEvent) => {
    if (e.dataTransfer) {
        e.dataTransfer.dropEffect = 'move';
    }
};

const handleDropOnFolder = async (e: DragEvent, folderId: string) => {
    try {
        let requestId = store.draggedRequestId;

        if (!requestId) {
            const data = e.dataTransfer?.getData('text/plain');

            if (data) {
                const parsed = JSON.parse(data);

                if (parsed.type === 'jackman-request') {
requestId = parsed.id;
}
            }
        }

        if (!requestId) {
return;
}
        
        if (props.collection) {
            const req = getRequestById(requestId);

            if (!req || req.folder_id !== folderId) {
                await store.moveRequest(requestId, props.collection.id, folderId);
            }
        }
    } catch (err) {} finally {
        store.draggedRequestId = null;
    }
};

const handleDropOnCollection = async (e: DragEvent) => {
    try {
        let requestId = store.draggedRequestId;
        let folderId = store.draggedFolderId; // add this to workspace.ts later
        const dataStr = e.dataTransfer?.getData('text/plain');

        if (dataStr) {
            try {
                const parsed = JSON.parse(dataStr);

                if (parsed.type === 'jackman-request') {
requestId = parsed.id;
} else if (parsed.type === 'jackman-folder') {
folderId = parsed.id;
}
            } catch (e) {}
        }
        
        if (requestId && props.collection) {
            const req = getRequestById(requestId);

            if (!req || req.folder_id !== null) {
                await store.moveRequest(requestId, props.collection.id, null);
            }
        } else if (folderId && props.collection) {
            const folder = props.collection.folders?.find((f: any) => f.id === folderId);

            if (folder && folder.parent_id === null) {
                return;
            }

            await store.moveFolder(folderId, props.collection.id, null);
        }
    } catch (err) {} finally {
        store.draggedRequestId = null;
        store.draggedFolderId = null;
    }
};

const getRequestById = (id: string) => {
    if (!props.collection) {
return null;
}

    let found = props.collection.requests.find((r: any) => r.id === id);

    if (found) {
return found;
}

    for (const f of props.collection.folders || []) {
        found = f.requests?.find((r: any) => r.id === id);

        if (found) {
return found;
}
    }

    return null;
};
</script>

<template>
    <div class="space-y-2" @dragenter.prevent @dragover.prevent @drop.prevent.stop="handleDropOnCollection">
        <div v-if="!props.collection">
            <div class="px-2 py-1 text-[11px] text-muted-foreground">
                No collection data.
            </div>
        </div>

        <div v-else class="space-y-1" @dragenter.prevent.stop @dragover.prevent.stop="handleDragOver" @drop.prevent.stop="handleDropOnCollection">
            <div v-if="isSelectionMode" class="px-2 py-1 flex items-center justify-between border-b border-sidebar-border/40 pb-2 mb-2 group">
                <div class="flex items-center gap-1 shrink-0">
                    <span class="text-[9px] font-semibold bg-primary/10 text-primary border border-primary/20 px-1 py-0.5 rounded font-mono mr-1">
                        {{ store.selectedRequestIds.length }} selected
                    </span>
                    <button 
                        class="p-1 rounded hover:bg-destructive/10 text-destructive disabled:opacity-50 cursor-pointer" 
                        title="Delete Selected Requests" 
                        :disabled="store.selectedRequestIds.length === 0"
                        @click.stop="handleDeleteSelectedRequests"
                    >
                        <Trash2 class="w-3.5 h-3.5" />
                    </button>
                    <button class="p-1 rounded hover:bg-muted text-muted-foreground hover:text-foreground font-semibold text-xs px-1.5 py-0.5 cursor-pointer" title="Exit Select Mode" @click.stop="exitSelectionMode">
                        Cancel
                    </button>
                </div>
            </div>

            <div v-if="store.activeNewFolder === props.collection.id" class="px-2 py-1">
                <Input 
                    v-model="newFolderName" 
                    placeholder="Folder name..." 
                    class="h-7 text-xs" 
                    @keyup.enter="($event.target as HTMLInputElement).blur()"
                    @blur="handleCreateFolder(props.collection.id)"
                    v-focus
                />
            </div>

            <div v-if="store.activeNewRequest === props.collection.id" class="px-2 py-1">
                <Input 
                    v-model="newRequestName" 
                    placeholder="Request name..." 
                    class="h-7 text-xs" 
                    @keyup.enter="($event.target as HTMLInputElement).blur()"
                    @blur="handleCreateRequest(props.collection.id)"
                    v-focus
                />
            </div>

            <div v-if="!props.collection.has_loaded_details" class="px-2 py-1 flex items-center text-[11px] text-muted-foreground">
                <Loader2 class="w-3.5 h-3.5 animate-spin mr-2 text-primary/50" />
                <span>Loading collection...</span>
            </div>
            
            <div v-else-if="(!props.collection.folders || props.collection.folders.length === 0) && (!props.collection.requests || props.collection.requests.length === 0) && store.activeNewRequest !== props.collection.id && store.activeNewFolder !== props.collection.id" class="flex flex-col items-center justify-center py-6 px-4">
                <p class="text-center text-xs text-muted-foreground mb-4">This collection is empty.</p>
                <Button 
                    variant="outline" 
                    size="sm"
                    class="h-8 text-[11px]" 
                    @click="store.activeNewRequest = props.collection.id"
                >
                    <FilePlus class="w-3.5 h-3.5 mr-2" />
                    Create Request
                </Button>
            </div>

            <div v-else class="space-y-0.5" @dragenter.prevent @dragover.prevent @drop.prevent.stop="handleDropOnCollection">
                <CollectionFolderNode 
                    v-for="folder in rootFolders" 
                    :key="folder.id" 
                    :folder="folder" 
                    :collection="props.collection"
                    @confirmDelete="confirmDelete"
                    @startMoveRequest="startMoveRequest"
                />

                <!-- Direct Requests of Collection -->
                <div 
                    v-for="req in props.collection.requests?.filter(r => !r.folder_id)" 
                    :key="req.id"
                    class="group/req flex items-center justify-between rounded-md px-2 py-0.5 text-xs hover:bg-muted/50 cursor-pointer select-none"
                    :class="{'bg-muted text-foreground': store.selectedRequest?.id === req.id}"
                    draggable="true"
                    style="-webkit-user-drag: element;"
                    @dragstart="handleDragStart($event, req)"
                    @dragend="handleDragEnd"
                    @dragenter.prevent.stop
                    @dragover.prevent.stop="handleDragOver"
                    @drop.prevent.stop="handleDropOnCollection"
                    @pointerdown.middle.stop.prevent
                    @auxclick.middle.prevent="!isSelectionMode && handleSelectRequest(req, true)"
                    @click="(e) => isSelectionMode ? toggleSelectRequest(req.id) : handleSelectRequest(req, e.metaKey || e.ctrlKey)"
                >
                    <div class="flex items-center gap-2 flex-1 min-w-0">
                        <div v-if="isSelectionMode" @click.stop class="flex items-center shrink-0">
                            <Checkbox 
                                :model-value="selectedRequestIds.includes(req.id)"
                                @update:model-value="toggleSelectRequest(req.id)"
                                class="h-3.5 w-3.5 rounded border-muted-foreground/30 shrink-0"
                            />
                        </div>
                        <span v-else :class="['text-[9px] font-bold px-1.5 py-0.5 rounded shrink-0', getMethodColor(req.method)]">
                            {{ req.method }}
                        </span>
                        <!-- Inline rename input -->
                        <input
                            v-if="editingRequestId === req.id"
                            v-model="editingRequestName"
                            class="flex-1 min-w-0 h-6 text-xs bg-background border border-primary rounded px-1 outline-none focus:ring-1 focus:ring-primary"
                            @click.stop
                            @keyup.enter="commitRenameRequest(req)"
                            @keyup.escape="cancelRenameRequest"
                            @blur="commitRenameRequest(req)"
                            v-focus
                        />
                        <span v-else class="truncate" @dblclick.stop="startRenameRequest(req)">{{ req.name }}</span>
                    </div>
                    <div v-if="!isSelectionMode" class="opacity-0 group-hover/req:opacity-100 flex items-center gap-1 transition-opacity">
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button class="p-1 rounded hover:bg-muted text-muted-foreground hover:text-foreground" title="More Options" @click.stop>
                                    <MoreVertical class="w-3.5 h-3.5" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-48" @close-auto-focus="(e) => e.preventDefault()">
                                <DropdownMenuItem @click.stop="startRenameRequest(req)">
                                    <Pencil class="mr-2 h-4 w-4" />
                                    <span>Rename</span>
                                </DropdownMenuItem>
                                <DropdownMenuItem @click.stop="store.cloneRequest(req.id)">
                                    <Copy class="mr-2 h-4 w-4" />
                                    <span>Duplicate</span>
                                </DropdownMenuItem>
                                <DropdownMenuItem @click.stop="startMoveRequest(req)">
                                    <ArrowRight class="mr-2 h-4 w-4" />
                                    <span>Move...</span>
                                </DropdownMenuItem>
                                <DropdownMenuItem @click.stop="handleDeleteRequest(req.id)" class="text-red-600 focus:text-red-600">
                                    <Trash2 class="mr-2 h-4 w-4" />
                                    <span>Delete</span>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shadcn Confirm Dialog -->
        <Dialog v-model:open="confirmDialog.isOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>{{ confirmDialog.title }}</DialogTitle>
                    <DialogDescription>
                        {{ confirmDialog.description }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex gap-2 justify-end mt-4">
                    <Button variant="outline" @click="confirmDialog.isOpen = false">Cancel</Button>
                    <Button variant="destructive" @click="confirmDialog.onConfirm">
                        {{ confirmDialog.confirmText }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Selected Requests Confirmation Dialog -->
        <Dialog v-model:open="showDeleteRequestsConfirm">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Delete Selected Requests</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete the {{ selectedRequestIds.length }} selected requests? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex gap-2 justify-end mt-4">
                    <Button variant="outline" @click="showDeleteRequestsConfirm = false">Cancel</Button>
                    <Button variant="destructive" @click="confirmDeleteSelectedRequests">
                        Delete Selected
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Move Request Modal -->
        <MoveRequestModal 
            v-model:open="isMoveModalOpen" 
            :request="movingRequest" 
        />
    </div>
</template>
