<script setup lang="ts">
import { ArrowRightIcon } from 'lucide-vue-next';
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
import { useWorkspaceStore } from '@/stores/workspace';
import type { RequestItem } from '@/stores/workspace';

const props = defineProps<{
    open: boolean;
    request: RequestItem | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
}>();

const store = useWorkspaceStore();

const targetCollectionId = ref<string>('');
const targetFolderId = ref<string>('root');
const isSubmitting = ref(false);

watch(() => props.request, (req) => {
    if (req) {
        targetCollectionId.value = req.collection_id;
        targetFolderId.value = req.folder_id || 'root';
    }
}, { immediate: true });

watch(targetCollectionId, () => {
    // If the selected collection changes, default the folder to root, UNLESS it's the original collection.
    if (props.request && props.request.collection_id === targetCollectionId.value) {
        targetFolderId.value = props.request.folder_id || 'root';
    } else {
        targetFolderId.value = 'root';
    }
});

const collectionOptions = computed(() =>
    store.collections.map((c) => ({ label: c.name, value: c.id }))
);

const folderOptions = computed(() => {
    if (!targetCollectionId.value) {
return [];
}

    const col = store.collections.find(c => c.id === targetCollectionId.value);

    return col?.folders?.map(f => ({ label: f.name, value: f.id })) || [];
});

const close = () => {
    emit('update:open', false);
};

const handleMove = async () => {
    if (!props.request || !targetCollectionId.value) {
return;
}

    isSubmitting.value = true;
    
    const folderId = targetFolderId.value === 'root' ? null : targetFolderId.value;
    
    await store.moveRequest(props.request.id, targetCollectionId.value, folderId);
    
    isSubmitting.value = false;
    close();
};
</script>

<template>
    <Dialog :open="open" @update:open="(val: boolean) => emit('update:open', val)">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>Move Request</DialogTitle>
                <DialogDescription v-if="request">
                    Select a new destination for <strong class="font-medium text-foreground">{{ request.name }}</strong>.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 py-4">
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
                <Button @click="handleMove" size="sm" class="h-8 text-xs" :disabled="isSubmitting || !targetCollectionId">
                    <ArrowRightIcon class="w-3.5 h-3.5 mr-1" />
                    Move Request
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
