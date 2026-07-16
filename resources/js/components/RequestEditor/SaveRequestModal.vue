<script setup lang="ts">
import { SaveIcon, Folder, ChevronDown } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { useWorkspaceStore } from '@/stores/workspace';

const store = useWorkspaceStore();

const requestName = ref<string>('');
const location = ref<string>('');
const targetCollectionId = ref<string>('');
const targetFolderId = ref<string>('root');
const isSubmitting = ref(false);
const expandedNodes = ref<Set<string>>(new Set());

const getCollectionInitials = (name: string): string => {
    const words = name.trim().split(/\s+/);

    if (words.length >= 2) {
        return (words[0][0] + words[1][0]).toUpperCase();
    }

    return name.substring(0, 2).toUpperCase();
};

const COLLECTION_COLORS = [
    { bg: '#10b98122', text: '#059669', border: '#10b98144' },
    { bg: '#ec489922', text: '#db2777', border: '#ec489944' },
    { bg: '#3b82f622', text: '#2563eb', border: '#3b82f644' },
    { bg: '#8b5cf622', text: '#7c3aed', border: '#8b5cf644' },
    { bg: '#f9731622', text: '#ea580c', border: '#f9731644' },
    { bg: '#14b8a622', text: '#0d9488', border: '#14b8a644' },
];

const getCollectionColor = (name: string) => {
    let hash = 0;

    for (let i = 0; i < name.length; i++) {
        hash = (hash * 31 + name.charCodeAt(i)) & 0xffffffff;
    }

    return COLLECTION_COLORS[Math.abs(hash) % COLLECTION_COLORS.length];
};

watch(location, (val) => {
    if (!val) {
        return;
    }

    if (val.startsWith('c_')) {
        targetCollectionId.value = val.substring(2);
        targetFolderId.value = 'root';
    } else if (val.startsWith('f_')) {
        const folderId = val.substring(2);
        targetFolderId.value = folderId;
        const col = store.collections.find((c) =>
            c.folders?.some((f) => f.id === folderId),
        );

        if (col) {
            targetCollectionId.value = col.id;
        }
    }
});

watch(
    () => store.showSaveRequestModal,
    (isOpen) => {
        if (isOpen) {
            expandedNodes.value = new Set();

            if (store.selectedCollection) {
                targetCollectionId.value = store.selectedCollection.id;
                location.value = `c_${store.selectedCollection.id}`;
            } else if (store.collections.length > 0) {
                targetCollectionId.value = store.collections[0].id;
                location.value = `c_${store.collections[0].id}`;
            }

            targetFolderId.value = 'root';
            requestName.value = store.selectedRequest?.name || 'New Request';
        }
    },
);

const locationOptions = computed(() => {
    const options: any[] = [];

    const sortedCollections = [...store.collections].sort((a, b) =>
        a.name.localeCompare(b.name),
    );

    sortedCollections.forEach((col) => {
        const hasChildren = col.folders && col.folders.length > 0;
        options.push({
            id: `c_${col.id}`,
            type: 'collection',
            name: col.name,
            depth: 0,
            hasChildren: hasChildren,
        });

        if (hasChildren && expandedNodes.value.has(`c_${col.id}`)) {
            const addFolders = (parentId: string | null, depth: number) => {
                const children = col.folders.filter(
                    (f: any) =>
                        (!f.parent_id && !parentId) || f.parent_id === parentId,
                );
                children.sort((a: any, b: any) => a.name.localeCompare(b.name));

                children.forEach((f: any) => {
                    const fHasChildren = col.folders.some(
                        (child: any) => child.parent_id === f.id,
                    );
                    options.push({
                        id: `f_${f.id}`,
                        type: 'folder',
                        name: f.name,
                        depth: depth,
                        hasChildren: fHasChildren,
                    });

                    if (fHasChildren && expandedNodes.value.has(`f_${f.id}`)) {
                        addFolders(f.id, depth + 1);
                    }
                });
            };
            addFolders(null, 1);
        }
    });

    return options;
});

