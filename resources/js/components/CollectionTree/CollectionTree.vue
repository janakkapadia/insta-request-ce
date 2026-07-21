<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ChevronDown,
    Folder,
    Plus,
    FolderPlus,
    FilePlus,
    Trash2,
} from 'lucide-vue-next';
import { ref, nextTick } from 'vue';
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
import { getMethodBadgeColors as getMethodColor } from '@/lib/method-colors';
import { useWorkspaceStore } from '@/stores/workspace';
import type { RequestItem } from '@/stores/workspace';

const store = useWorkspaceStore();

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

const confirmDelete = (
    title: string,
    description: string,
    onConfirm: () => void,
) => {
    confirmDialog.value = {
        isOpen: true,
        title,
        description,
        confirmText: 'Delete',
        onConfirm: () => {
            onConfirm();
            confirmDialog.value.isOpen = false;
        },
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

const handleCreateCollection = async () => {
    if (!newCollectionName.value.trim()) {
        return;
    }

    await store.createCollection(newCollectionName.value);
    newCollectionName.value = '';
    showNewCollection.value = false;
};

const handleCreateFolder = async (
    collectionId: string,
    parentId: string | null = null,
) => {
    if (!newFolderName.value.trim()) {
        activeNewFolder.value = null;

        return;
    }

    const name = newFolderName.value;
    newFolderName.value = '';
    activeNewFolder.value = null;
    await store.createFolder(collectionId, name, parentId);
};

const handleCreateRequest = async (
    collectionId: string,
    folderId: string | null = null,
) => {
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
        () => store.deleteCollection(collectionId),
    );
};

const handleDeleteFolder = (folderId: string) => {
    confirmDelete(
        'Delete Folder',
        'Are you sure you want to delete this folder? This action cannot be undone.',
        () => store.deleteFolder(folderId),
    );
};

const handleDeleteRequest = (requestId: string) => {
    confirmDelete(
        'Delete Request',
        'Are you sure you want to delete this request? This action cannot be undone.',
        () => store.deleteRequest(requestId),
    );
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
                <Plus class="h-3.5 w-3.5" />
                New Collection
            </Button>
            <div v-else class="space-y-2 rounded border bg-muted/20 p-2">
                <Input
                    v-model="newCollectionName"
                    placeholder="Collection Name"
                    class="h-8 text-xs"
                    @keyup.enter="handleCreateCollection"
                    v-focus
                />
                <div class="flex justify-end gap-2">
                    <Button
                        size="sm"
                        variant="ghost"
                        class="h-7 px-2 text-xs"
                        @click="showNewCollection = false"
                        >Cancel</Button
                    >
                    <Button
                        size="sm"
                        class="h-7 px-2 text-xs"
                        @click="handleCreateCollection"
                        >Create</Button
                    >
                </div>
            </div>
        </div>

        <div class="space-y-1">
            <template
                v-for="collection in store.collections"
                :key="collection.id"
            >
                <!-- Collection Header -->
                <div
                    class="group flex cursor-pointer items-center justify-between rounded-md px-2 py-1.5 text-sm hover:bg-muted/50"
                >
                    <div
                        class="flex min-w-0 flex-1 items-center gap-2"
                        @click="toggle(collection)"
                    >
                        <div
                            class="flex h-4 w-4 shrink-0 items-center justify-center"
                        >
                            <ChevronDown
                                v-if="
                                    collection.folders?.length ||
                                    collection.requests?.length
                                "
                                class="h-3.5 w-3.5 text-muted-foreground transition-transform"
                                :class="{
                                    'rotate-[-90deg]': !collection.expanded,
                                }"
                            />
                        </div>
                        <Folder class="h-4 w-4 shrink-0 text-amber-500/80" />
                        <span class="truncate font-medium">{{
                            collection.name
                        }}</span>
                    </div>

                    <div
                        class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100"
                    >
                        <button
                            class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                            title="Add Folder"
                            @click.prevent.stop="
                                activeNewFolder = collection.id
                            "
                        >
                            <FolderPlus class="h-3.5 w-3.5" />
                        </button>
                        <button
                            class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                            title="Add Request"
                            @click.prevent.stop="
                                activeNewRequest = collection.id
                            "
                        >
                            <FilePlus class="h-3.5 w-3.5" />
                        </button>
                        <button
                            v-if="
                                !collection.folders?.length &&
                                !collection.requests?.length
                            "
                            class="rounded p-1 text-red-500 hover:bg-muted hover:text-red-600"
                            title="Delete Collection"
                            @click.stop="handleDeleteCollection(collection.id)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>

                <!-- Create Folder Input Inline -->
                <div
                    v-if="activeNewFolder === collection.id"
                    class="py-1 pr-2 pl-6"
                >
                    <Input
                        v-model="newFolderName"
                        placeholder="Folder name..."
                        class="h-7 text-xs"
                        @keyup.enter="handleCreateFolder(collection.id)"
                        @blur="handleCreateFolder(collection.id)"
                        v-focus
                    />
                </div>

                <!-- Create Request Input Inline -->
                <div
                    v-if="activeNewRequest === collection.id"
                    class="py-1 pr-2 pl-6"
                >
                    <Input
                        v-model="newRequestName"
                        placeholder="Request name..."
                        class="h-7 text-xs"
                        @keyup.enter="handleCreateRequest(collection.id)"
                        @blur="handleCreateRequest(collection.id)"
                        v-focus
                    />
                </div>

                <!-- Folders and Requests inside Collection -->
                <div
                    v-if="collection.expanded"
                    class="ml-4 space-y-0.5 border-l border-sidebar-border/50 pl-4"
                >
                    <!-- Folders -->
                    <template
                        v-for="folder in collection.folders"
                        :key="folder.id"
                    >
                        <div
                            class="group flex cursor-pointer items-center justify-between rounded-md px-2 py-1.5 text-sm hover:bg-muted/50"
                            :class="{
                                'bg-muted text-foreground':
                                    store.selectedRequest?.folder_id ===
                                    folder.id,
                            }"
                        >
                            <div
                                class="flex min-w-0 flex-1 items-center gap-2"
                                @click="toggle(folder)"
                            >
                                <div
                                    class="flex h-4 w-4 shrink-0 items-center justify-center"
                                >
                                    <ChevronDown
                                        v-if="folder.requests?.length"
                                        class="h-3.5 w-3.5 text-muted-foreground transition-transform"
                                        :class="{
                                            'rotate-[-90deg]': !folder.expanded,
                                        }"
                                    />
                                </div>
                                <Folder
                                    class="h-4 w-4 shrink-0 text-blue-500/80"
                                />
                                <span
                                    class="truncate"
                                    :class="{
                                        'font-medium':
                                            store.selectedRequest?.folder_id ===
                                            folder.id,
                                    }"
                                    >{{ folder.name }}</span
                                >
                            </div>

                            <div
                                class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100"
                            >
                                <button
                                    class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                    title="Add Request"
                                    @click.prevent.stop="
                                        activeNewRequest = folder.id
                                    "
                                >
                                    <FilePlus class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    v-if="!folder.requests?.length"
                                    class="rounded p-1 text-red-500 hover:bg-muted hover:text-red-600"
                                    title="Delete Folder"
                                    @click.stop="handleDeleteFolder(folder.id)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>

                        <!-- Create Request Input Inline under Folder -->
                        <div
                            v-if="activeNewRequest === folder.id"
                            class="py-1 pr-2 pl-6"
                        >
                            <Input
                                v-model="newRequestName"
                                placeholder="Request name..."
                                class="h-7 text-xs"
                                @keyup.enter="
                                    ($event.target as HTMLInputElement).blur()
                                "
                                @blur="
                                    handleCreateRequest(
                                        collection.id,
                                        folder.id,
                                    )
                                "
                                v-focus
                            />
                        </div>

                        <!-- Requests inside Folder -->
                        <div
                            v-if="folder.expanded"
                            class="ml-4 border-l border-sidebar-border/50 pl-4"
                        >
                            <div
                                v-for="req in folder.requests"
                                :key="req.id"
                                class="group/req flex cursor-pointer items-center justify-between rounded-md px-2 py-1.5 text-sm hover:bg-muted/50"
                                :class="{
                                    'bg-muted text-foreground':
                                        store.selectedRequest?.id === req.id,
                                }"
                                @click="handleSelectRequest(req)"
                            >
                                <div
                                    class="flex min-w-0 flex-1 items-center gap-2"
                                >
                                    <span
                                        :class="[
                                            'shrink-0 rounded px-1.5 py-0.5 text-[9px] font-bold',
                                            getMethodColor(req.method),
                                        ]"
                                    >
                                        {{ req.method }}
                                    </span>
                                    <span class="truncate">{{ req.name }}</span>
                                </div>
                                <div
                                    class="flex items-center gap-1 opacity-0 transition-opacity group-hover/req:opacity-100"
                                >
                                    <button
                                        class="rounded p-1 text-red-500 hover:bg-muted hover:text-red-600"
                                        title="Delete Request"
                                        @click.stop="
                                            handleDeleteRequest(req.id)
                                        "
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Direct Requests of Collection -->
                    <div
                        v-for="req in collection.requests.filter(
                            (r) => !r.folder_id,
                        )"
                        :key="req.id"
                        class="group/req flex cursor-pointer items-center justify-between rounded-md px-2 py-1.5 text-sm hover:bg-muted/50"
                        :class="{
                            'bg-muted text-foreground':
                                store.selectedRequest?.id === req.id,
                        }"
                        @click="handleSelectRequest(req)"
                    >
                        <div class="flex min-w-0 flex-1 items-center gap-2">
                            <span
                                :class="[
                                    'shrink-0 rounded px-1.5 py-0.5 text-[9px] font-bold',
                                    getMethodColor(req.method),
                                ]"
                            >
                                {{ req.method }}
                            </span>
                            <span class="truncate">{{ req.name }}</span>
                        </div>
                        <div
                            class="flex items-center gap-1 opacity-0 transition-opacity group-hover/req:opacity-100"
                        >
                            <button
                                class="rounded p-1 text-red-500 hover:bg-muted hover:text-red-600"
                                title="Delete Request"
                                @click.stop="handleDeleteRequest(req.id)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
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
                <DialogFooter class="mt-4 flex justify-end gap-2">
                    <Button
                        variant="outline"
                        @click="confirmDialog.isOpen = false"
                        >Cancel</Button
                    >
                    <Button
                        variant="destructive"
                        @click="confirmDialog.onConfirm"
                    >
                        {{ confirmDialog.confirmText }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
