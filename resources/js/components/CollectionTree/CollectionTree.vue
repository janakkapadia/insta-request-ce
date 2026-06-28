<script setup lang="ts">
import type { RequestItem } from '@/stores/workspace';
import { useWorkspaceStore } from '@/stores/workspace';
import { usePage, router } from '@inertiajs/vue3';
import { ChevronRight, ChevronDown, Folder, FileJson, Plus, FolderPlus, FilePlus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

const store = useWorkspaceStore();
const page = usePage();

const newCollectionName = ref('');
const showNewCollection = ref(false);

const activeNewFolder = ref<string | null>(null);
const newFolderName = ref('');

const activeNewRequest = ref<string | null>(null);
const newRequestName = ref('');

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

const toggle = async (item: any) => {
    item.expanded = !item.expanded;

    if (item.expanded && item.team_id && !item.has_loaded_details) {
        await store.fetchCollectionDetails(item.id);
    }
};

const handleSelectRequest = (req: RequestItem) => {
    store.selectRequest(req);
    router.get(`/collections/${req.collection_id}/requests/${req.id}`, {}, {
        preserveState: true,
        preserveScroll: true,
        only: ['activeCollectionId', 'activeRequestId']
    });
};

const handleCreateCollection = async () => {
    if (!newCollectionName.value.trim()) {
return;
}

    await store.createCollection(newCollectionName.value);
    newCollectionName.value = '';
    showNewCollection.value = false;
};

const handleCreateFolder = async (collectionId: string, parentId: string | null = null) => {
    if (!newFolderName.value.trim()) {
        activeNewFolder.value = null;

        return;
    }

    const name = newFolderName.value;
    newFolderName.value = '';
    activeNewFolder.value = null;
    await store.createFolder(collectionId, name, parentId);
};

const handleCreateRequest = async (collectionId: string, folderId: string | null = null) => {
    if (!newRequestName.value.trim()) {
        activeNewRequest.value = null;

        return;
    }

    const name = newRequestName.value;
    newRequestName.value = '';
    activeNewRequest.value = null;
    await store.createRequest(collectionId, name, folderId);
};

const handleDeleteCollection = (collectionId: string) => {
    confirmDelete(
        'Delete Collection',
        'Are you sure you want to delete this collection? This action cannot be undone.',
        () => store.deleteCollection(collectionId)
    );
};

const handleDeleteFolder = (folderId: string) => {
    confirmDelete(
        'Delete Folder',
        'Are you sure you want to delete this folder? This action cannot be undone.',
        () => store.deleteFolder(folderId)
    );
};

const handleDeleteRequest = (requestId: string) => {
    confirmDelete(
        'Delete Request',
        'Are you sure you want to delete this request? This action cannot be undone.',
        () => store.deleteRequest(requestId)
    );
};

import { getMethodBadgeColors as getMethodColor } from '@/lib/method-colors';
import collections from '@/routes/collections';
import { nextTick } from 'vue';

const vFocus = {
    mounted: (el: HTMLElement) => {
        nextTick(() => {
            const input = el.tagName === 'INPUT' ? el : el.querySelector('input');
            if (input) input.focus();
        });
    }
};
</script>

<template>
    <div class="space-y-4">
        <!-- New Collection Action -->
        <div class="px-2">
            <Button 
                v-if="!showNewCollection"
                variant="outline" 
                size="sm" 
                class="w-full justify-start gap-2 text-xs" 
                @click.prevent.stop="showNewCollection = true"
            >
                <Plus class="w-3.5 h-3.5" />
                New Collection
            </Button>
            <div v-else class="space-y-2 border p-2 rounded bg-muted/20">
                <Input 
                    v-model="newCollectionName" 
                    placeholder="Collection Name" 
                    class="h-8 text-xs" 
                    @keyup.enter="handleCreateCollection" 
                    v-focus
                />
                <div class="flex gap-2 justify-end">
                    <Button size="sm" variant="ghost" class="text-xs h-7 px-2" @click="showNewCollection = false">Cancel</Button>
                    <Button size="sm" class="text-xs h-7 px-2" @click="handleCreateCollection">Create</Button>
                </div>
            </div>
        </div>

        <div class="space-y-1">
            <template v-for="collection in store.collections" :key="collection.id">
                <!-- Collection Header -->
                <div class="group flex items-center justify-between rounded-md px-2 py-1.5 text-sm hover:bg-muted/50 cursor-pointer">
                    <div class="flex items-center gap-2 flex-1 min-w-0" @click="toggle(collection)">
                        <div class="w-4 h-4 flex items-center justify-center shrink-0">
                            <ChevronDown v-if="collection.folders?.length || collection.requests?.length" class="w-3.5 h-3.5 text-muted-foreground transition-transform" :class="{'rotate-[-90deg]': !collection.expanded}" />
                        </div>
                        <Folder class="w-4 h-4 text-amber-500/80 shrink-0" />
                        <span class="font-medium truncate">{{ collection.name }}</span>
                    </div>

                    <div class="opacity-0 group-hover:opacity-100 flex items-center gap-1 transition-opacity">
                        <button class="p-1 rounded hover:bg-muted text-muted-foreground hover:text-foreground" title="Add Folder" @click.prevent.stop="activeNewFolder = collection.id">
                            <FolderPlus class="w-3.5 h-3.5" />
                        </button>
                        <button class="p-1 rounded hover:bg-muted text-muted-foreground hover:text-foreground" title="Add Request" @click.prevent.stop="activeNewRequest = collection.id">
                            <FilePlus class="w-3.5 h-3.5" />
                        </button>
                        <button v-if="!collection.folders?.length && !collection.requests?.length" class="p-1 rounded hover:bg-muted text-red-500 hover:text-red-600" title="Delete Collection" @click.stop="handleDeleteCollection(collection.id)">
                            <Trash2 class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>

                <!-- Create Folder Input Inline -->
                <div v-if="activeNewFolder === collection.id" class="pl-6 pr-2 py-1">
                    <Input 
                        v-model="newFolderName" 
                        placeholder="Folder name..." 
                        class="h-7 text-xs" 
                        @keyup.enter="($event.target as HTMLInputElement).blur()"
                        @blur="handleCreateFolder(collection.id)"
                        v-focus
                    />
                </div>

                <!-- Create Request Input Inline -->
                <div v-if="activeNewRequest === collection.id" class="pl-6 pr-2 py-1">
                    <Input 
                        v-model="newRequestName" 
                        placeholder="Request name..." 
                        class="h-7 text-xs" 
                        @keyup.enter="($event.target as HTMLInputElement).blur()"
                        @blur="handleCreateRequest(collection.id)"
                        v-focus
                    />
                </div>

                <!-- Folders and Requests inside Collection -->
                <div v-if="collection.expanded" class="pl-4 border-l ml-4 border-sidebar-border/50 space-y-0.5">
                    <!-- Folders -->
                    <template v-for="folder in collection.folders" :key="folder.id">
                        <div class="group flex items-center justify-between rounded-md px-2 py-1.5 text-sm hover:bg-muted/50 cursor-pointer"
                            :class="{'bg-muted text-foreground': store.selectedRequest?.folder_id === folder.id}">
                            <div class="flex items-center gap-2 flex-1 min-w-0" @click="toggle(folder)">
                                <div class="w-4 h-4 flex items-center justify-center shrink-0">
                                    <ChevronDown v-if="folder.requests?.length" class="w-3.5 h-3.5 text-muted-foreground transition-transform" :class="{'rotate-[-90deg]': !folder.expanded}" />
                                </div>
                                <Folder class="w-4 h-4 text-blue-500/80 shrink-0" />
                                <span class="truncate" :class="{'font-medium': store.selectedRequest?.folder_id === folder.id}">{{ folder.name }}</span>
                            </div>

                            <div class="opacity-0 group-hover:opacity-100 flex items-center gap-1 transition-opacity">
                                <button class="p-1 rounded hover:bg-muted text-muted-foreground hover:text-foreground" title="Add Request" @click.prevent.stop="activeNewRequest = folder.id">
                                    <FilePlus class="w-3.5 h-3.5" />
                                </button>
                                <button v-if="!folder.requests?.length" class="p-1 rounded hover:bg-muted text-red-500 hover:text-red-600" title="Delete Folder" @click.stop="handleDeleteFolder(folder.id)">
                                    <Trash2 class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>

                        <!-- Create Request Input Inline under Folder -->
                        <div v-if="activeNewRequest === folder.id" class="pl-6 pr-2 py-1">
                            <Input 
                                v-model="newRequestName" 
                                placeholder="Request name..." 
                                class="h-7 text-xs" 
                                @keyup.enter="($event.target as HTMLInputElement).blur()"
                                @blur="handleCreateRequest(collection.id, folder.id)"
                                v-focus
                            />
                        </div>

                        <!-- Requests inside Folder -->
                        <div v-if="folder.expanded" class="pl-4 border-l ml-4 border-sidebar-border/50">
                            <div 
                                v-for="req in folder.requests" 
                                :key="req.id"
                                class="group/req flex items-center justify-between rounded-md px-2 py-1.5 text-sm hover:bg-muted/50 cursor-pointer"
                                :class="{'bg-muted text-foreground': store.selectedRequest?.id === req.id}"
                                @click="handleSelectRequest(req)"
                            >
                                <div class="flex items-center gap-2 flex-1 min-w-0">
                                    <span :class="['text-[9px] font-bold px-1.5 py-0.5 rounded shrink-0', getMethodColor(req.method)]">
                                        {{ req.method }}
                                    </span>
                                    <span class="truncate">{{ req.name }}</span>
                                </div>
                                <div class="opacity-0 group-hover/req:opacity-100 flex items-center gap-1 transition-opacity">
                                    <button class="p-1 rounded hover:bg-muted text-red-500 hover:text-red-600" title="Delete Request" @click.stop="handleDeleteRequest(req.id)">
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Direct Requests of Collection -->
                    <div 
                        v-for="req in collection.requests.filter(r => !r.folder_id)" 
                        :key="req.id"
                        class="group/req flex items-center justify-between rounded-md px-2 py-1.5 text-sm hover:bg-muted/50 cursor-pointer"
                        :class="{'bg-muted text-foreground': store.selectedRequest?.id === req.id}"
                        @click="handleSelectRequest(req)"
                    >
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <span :class="['text-[9px] font-bold px-1.5 py-0.5 rounded shrink-0', getMethodColor(req.method)]">
                                {{ req.method }}
                            </span>
                            <span class="truncate">{{ req.name }}</span>
                        </div>
                        <div class="opacity-0 group-hover/req:opacity-100 flex items-center gap-1 transition-opacity">
                            <button class="p-1 rounded hover:bg-muted text-red-500 hover:text-red-600" title="Delete Request" @click.stop="handleDeleteRequest(req.id)">
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </template>
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
    </div>
</template>
