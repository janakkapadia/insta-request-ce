<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, watch } from 'vue';
import { watchEffect } from 'vue';
import RequestEditor from '@/components/RequestEditor/RequestEditor.vue';
import { layoutState } from '@/lib/layoutState';
import type { CollectionItem } from '@/stores/workspace';
import { useWorkspaceStore } from '@/stores/workspace';
import type { Team } from '@/types';

const props = defineProps<{
    currentTeam?: Team;
    collections: CollectionItem[];
    activeCollectionId?: string | null;
    activeRequestId?: string | null;
}>();

const store = useWorkspaceStore();
const selectCollectionFromId = (colId: string | null | undefined) => {
    if (!colId) {
        return;
    }

    if (store.selectedCollection?.id === colId) {
        return;
    }

    const col = store.collections.find((c) => c.id === colId);

    if (col) {
        store.selectCollection(col);
    }
};

const selectRequestFromId = (reqId: string | null | undefined) => {
    if (!reqId) {
        return;
    }

    if (store.selectedRequest?.id === reqId) {
        return;
    }

    for (const col of store.collections) {
        // Check direct requests
        const req = col.requests.find((r) => r.id === reqId);

        if (req) {
            store.selectCollection(col);
            store.selectRequest(req);

            return;
        }

        // Check requests in folders
        for (const folder of col.folders) {
            const freq = folder.requests?.find((r) => r.id === reqId);

            if (freq) {
                folder.expanded = true;
                store.selectCollection(col);
                store.selectRequest(freq);

                return;
            }
        }
    }
};

watch(
    () => props.collections,
    () => {
        if (
            props.activeCollectionId &&
            store.selectedCollection?.id !== props.activeCollectionId
        ) {
            selectCollectionFromId(props.activeCollectionId);
        }

        if (
            props.activeRequestId &&
            store.selectedRequest?.id !== props.activeRequestId
        ) {
            selectRequestFromId(props.activeRequestId);
        }
    },
    { deep: true },
);

watch(
    () => props.activeCollectionId,
    (newId) => {
        if (newId) {
            selectCollectionFromId(newId);
        }
    },
);

watch(
    () => props.activeRequestId,
    (newId) => {
        if (newId) {
            selectRequestFromId(newId);
        }
    },
);

onMounted(() => {
    if (props.activeCollectionId) {
        selectCollectionFromId(props.activeCollectionId);
    }

    if (props.activeRequestId) {
        selectRequestFromId(props.activeRequestId);
    }

    if (store.collections.length > 0 && !store.selectedCollection) {
        store.selectCollection(store.collections[0]);
    }
});

watchEffect(() => {
    const crumbs = [{ title: 'Collections', href: '/collections' }];

    if (store.selectedCollection) {
        crumbs.push({
            title: store.selectedCollection.name,
            href: `/collections/${store.selectedCollection.id}`,
        });
    }

    if (store.selectedRequest) {
        if (store.selectedRequest.folder_id && store.selectedCollection) {
            const folder = store.selectedCollection.folders?.find(
                (f) => f.id === store.selectedRequest?.folder_id,
            );

            if (folder) {
                crumbs.push({ title: folder.name, href: '#' });
            }
        }

        crumbs.push({
            title: store.selectedRequest.name,
            href: `/collections/${store.selectedRequest.collection_id}/requests/${store.selectedRequest.id}`,
        });
    }

    // Reactively update layoutState breadcrumbs for the AppSidebarHeader
    layoutState.breadcrumbs = crumbs;
});

defineOptions({
    layout: () => {
        return {
            managed: true, // Tell layoutState.ts to not overwrite breadcrumbs
            breadcrumbs: [],
        };
    },
});
</script>

<template>
    <Head title="Collections" />
    <div
        class="flex h-full min-h-0 flex-1 flex-col overflow-hidden bg-background"
    >
        <RequestEditor />
    </div>
</template>
