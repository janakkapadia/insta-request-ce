<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3';
import {
    Database,
    SlidersHorizontal,
    History,
    ChevronDown,
    ChevronRight,
    Plus,
    Trash2,
    Upload,
    Download,
    MoreVertical,
    Pencil,
    BookOpen,
    LayoutDashboard,
    FolderPlus,
    FilePlus,
    CheckSquare,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { computed, onMounted } from 'vue';
import CollectionSidebarTree from '@/components/CollectionTree/CollectionSidebarTree.vue';
import CommandPalette from '@/components/CommandPalette.vue';
import ExportModal from '@/components/ImportExport/ExportModal.vue';
import ImportModal from '@/components/ImportExport/ImportModal.vue';
import NavUser from '@/components/NavUser.vue';
import TeamSwitcher from '@/components/TeamSwitcher.vue';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    ResizableHandle,
    ResizablePanel,
    ResizablePanelGroup,
} from '@/components/ui/resizable';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarGroup,
} from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { dashboard } from '@/routes';
import environments from '@/routes/environments';
import history from '@/routes/history';
import { useWorkspaceStore } from '@/stores/workspace';
import type { NavItem } from '@/types';

const page = usePage();
const store = useWorkspaceStore();

const newCollectionName = ref('');
const newCollectionDescription = ref('');
const isCreating = ref(false);
const showImportModal = ref(false);
const showExportModal = ref(false);
const exportCollectionId = ref('');

onMounted(async () => {
    // Only refresh if empty to prevent duplicate network calls and user is authenticated
    if (page.props.auth?.user && store.collections.length === 0) {
        await store.refreshCollections();
    }

    // Select first collection if none is selected and we are on collections page
    if (page.url.includes('/collections') && store.collections.length > 0 && !store.selectedCollection) {
        store.selectedCollection = store.collections[0];
    }
});

const handleCreateCollection = async () => {
    if (!newCollectionName.value.trim()) {
return;
}

    isCreating.value = true;

    try {
        await store.createCollection(newCollectionName.value, newCollectionDescription.value);
        newCollectionName.value = '';
        newCollectionDescription.value = '';
        store.showNewCollectionModal = false;
    } finally {
        isCreating.value = false;
    }
};

const cancelCollectionCreation = () => {
    newCollectionName.value = '';
    newCollectionDescription.value = '';
    store.showNewCollectionModal = false;
};

const collectionToEdit = ref<any | null>(null);
const showEditCollection = ref(false);
const editCollectionName = ref('');
const editCollectionDescription = ref('');
const isUpdating = ref(false);

const handleEditCollection = (collection: any) => {
    collectionToEdit.value = { id: collection.id };
    editCollectionName.value = collection.name;
    editCollectionDescription.value = collection.description || '';
    showEditCollection.value = true;
};

const confirmEditCollection = async () => {
    if (!collectionToEdit.value || !editCollectionName.value.trim()) {
return;
}

    isUpdating.value = true;

    try {
        await store.updateCollection(
            collectionToEdit.value.id,
            editCollectionName.value,
            editCollectionDescription.value,
        );
        showEditCollection.value = false;
        collectionToEdit.value = null;
    } finally {
        isUpdating.value = false;
    }
};

const collectionToDelete = ref<any | null>(null);
const isDeleting = ref(false);

const handleDeleteCollection = (collection: any) => {
    collectionToDelete.value = collection;
};

const confirmDeleteCollection = async () => {
    if (!collectionToDelete.value) {
return;
}

    isDeleting.value = true;

    try {
        await store.deleteCollection(collectionToDelete.value.id);
    } finally {
        isDeleting.value = false;
        collectionToDelete.value = null;
    }
};

const handleExportCollection = (collectionId: string) => {
    exportCollectionId.value = collectionId;
    showExportModal.value = true;
};

const handleImported = async () => {
    await store.refreshCollections();
};

const handleSelectCollection = (collection: any) => {
    collection.expanded = !collection.expanded;

    if (collection.expanded && !collection.has_loaded_details) {
        store.fetchCollectionDetails(collection.id);
    }
};

const handleDragOverCollection = (e: DragEvent) => {
    const types = Array.from(e.dataTransfer?.types || []);

    if (types.includes('text/plain')) {
        if (e.dataTransfer) {
            e.dataTransfer.dropEffect = 'move';
        }
    }
};

