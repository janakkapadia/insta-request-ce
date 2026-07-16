<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import {
    ResizablePanelGroup,
    ResizablePanel,
    ResizableHandle,
} from '@/components/ui/resizable';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <ResizablePanelGroup direction="horizontal" class="h-screen w-full">
            <ResizablePanel
                :default-size="20"
                :min-size="15"
                :max-size="40"
                class="flex flex-col"
            >
                <AppSidebar class="h-full !w-full flex-1" collapsible="none" />
            </ResizablePanel>

            <ResizableHandle with-handle />

            <ResizablePanel
                :default-size="80"
                class="flex h-full flex-col bg-background"
            >
                <AppContent
                    variant="sidebar"
                    class="h-full flex-1 overflow-hidden"
                >
                    <AppSidebarHeader :breadcrumbs="breadcrumbs" />
                    <div class="flex min-h-0 flex-1 flex-col overflow-y-auto">
                        <slot />
                    </div>
                </AppContent>
            </ResizablePanel>
        </ResizablePanelGroup>
        <Toaster />
    </AppShell>
</template>
