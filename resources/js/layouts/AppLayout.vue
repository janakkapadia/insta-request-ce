<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { onMounted, onUnmounted } from 'vue';
import OfflineOverlay from '@/components/OfflineOverlay.vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import { layoutState, updateLayoutForPage } from '@/lib/layoutState';
import { useWorkspaceStore } from '@/stores/workspace';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();

const page = usePage();

// Automatically update dynamic layout state when page component or props change
watch(
    () => [page.component, page.props],
    () => {
        updateLayoutForPage(page.component, page.props);
    },
    { immediate: true, deep: true }
);


const handleBeforeUnload = (e: BeforeUnloadEvent) => {
    const store = useWorkspaceStore();
    if (store.hasDirtyRequests) {
        e.preventDefault();
        e.returnValue = '';
    }
};

onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload);
});

onUnmounted(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
});

// Fallback to manual props if provided, otherwise fallback to global layout state
const activeBreadcrumbs = computed(() => {
    if (props.breadcrumbs && props.breadcrumbs.length > 0) {
        return props.breadcrumbs;
    }

    return layoutState.breadcrumbs;
});

</script>

<template>
    <AppLayout :breadcrumbs="activeBreadcrumbs">
        <slot />
        <OfflineOverlay />
    </AppLayout>
</template>