const handleDropOnCollectionHeader = async (e: DragEvent, collectionId: string) => {
    try {
        // Check if we're dropping a folder
        if (store.draggedFolderId) {
            const collection = store.collections.find((c: any) => c.id === collectionId);

            if (collection) {
                const folder = collection.folders?.find((f: any) => f.id === store.draggedFolderId);

                if (folder && folder.parent_id === null) {
                    return;
                }
            }

            await store.moveFolder(store.draggedFolderId, collectionId, null);
            return;
        }

        // Otherwise handle request drop
        let requestId = store.draggedRequestId;

        if (!requestId) {
            const data = e.dataTransfer?.getData('text/plain');

            if (data) {
                try {
                    const parsed = JSON.parse(data);

                    if (parsed.type === 'jackman-request') {
requestId = parsed.id;
} else if (parsed.type === 'jackman-folder') {
                        const collection = store.collections.find((c: any) => c.id === collectionId);
                        const folder = collection?.folders?.find((f: any) => f.id === parsed.id);

                        if (folder && folder.parent_id !== null) {
                            await store.moveFolder(parsed.id, null);
                        }

                        return;
                    }
                } catch { /* ignore parse errors */ }
            }
        }

        if (!requestId) {
return;
}
        
        const collection = store.collections.find((c: any) => c.id === collectionId);

        if (collection) {
            if (collection.requests?.some((r: any) => r.id === requestId && !r.folder_id)) {
                // Already in the root of this collection
                return;
            }
        }

        await store.moveRequest(requestId, collectionId, null);
    } catch (err) {
        // Not valid JSON or not our drag event
    } finally {
        store.draggedRequestId = null;
        store.draggedFolderId = null;
    }
};

const handleDropOnSidebarVoid = async (e: DragEvent) => {
    if (store.collections && store.collections.length > 0) {
        const lastCol = store.collections[store.collections.length - 1];
        await handleDropOnCollectionHeader(e, lastCol.id);
    }
};

const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/dashboard',
);

const environmentsUrl = computed(() =>
    page.props.currentTeam
        ? environments.index(page.props.currentTeam.slug).url
        : '#',
);

const historyUrl = computed(() =>
    page.props.currentTeam
        ? history.index(page.props.currentTeam.slug).url
        : '#',
);

const documentationUrl = computed(() => '/documentation');

const handleNavAway = () => {
    store.selectedCollection = null;
    store.selectedRequest = null;
};

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Environments',
        href: environmentsUrl.value,
        icon: SlidersHorizontal,
    },
    {
        title: 'History',
        href: historyUrl.value,
        icon: History,
    },
    {
        title: 'API Docs',
        href: documentationUrl.value,
        icon: BookOpen,
    },
]);

// ── Collection initials badge helpers ─────────────────────────────────────
const getCollectionInitials = (name: string): string => {
    const words = name.trim().split(/\s+/);

    if (words.length >= 2) {
        return (words[0][0] + words[1][0]).toUpperCase();
    }

    return name.substring(0, 2).toUpperCase();
};

// Palette of tasteful bg/text pairs (Tailwind-compatible inline styles)
const COLLECTION_COLORS = [
    { bg: '#f59e0b22', text: '#d97706', border: '#f59e0b44' }, // amber
    { bg: '#6366f122', text: '#4f46e5', border: '#6366f144' }, // indigo
    { bg: '#10b98122', text: '#059669', border: '#10b98144' }, // emerald
    { bg: '#ec489922', text: '#db2777', border: '#ec489944' }, // pink
    { bg: '#3b82f622', text: '#2563eb', border: '#3b82f644' }, // blue
    { bg: '#8b5cf622', text: '#7c3aed', border: '#8b5cf644' }, // violet
    { bg: '#f9731622', text: '#ea580c', border: '#f9731644' }, // orange
    { bg: '#14b8a622', text: '#0d9488', border: '#14b8a644' }, // teal
];

const getCollectionColor = (name: string) => {
    let hash = 0;

    for (let i = 0; i < name.length; i++) {
        hash = (hash * 31 + name.charCodeAt(i)) & 0xffffffff;
    }

    return COLLECTION_COLORS[Math.abs(hash) % COLLECTION_COLORS.length];
};
</script>