const toggleExpand = (id: string) => {
    const newSet = new Set(expandedNodes.value);

    if (newSet.has(id)) {
        newSet.delete(id);
    } else {
        newSet.add(id);
    }

    expandedNodes.value = newSet;
};

const close = () => {
    store.showSaveRequestModal = false;
    store.pendingSaveRequestData = null;
};

const handleSave = async () => {
    if (!targetCollectionId.value || !requestName.value.trim()) {
        return;
    }

    isSubmitting.value = true;

    if (store.pendingSaveRequestData) {
        store.pendingSaveRequestData.name = requestName.value.trim();
    }

    const folderId =
        targetFolderId.value === 'root' ? null : targetFolderId.value;

    await store.confirmSaveNewRequest(targetCollectionId.value, folderId);

    toast.success('Request saved successfully');

    isSubmitting.value = false;
};
</script>

<template>
    <Dialog
        :open="store.showSaveRequestModal"
        @update:open="(val: boolean) => !val && close()"
    >
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>Save Request</DialogTitle>
                <DialogDescription>
                    Select a destination collection and folder to save this new
                    request.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 py-4">
                <div class="grid gap-2">
                    <label class="text-xs font-medium">Request Name</label>
                    <Input
                        v-model="requestName"
                        class="h-8 text-xs"
                        placeholder="Request Name"
                    />
                </div>

                <div class="grid gap-2">
                    <label class="text-xs font-medium">Location</label>
                    <div
                        class="h-64 overflow-y-auto rounded-md border bg-background p-1"
                    >
                        <div
                            v-for="opt in locationOptions"
                            :key="opt.id"
                            @click="location = opt.id"
                            :class="
                                cn(
                                    'flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm select-none hover:bg-accent hover:text-accent-foreground',
                                    location === opt.id
                                        ? 'bg-accent font-medium text-accent-foreground'
                                        : 'text-muted-foreground',
                                )
                            "
                        >
                            <div
                                :style="{ width: `${opt.depth * 1.25}rem` }"
                                class="shrink-0"
                            ></div>

                            <div
                                class="mr-0.5 -ml-1 flex h-4 w-4 shrink-0 items-center justify-center"
                            >
                                <button
                                    v-if="opt.hasChildren"
                                    @click.stop="toggleExpand(opt.id)"
                                    class="flex h-full w-full items-center justify-center rounded-sm hover:bg-black/10 dark:hover:bg-white/10"
                                >
                                    <ChevronDown
                                        class="h-3.5 w-3.5 text-muted-foreground transition-transform"
                                        :class="{
                                            'rotate-[-90deg]':
                                                !expandedNodes.has(opt.id),
                                        }"
                                    />
                                </button>
                            </div>

                            <span
                                v-if="opt.type === 'collection'"
                                class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded border text-[8px] leading-none font-bold select-none"
                                :style="{
                                    background: getCollectionColor(opt.name).bg,
                                    color: getCollectionColor(opt.name).text,
                                    borderColor: getCollectionColor(opt.name)
                                        .border,
                                }"
                            >
                                {{ getCollectionInitials(opt.name) }}
                            </span>
                            <Folder
                                v-else
                                class="h-4 w-4 shrink-0 opacity-70"
                            />
                            <span
                                class="truncate"
                                :class="
                                    location === opt.id ? 'text-foreground' : ''
                                "
                                >{{ opt.name }}</span
                            >
                        </div>

                        <div
                            v-if="locationOptions.length === 0"
                            class="p-4 text-center text-xs text-muted-foreground"
                        >
                            No collections found.
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button
                    variant="outline"
                    @click="close"
                    size="sm"
                    class="h-8 text-xs"
                    :disabled="isSubmitting"
                    >Cancel</Button
                >
                <Button
                    @click="handleSave"
                    size="sm"
                    class="h-8 text-xs"
                    :disabled="isSubmitting || !location"
                >
                    <SaveIcon class="mr-1 h-3.5 w-3.5" />
                    Save Request
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
