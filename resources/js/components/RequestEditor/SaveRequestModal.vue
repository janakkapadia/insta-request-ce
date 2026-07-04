<script setup lang="ts">
import { SaveIcon } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { useWorkspaceStore } from '@/stores/workspace';
import { toast } from 'vue-sonner';

const store = useWorkspaceStore();

const requestName = ref<string>('');
const targetCollectionId = ref<string>('');
const targetFolderId = ref<string>('root');
const isSubmitting = ref(false);

watch(() => store.showSaveRequestModal, (isOpen) => {
    if (isOpen) {
        // If there's an active collection in the tree or editor, default to it
        if (store.selectedCollection) {
            targetCollectionId.value = store.selectedCollection.id;
        } else if (store.collections.length > 0) {
            targetCollectionId.value = store.collections[0].id;
        }
        targetFolderId.value = 'root';
        requestName.value = store.selectedRequest?.name || 'New Request';
    }
});

watch(targetCollectionId, () => {
    targetFolderId.value = 'root';
});

const collectionOptions = computed(() =>
    store.collections.map((c) => ({ label: c.name, value: c.id }))
);

const folderOptions = computed(() => {
    if (!targetCollectionId.value) {
        return [];
    }
    const col = store.collections.find(c => c.id === targetCollectionId.value);
    return col?.folders?.map((f: any) => ({ label: f.name, value: f.id })) || [];
});

const close = () => {
    store.showSaveRequestModal = false;
    store.pendingSaveRequestData = null;
};

const handleSave = async () => {
    if (!targetCollectionId.value || !requestName.value.trim()) return;

    isSubmitting.value = true;
    
    if (store.pendingSaveRequestData) {
        store.pendingSaveRequestData.name = requestName.value.trim();
    }
    
    const folderId = targetFolderId.value === 'root' ? null : targetFolderId.value;
    
    await store.confirmSaveNewRequest(targetCollectionId.value, folderId);
    
    toast.success('Request saved successfully');
    
    isSubmitting.value = false;
};
</script>

<template>
    <Dialog :open="store.showSaveRequestModal" @update:open="(val: boolean) => !val && close()">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>Save Request</DialogTitle>
                <DialogDescription>
                    Select a destination collection and folder to save this new request.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 py-4">
                <div class="grid gap-2">
                    <label class="text-xs font-medium">Request Name</label>
                    <Input v-model="requestName" class="h-8 text-xs" placeholder="Request Name" />
                </div>

                <div class="grid gap-2">
                    <label class="text-xs font-medium">Target Collection</label>
                    <Select v-model="targetCollectionId">
                        <SelectTrigger class="h-8 text-xs">
                            <SelectValue placeholder="Select a collection" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="col in collectionOptions"
                                :key="col.value"
                                :value="col.value"
                            >
                                {{ col.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-2" v-if="targetCollectionId">
                    <label class="text-xs font-medium">Target Folder</label>
                    <Select v-model="targetFolderId">
                        <SelectTrigger class="h-8 text-xs">
                            <SelectValue placeholder="Root of Collection" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="root">Root of Collection</SelectItem>
                            <SelectItem
                                v-for="f in folderOptions"
                                :key="f.value"
                                :value="f.value"
                            >
                                {{ f.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="close" size="sm" class="h-8 text-xs" :disabled="isSubmitting">Cancel</Button>
                <Button @click="handleSave" size="sm" class="h-8 text-xs" :disabled="isSubmitting || !targetCollectionId">
                    <SaveIcon class="w-3.5 h-3.5 mr-1" />
                    Save Request
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
