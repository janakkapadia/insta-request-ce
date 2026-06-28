<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { Plus, Import, FolderOpen, Activity, LayoutGrid, Layers, Globe } from 'lucide-vue-next';
import { onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import * as collections from '@/routes/collections';
import { useWorkspaceStore } from '@/stores/workspace';
import type { Team } from '@/types';

const page = usePage();

onMounted(() => {
    if (page.props.status === 'Email verified!') {
        window.location.href = 'jackman://verified';
    }
});

interface Stats {
    total_collections: number;
    total_requests_made: number;
    total_environments: number;
}

interface RecentCollection {
    id: string;
    name: string;
    description: string | null;
    requests_count: number;
    updated_at: string;
}

interface RecentHistory {
    id: string;
    request_id: string | null;
    method: string;
    url: string;
    status: number;
    time_ms: number;
    created_at: string;
    request?: {
        name: string;
    };
}

const props = defineProps<{
    stats: Stats;
    recentCollections: RecentCollection[];
    recentHistory: RecentHistory[];
}>();

const store = useWorkspaceStore();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard().url,
            },
        ],
    }),
});

function formatDate(dateStr: string) {
    return new Date(dateStr).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: 'numeric'
    });
}

function getStatusColor(status: number) {
    if (status >= 200 && status < 300) {
return 'text-green-500';
}

    if (status >= 400 && status < 500) {
return 'text-yellow-500';
}

    if (status >= 500) {
return 'text-red-500';
}

    return 'text-muted-foreground';
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-y-auto p-6 lg:p-8">
        <!-- Header & Quick Actions -->
        <div v-if="$page.props.status === 'Email verified!'" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
            <span class="block sm:inline font-medium">Email successfully verified!</span>
        </div>

        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Workspace Overview</h1>
                <p class="text-muted-foreground mt-1 text-sm">
                    Manage your API collections, monitor activity, and collaborate with your team.
                </p>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" asChild>
                    <Link :href="collections.index().url">
                        <Import class="mr-2 h-4 w-4" />
                        Import
                    </Link>
                </Button>
                <Button @click="store.showNewCollectionModal = true">
                    <Plus class="mr-2 h-4 w-4" />
                    New Collection
                </Button>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="grid gap-4 md:grid-cols-3">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Total Collections</CardTitle>
                    <LayoutGrid class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.total_collections }}</div>
                    <p class="text-xs text-muted-foreground mt-1">Organized API endpoints</p>
                </CardContent>
            </Card>
            
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Environments</CardTitle>
                    <Globe class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.total_environments }}</div>
                    <p class="text-xs text-muted-foreground mt-1">Configured variable sets</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Requests Logged</CardTitle>
                    <Activity class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.total_requests_made }}</div>
                    <p class="text-xs text-muted-foreground mt-1">Total API calls executed</p>
                </CardContent>
            </Card>
        </div>

        <!-- Main Content Grid -->
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-7">
            <!-- Recent Collections -->
            <Card class="lg:col-span-4 flex flex-col">
                <CardHeader>
                    <CardTitle>Recent Collections</CardTitle>
                    <CardDescription>Collections you've recently modified or created.</CardDescription>
                </CardHeader>
                <CardContent class="flex-1">
                    <div v-if="recentCollections.length === 0" class="flex flex-col items-center justify-center h-40 text-center">
                        <Layers class="h-10 w-10 text-muted-foreground mb-3 opacity-50" />
                        <p class="text-sm text-muted-foreground">No collections found.</p>
                        <Button variant="link" class="mt-2" asChild>
                            <Link :href="collections.index().url">Create your first collection</Link>
                        </Button>
                    </div>
                    <div v-else class="grid gap-4 sm:grid-cols-2">
                        <Link 
                            v-for="collection in recentCollections" 
                            :key="collection.id"
                            :href="collections.show({ collection: collection.id }).url"
                            class="group relative flex flex-col p-4 rounded-xl border bg-card text-card-foreground shadow-sm transition-colors hover:bg-accent/50"
                        >
                            <div class="flex items-center gap-3 mb-2">
                                <div class="bg-primary/10 p-2 rounded-md">
                                    <FolderOpen class="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <h3 class="font-semibold text-sm line-clamp-1 group-hover:underline">{{ collection.name }}</h3>
                                    <p class="text-xs text-muted-foreground">{{ collection.requests_count }} requests</p>
                                </div>
                            </div>
                            <p v-if="collection.description" class="text-xs text-muted-foreground line-clamp-2 mt-1">
                                {{ collection.description }}
                            </p>
                            <div class="mt-auto pt-4 text-[10px] text-muted-foreground">
                                Updated {{ formatDate(collection.updated_at) }}
                            </div>
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <!-- Recent Activity Log -->
            <Card class="lg:col-span-3 flex flex-col">
                <CardHeader>
                    <CardTitle>Recent Activity</CardTitle>
                    <CardDescription>Latest API executions in your workspace.</CardDescription>
                </CardHeader>
                <CardContent class="flex-1 p-0">
                    <div v-if="recentHistory.length === 0" class="flex flex-col items-center justify-center h-40 text-center p-6">
                        <Activity class="h-8 w-8 text-muted-foreground mb-3 opacity-50" />
                        <p class="text-sm text-muted-foreground">No recent activity.</p>
                    </div>
                    <div v-else class="divide-y border-t">
                        <div 
                            v-for="log in recentHistory" 
                            :key="log.id"
                            class="flex items-center justify-between p-4 text-sm"
                        >
                            <div class="flex flex-col gap-1 min-w-0 pr-4">
                                <div class="flex items-center gap-2">
                                    <span :class="['font-mono font-medium text-xs', 
                                        log.method === 'GET' ? 'text-blue-500' :
                                        log.method === 'POST' ? 'text-green-500' :
                                        log.method === 'PUT' || log.method === 'PATCH' ? 'text-orange-500' :
                                        log.method === 'DELETE' ? 'text-red-500' : 'text-gray-500'
                                    ]">{{ log.method }}</span>
                                    <span class="truncate font-medium">{{ log.request?.name || 'Manual Execution' }}</span>
                                </div>
                                <span class="truncate text-xs text-muted-foreground font-mono" :title="log.url">{{ log.url }}</span>
                            </div>
                            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                <div class="flex items-center gap-2">
                                    <span :class="['font-mono font-semibold text-xs', getStatusColor(log.status)]">{{ log.status }}</span>
                                    <span class="text-xs text-muted-foreground">{{ log.time_ms }}ms</span>
                                </div>
                                <span class="text-[10px] text-muted-foreground">{{ formatDate(log.created_at) }}</span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
