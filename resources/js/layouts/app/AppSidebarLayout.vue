<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { ResizablePanelGroup, ResizablePanel, ResizableHandle } from '@/components/ui/resizable';
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
            <ResizablePanel :default-size="20" :min-size="15" :max-size="40" class="flex flex-col">
                <AppSidebar class="!w-full h-full flex-1" collapsible="none" />
            </ResizablePanel>
            
            <ResizableHandle with-handle />
            
            <ResizablePanel :default-size="80" class="flex flex-col h-full bg-background">
                <AppContent variant="sidebar" class="overflow-hidden flex-1 h-full">
                    <AppSidebarHeader :breadcrumbs="breadcrumbs" />
                    <div class="flex-1 flex flex-col min-h-0 overflow-y-auto">
                        <slot />
                    </div>
                </AppContent>
            </ResizablePanel>
        </ResizablePanelGroup>
        <Toaster />
    </AppShell>
</template>