<template>
    <Sidebar collapsible="none" variant="sidebar" class="!w-full !border-r-0 overflow-hidden" :class="{ 'pointer-events-none opacity-50': isCreating }">
        <SidebarHeader class="relative">
            <!-- Top area: Logo/App name + User dropdown -->
            <div class="flex items-center gap-2 py-2">
                <img src="/logo.svg" class="h-11 w-auto ml-2 pointer-events-none" alt="InstaRequest" />
            </div>
            <SidebarMenu>
                <SidebarMenuItem>
                    <TeamSwitcher />
                </SidebarMenuItem>
            </SidebarMenu>
            <SidebarMenu
                class="px-2 pt-1 pb-1 group-data-[collapsible=icon]:hidden"
            >
                <SidebarMenuItem>
                    <CommandPalette />
                </SidebarMenuItem>
            </SidebarMenu>

            <!-- Horizontal main navigation -->
            <div class="px-2 pb-2 group-data-[collapsible=icon]:hidden flex items-center gap-0.5 justify-between">
                <Link :href="dashboardUrl" class="flex flex-col items-center justify-center p-1.5 rounded-md hover:bg-sidebar-accent hover:text-sidebar-accent-foreground text-muted-foreground flex-1 min-w-0" @click="handleNavAway" :class="{ 'bg-sidebar-accent text-sidebar-accent-foreground': page.url.includes('/dashboard') }" title="Dashboard">
                    <LayoutDashboard class="h-4 w-4 mb-1" />
                    <span class="text-[9px] font-medium leading-none truncate w-full text-center">Dashboard</span>
                </Link>
                <Link :href="environmentsUrl" class="flex flex-col items-center justify-center p-1.5 rounded-md hover:bg-sidebar-accent hover:text-sidebar-accent-foreground text-muted-foreground flex-1 min-w-0" @click="handleNavAway" :class="{ 'bg-sidebar-accent text-sidebar-accent-foreground': page.url.includes('/environments') }" title="Environments">
                    <SlidersHorizontal class="h-4 w-4 mb-1" />
                    <span class="text-[9px] font-medium leading-none truncate w-full text-center">Env</span>
                </Link>
                <Link :href="historyUrl" class="flex flex-col items-center justify-center p-1.5 rounded-md hover:bg-sidebar-accent hover:text-sidebar-accent-foreground text-muted-foreground flex-1 min-w-0" @click="handleNavAway" :class="{ 'bg-sidebar-accent text-sidebar-accent-foreground': page.url.includes('/history') }" title="History">
                    <History class="h-4 w-4 mb-1" />
                    <span class="text-[9px] font-medium leading-none truncate w-full text-center">History</span>
                </Link>
                <Link :href="documentationUrl" class="flex flex-col items-center justify-center p-1.5 rounded-md hover:bg-sidebar-accent hover:text-sidebar-accent-foreground text-muted-foreground flex-1 min-w-0" @click="handleNavAway" :class="{ 'bg-sidebar-accent text-sidebar-accent-foreground': page.url.includes('/documentation') }" title="API Docs">
                    <BookOpen class="h-4 w-4 mb-1" />
                    <span class="text-[9px] font-medium leading-none truncate w-full text-center">Docs</span>
                </Link>
            </div>
        </SidebarHeader>

        <SidebarContent class="flex h-full flex-col overflow-hidden pb-4" @dragenter.prevent @dragover.prevent>
            <!-- Sidebar navigation section -->
            <SidebarGroup
                class="mt-2 flex flex-1 flex-col overflow-hidden px-2 py-0 group-data-[collapsible=icon]:hidden"
                @dragenter.prevent @dragover.prevent
            >
                <SidebarMenu class="flex h-full flex-col overflow-hidden gap-1.5" @dragenter.prevent @dragover.prevent>
                            <!-- Collapsible Collections List (Expanded by Default) -->
                            <Collapsible
                                :default-open="true"
                                class="group/collapsible flex flex-col overflow-hidden h-full pb-2"
                                @dragenter.prevent @dragover.prevent
                            >
                        <SidebarMenuItem
                            class="flex list-none flex-col overflow-hidden"
                            @dragenter.prevent @dragover.prevent
                        >
                            <div
                                class="flex w-full shrink-0 items-center justify-between select-none"
                            >
                                <CollapsibleTrigger as-child class="min-w-0 flex-1">
                                    <SidebarMenuButton
                                        class="w-full shrink-0 justify-start gap-2 py-2 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase hover:bg-transparent"
                                    >
                                        <Database class="h-3.5 w-3.5" />
                                        <span>Collections</span>
                                        <ChevronDown
                                            class="h-3.5 w-3.5 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-180"
                                        />
                                    </SidebarMenuButton>
                                </CollapsibleTrigger>
                                <div
                                    class="mr-1 flex shrink-0 items-center"
                                >
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <button
                                                class="rounded p-1 text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                                                @click.stop
                                            >
                                                <span class="sr-only">Collection Actions</span>
                                                <MoreVertical class="h-3.5 w-3.5" />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="w-48">
                                            <DropdownMenuItem
                                                class="cursor-pointer gap-2 text-xs"
                                                @click.stop="store.showNewCollectionModal = true"
                                            >
                                                <Plus class="h-3.5 w-3.5 text-muted-foreground" />
                                                <span>Create New Collection</span>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                class="cursor-pointer gap-2 text-xs"
                                                @click.stop="showImportModal = true"
                                            >
                                                <Upload class="h-3.5 w-3.5 text-muted-foreground" />
                                                <span>Import Collection</span>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                class="cursor-pointer gap-2 text-xs"
                                                @click.stop="showExportModal = true"
                                            >
                                                <Download class="h-3.5 w-3.5 text-muted-foreground" />
                                                <span>Export Collection</span>
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </div>
                            <CollapsibleContent
                                class="mt-1 flex flex-1 flex-col overflow-hidden"
                                @dragenter.prevent @dragover.prevent
                            >
                                <ScrollArea class="flex-1 overflow-y-auto pr-1" @dragenter.prevent @dragover.prevent @drop.prevent="handleDropOnSidebarVoid">
                                    <div class="space-y-1" @dragenter.prevent @dragover.prevent @drop.prevent="handleDropOnSidebarVoid">
                                        <!-- Collections List -->
                                        <Collapsible
                                            v-for="collection in store.collections"
                                            :key="collection.id"
                                            :open="collection.expanded"
                                            @update:open="(val) => { collection.expanded = val; if (val && !collection.has_loaded_details) store.fetchCollectionDetails(collection.id); }"
                                            class="group/colTree"
                                            @dragenter.prevent
                                            @dragover.prevent
                                        >
                                            <div
                                                class="group/coll flex cursor-pointer items-center justify-between rounded-md px-2 py-0.5 text-xs font-medium transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                                                :class="{
                                                    'bg-sidebar-accent font-semibold text-sidebar-accent-foreground':
                                                        page.url.includes('/collections') && store.selectedCollection?.id === collection.id,
                                                }"
                                                @click="
                                                    handleSelectCollection(collection)
                                                "
                                                @dragenter.prevent
                                                @dragover.prevent="handleDragOverCollection"
                                                @drop.prevent.stop="handleDropOnCollectionHeader($event, collection.id)"
                                            >
                                                <div
                                                    class="flex min-w-0 flex-1 items-center gap-2"
                                                >
                                                    <CollapsibleTrigger as-child>
                                                        <button class="p-0.5 rounded hover:bg-muted/50" @click.stop="collection.expanded = !collection.expanded; if(collection.expanded && !collection.has_loaded_details) store.fetchCollectionDetails(collection.id);">
                                                            <ChevronRight class="h-3 w-3 transition-transform" :class="{ 'rotate-90': collection.expanded }" />
                                                        </button>
                                                    </CollapsibleTrigger>
                                                    <!-- Initials badge -->
                                                    <span
                                                        class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded border text-[9px] leading-none font-bold select-none"
                                                        :style="{
                                                            background:
                                                                getCollectionColor(
                                                                    collection.name,
                                                                ).bg,
                                                            color: getCollectionColor(
                                                                collection.name,
                                                            ).text,
                                                            borderColor:
                                                                getCollectionColor(
                                                                    collection.name,
                                                                ).border,
                                                        }"
                                                    >
                                                        {{
                                                            getCollectionInitials(
                                                                collection.name,
                                                            )
                                                        }}
                                                    </span>
                                                    <span class="truncate">{{
                                                        collection.name
                                                    }}</span>
                                                </div>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger as-child>
                                                        <button
                                                            class="rounded p-0.5 text-muted-foreground opacity-0 transition-opacity group-hover/coll:opacity-100 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                                                            @click.stop
                                                        >
                                                            <MoreVertical class="h-3 w-3" />
                                                        </button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end" class="w-40" @close-auto-focus="(e) => e.preventDefault()">
                                                        <DropdownMenuItem
                                                            class="cursor-pointer gap-2 text-xs"
                                                            @click.stop="collection.expanded = true; if(!collection.has_loaded_details) store.fetchCollectionDetails(collection.id); store.activeNewFolder = collection.id;"
                                                        >
                                                            <FolderPlus class="h-3.5 w-3.5 text-muted-foreground" />
                                                            <span>Add Folder</span>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            class="cursor-pointer gap-2 text-xs"
                                                            @click.stop="collection.expanded = true; if(!collection.has_loaded_details) store.fetchCollectionDetails(collection.id); store.activeNewRequest = collection.id;"
                                                        >
                                                            <FilePlus class="h-3.5 w-3.5 text-muted-foreground" />
                                                            <span>Add Request</span>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            class="cursor-pointer gap-2 text-xs"
                                                            @click.stop="collection.expanded = true; if(!collection.has_loaded_details) store.fetchCollectionDetails(collection.id); store.selectionModeCollectionId = collection.id; store.selectedRequestIds = []"
                                                        >
                                                            <CheckSquare class="h-3.5 w-3.5 text-muted-foreground" />
                                                            <span>Select Requests</span>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            class="cursor-pointer gap-2 text-xs"
                                                            @click.stop="handleEditCollection(collection)"
                                                        >
                                                            <Pencil class="h-3.5 w-3.5 text-muted-foreground" />
                                                            <span>Edit</span>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            v-if="store.collections.length > 1"
                                                            class="cursor-pointer gap-2 text-xs text-red-600 focus:text-red-600"
                                                            @click.stop="handleDeleteCollection(collection)"
                                                        >
                                                            <Trash2 class="h-3.5 w-3.5" />
                                                            <span>Delete</span>
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </div>
                                            <CollapsibleContent class="pl-4 pr-1 py-1" @dragenter.prevent @dragover.prevent @drop.prevent.stop="handleDropOnCollectionHeader($event, collection.id)">
                                                <CollectionSidebarTree :collection="collection" />
                                            </CollapsibleContent>
                                        </Collapsible>
                                    </div>
                                </ScrollArea>
                            </CollapsibleContent>
                        </SidebarMenuItem>
                        </Collapsible>
                        </SidebarMenu>
            </SidebarGroup>

            <!-- Collapsed (icon-only) View -->
            <SidebarGroup
                class="mt-2 hidden flex-col px-2 py-0 group-data-[collapsible=icon]:flex animate-in fade-in duration-200"
            >
                <SidebarMenu class="flex flex-col gap-1.5">
                    <!-- Dashboard Icon -->
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="page.url.includes('/dashboard')"
                            tooltip="Dashboard"
                            class="justify-center p-0!"
                        >
                            <Link :href="dashboardUrl" @click="handleNavAway">
                                <LayoutDashboard class="h-4 w-4" />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <!-- "+" to create a new collection -->
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            tooltip="New Collection"
                            class="justify-center p-0!"
                            @click="store.showNewCollectionModal = true"
                        >
                            <Plus class="h-4 w-4" />
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <!-- One folder icon per collection -->
                    <SidebarMenuItem
                        v-for="collection in store.collections"
                        :key="collection.id"
                    >
                        <SidebarMenuButton
                            :tooltip="collection.name"
                            :is-active="
                                page.url.includes('/collections') && store.selectedCollection?.id === collection.id
                            "
                            class="justify-center p-0!"
                            @click="handleSelectCollection(collection)"
                        >
                            <!-- Initials badge (collapsed) -->
                            <span
                                class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded border text-[9px] leading-none font-bold select-none"
                                :style="{
                                    background: getCollectionColor(
                                        collection.name,
                                    ).bg,
                                    color: getCollectionColor(collection.name)
                                        .text,
                                    borderColor: getCollectionColor(
                                        collection.name,
                                    ).border,
                                }"
                            >
                                {{ getCollectionInitials(collection.name) }}
                            </span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <!-- Thin separator in collapsed view -->
                    <div class="h-px bg-sidebar-border/50 my-1.5"></div>

                    <!-- Environments Icon -->
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="page.url.includes('/environments')"
                            tooltip="Environments"
                            class="justify-center p-0!"
                        >
                            <Link :href="environmentsUrl" @click="handleNavAway">
                                <SlidersHorizontal class="h-4 w-4" />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <!-- History Icon -->
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="page.url.includes('/history')"
                            tooltip="History"
                            class="justify-center p-0!"
                        >
                            <Link :href="historyUrl" @click="handleNavAway">
                                <History class="h-4 w-4" />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <!-- API Docs Icon -->
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="page.url.includes('/documentation')"
                            tooltip="API Docs"
                            class="justify-center p-0!"
                        >
                            <Link :href="documentationUrl" @click="handleNavAway">
                                <BookOpen class="h-4 w-4" />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />

    <!-- Delete Collection Confirmation Dialog -->
    <Dialog
        :open="collectionToDelete !== null"
        @update:open="
            (val) => {
                if (!val) collectionToDelete = null;
            }
        "
    >
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>Delete Collection</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete the collection
                    <strong>"{{ collectionToDelete?.name }}"</strong>? This
                    action cannot be undone and will permanently delete all
                    folders and requests in this collection.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="mt-4 gap-2">
                <DialogClose as-child>
                    <Button variant="secondary" :disabled="isDeleting"
                        >Cancel</Button
                    >
                </DialogClose>
                <Button
                    variant="destructive"
                    :disabled="isDeleting"
                    @click="confirmDeleteCollection"
                >
                    <span v-if="isDeleting">Deleting...</span>
                    <span v-else>Delete</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Edit Collection Modal -->
    <Dialog
        :open="showEditCollection"
        @update:open="(val) => { if (!val) { showEditCollection = false; collectionToEdit = null; } }"
    >
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>Edit Collection</DialogTitle>
                <DialogDescription>
                    Update the collection name and description.
                </DialogDescription>
            </DialogHeader>
            <div class="flex flex-col gap-4 py-2">
                <div class="flex flex-col gap-2">
                    <Label for="edit-collection-name" class="text-sm font-medium">Name</Label>
                    <Input
                        id="edit-collection-name"
                        v-model="editCollectionName"
                        placeholder="Collection name"
                        class="h-9"
                        @keyup.enter="confirmEditCollection"
                        autofocus
                    />
                </div>
                <div class="flex flex-col gap-2">
                    <Label for="edit-collection-description" class="text-sm font-medium">Description</Label>
                    <textarea
                        id="edit-collection-description"
                        v-model="editCollectionDescription"
                        placeholder="Optional description..."
                        rows="3"
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 resize-none"
                    />
                </div>
            </div>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary" :disabled="isUpdating">Cancel</Button>
                </DialogClose>
                <Button
                    :disabled="!editCollectionName.trim() || isUpdating"
                    @click="confirmEditCollection"
                >
                    <span v-if="isUpdating">Saving...</span>
                    <span v-else>Save</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Create Collection Modal -->
    <Dialog
        :open="store.showNewCollectionModal"
        @update:open="(val) => { if (!val) cancelCollectionCreation(); }"
    >
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>Create Collection</DialogTitle>
                <DialogDescription>
                    Create a new collection to organize your API requests.
                </DialogDescription>
            </DialogHeader>
            <div class="flex flex-col gap-4 py-2">
                <div class="flex flex-col gap-2">
                    <Label for="collection-name" class="text-sm font-medium">Name</Label>
                    <Input
                        id="collection-name"
                        v-model="newCollectionName"
                        placeholder="e.g. Payment API"
                        class="h-9"
                        @keyup.enter="handleCreateCollection"
                        autofocus
                    />
                </div>
                <div class="flex flex-col gap-2">
                    <Label for="collection-description" class="text-sm font-medium">Description</Label>
                    <textarea
                        id="collection-description"
                        v-model="newCollectionDescription"
                        placeholder="Optional description..."
                        rows="3"
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 resize-none"
                    />
                </div>
            </div>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary" :disabled="isCreating">Cancel</Button>
                </DialogClose>
                <Button
                    :disabled="!newCollectionName.trim() || isCreating"
                    @click="handleCreateCollection"
                >
                    <span v-if="isCreating">Creating...</span>
                    <span v-else>Create</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Import / Export Modals -->
    <ImportModal v-model:open="showImportModal" @imported="handleImported" />
    <ExportModal
        v-model:open="showExportModal"
        :preselected-collection-id="exportCollectionId"
    />

</template>
