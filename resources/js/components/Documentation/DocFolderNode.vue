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

    return props.folders.filter((f) => f.parent_id === props.folder.id);
});

const folderRequests = computed(() => {
    if (!props.requests) {
        return [];
    }

    return props.requests.filter((r) => r.folder_id === props.folder.id);
});

const getFolderRequestsCount = (folderId: string): number => {
    if (!props.requests || !props.folders) {
        return 0;
    }

    let count = props.requests.filter((r) => r.folder_id === folderId).length;
    const children = props.folders.filter((f) => f.parent_id === folderId);

    for (const child of children) {
        count += getFolderRequestsCount(child.id);
    }

    return count;
};

const folderRequestsCount = computed(() =>
    getFolderRequestsCount(props.folder.id),
);
</script>

<template>
    <div
        v-if="
            folderRequestsCount > 0 ||
            childFolders.length > 0 ||
            currentDepth === 0
        "
        class="space-y-1 select-none"
    >
        <div
            @click="isExpanded = !isExpanded"
            class="group flex cursor-pointer items-center justify-between rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-muted/70 hover:text-foreground"
        >
            <div class="flex min-w-0 flex-1 items-center gap-1.5">
                <div class="flex h-4 w-4 shrink-0 items-center justify-center">
                    <ChevronDown
                        class="h-3.5 w-3.5 shrink-0 text-muted-foreground transition-transform duration-150"
                        :class="{ 'rotate-[-90deg]': !isExpanded }"
                    />
                </div>
                <Folder class="h-3.5 w-3.5 shrink-0 text-blue-500/80" />
                <span class="truncate text-xs font-semibold">{{
                    folder.name
                }}</span>
            </div>
            <span
                class="shrink-0 rounded bg-muted/60 px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground/70"
                >{{ folderRequestsCount }}</span
            >
        </div>

        <div
            v-if="isExpanded"
            class="mt-0.5 ml-2 space-y-1 border-l border-border/60 pl-2"
        >
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
                class="group/btn flex w-full items-center gap-2 rounded-md p-1.5 text-left transition-colors"
                :class="
                    selectedRequestId === req.id
                        ? 'border border-border/50 bg-sidebar-accent font-semibold text-sidebar-accent-foreground shadow-sm'
                        : 'text-foreground hover:bg-muted'
                "
            >
                <span
                    class="inline-flex w-10 shrink-0 items-center justify-center rounded border px-1 py-0.5 text-center text-[8px] leading-none font-bold uppercase select-none"
                    :class="getMethodColor(req.method)"
                >
                    {{ req.method }}
                </span>
                <span class="flex-1 truncate text-xs">{{ req.name }}</span>
                <ChevronRight
                    class="h-3 w-3 shrink-0 opacity-50 transition-opacity group-hover/btn:opacity-100"
                />
            </button>

            <!-- Empty indicator if both childFolders and folderRequests are empty -->
            <div
                v-if="childFolders.length === 0 && folderRequests.length === 0"
                class="px-2 py-1 text-[10px] text-muted-foreground italic"
            >
                Empty folder
            </div>
        </div>
    </div>
</template>
