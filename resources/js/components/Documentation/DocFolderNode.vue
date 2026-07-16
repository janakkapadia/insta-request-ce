<script setup lang="ts">
import { ChevronDown, ChevronRight, Folder } from 'lucide-vue-next';
import { ref, computed } from 'vue';

const props = defineProps<{
    folder: any;
    folders?: Array<any>;
    requests?: Array<any>;
    selectedRequestId?: string;
    getMethodColor: (method: string) => string;
    depth?: number;
}>();

const emit = defineEmits<{
    (e: 'selectRequest', id: string): void;
}>();

const currentDepth = props.depth || 0;
const isExpanded = ref<boolean>(true);

const childFolders = computed(() => {
    if (!props.folders) {
return [];
}

    return props.folders.filter(f => f.parent_id === props.folder.id);
});

const folderRequests = computed(() => {
    if (!props.requests) {
return [];
}

    return props.requests.filter(r => r.folder_id === props.folder.id);
});

const getFolderRequestsCount = (folderId: string): number => {
    if (!props.requests || !props.folders) {
return 0;
}

    let count = props.requests.filter(r => r.folder_id === folderId).length;
    const children = props.folders.filter(f => f.parent_id === folderId);

    for (const child of children) {
        count += getFolderRequestsCount(child.id);
    }

    return count;
};

const folderRequestsCount = computed(() => getFolderRequestsCount(props.folder.id));
</script>

<template>
    <div v-if="folderRequestsCount > 0 || childFolders.length > 0 || currentDepth === 0" class="select-none space-y-1">
        <div
            @click="isExpanded = !isExpanded"
            class="flex items-center justify-between p-1.5 rounded-md hover:bg-muted/70 cursor-pointer text-muted-foreground hover:text-foreground transition-colors group"
        >
            <div class="flex items-center gap-1.5 min-w-0 flex-1">
                <div class="w-4 h-4 flex items-center justify-center shrink-0">
                    <ChevronDown
                        class="h-3.5 w-3.5 shrink-0 transition-transform duration-150 text-muted-foreground"
                        :class="{ 'rotate-[-90deg]': !isExpanded }"
                    />
                </div>
                <Folder class="h-3.5 w-3.5 text-blue-500/80 shrink-0" />
                <span class="text-xs font-semibold truncate">{{ folder.name }}</span>
            </div>
            <span class="text-[10px] text-muted-foreground/70 bg-muted/60 px-1.5 py-0.5 rounded font-mono shrink-0">{{ folderRequestsCount }}</span>
        </div>

        <div v-if="isExpanded" class="pl-2 border-l ml-2 border-border/60 space-y-1 mt-0.5">
            <!-- Nested child folders -->
            <DocFolderNode
                v-for="child in childFolders"
                :key="child.id"
                :folder="child"
                :folders="folders"
                :requests="requests"
                :selected-request-id="selectedRequestId"
                :get-method-color="getMethodColor"
                :depth="currentDepth + 1"
                @select-request="(id) => emit('selectRequest', id)"
            />

            <!-- Direct requests in folder -->
            <button
                v-for="req in folderRequests"
                :key="req.id"
                @click="emit('selectRequest', req.id)"
                class="w-full flex items-center gap-2 p-1.5 rounded-md transition-colors text-left group/btn"
                :class="selectedRequestId === req.id ? 'bg-sidebar-accent font-semibold text-sidebar-accent-foreground border border-border/50 shadow-sm' : 'hover:bg-muted text-foreground'"
            >
                <span
                    class="inline-flex shrink-0 items-center justify-center rounded border px-1 text-[8px] leading-none font-bold uppercase py-0.5 w-10 text-center select-none"
                    :class="getMethodColor(req.method)"
                >
                    {{ req.method }}
                </span>
                <span class="text-xs truncate flex-1">{{ req.name }}</span>
                <ChevronRight class="h-3 w-3 opacity-50 shrink-0 group-hover/btn:opacity-100 transition-opacity" />
            </button>

            <!-- Empty indicator if both childFolders and folderRequests are empty -->
            <div v-if="childFolders.length === 0 && folderRequests.length === 0" class="py-1 px-2 text-[10px] text-muted-foreground italic">
                Empty folder
            </div>
        </div>
    </div>
</template>
