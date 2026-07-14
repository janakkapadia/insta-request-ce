<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BookOpen,
    Eye,
    Globe,
    Lock,
    Save,
    Settings,
    FileText,
    ChevronRight,
    Play,
    Plus,
    Trash2,
    Check,
    AlertCircle,
    Info,
    Search,
    ExternalLink,
    X
} from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import DocFolderNode from '@/components/Documentation/DocFolderNode.vue';
import { parseMarkdown } from '@/lib/markdown';

// Props passed from Inertia controller
const props = defineProps<{
    collections: Array<{
        id: string;
        name: string;
        description: string | null;
        requests: Array<{
            id: string;
            name: string;
            description: string | null;
            method: string;
            url: string;
            headers: any;
            query_params: any;
            body: any;
        }>;
    }>;
    selectedCollectionId?: string;
    documentation?: any;
    requestsList?: any[];
    environments?: Array<{
        id: string;
        name: string;
        color: string | null;
        variables: Array<{ key: string; value: string; enabled: boolean }>;
    }>;
}>();

// State vars
const selectedCollectionId = ref<string>(props.selectedCollectionId || '');
const isLoading = ref<boolean>(false);
const activeTab = ref<'settings' | 'intro' | 'requests'>('settings');

// Documentation details
const docId = ref<string>('');
const isPublic = ref<boolean>(false);
const publicSlug = ref<string>('');
const version = ref<string>('1.0.0');
const markdownIntro = ref<string>('');
const authInfo = ref<string>('');
const docSettings = ref<any>({});
const environmentId = ref<string>('');

// Requests of selected collection with descriptions and mock response examples
const requestsList = ref<Array<any>>([]);
const selectedRequestId = ref<string>('');

// Selected request details
const selectedRequest = computed(() => {
    return requestsList.value.find(r => r.id === selectedRequestId.value) || null;
});

const activeCollectionFolders = computed(() => {
    if (!selectedCollectionId.value) return [];
    const col = props.collections.find(c => c.id === selectedCollectionId.value);
    return col?.folders || [];
});

const rootFolders = computed(() => {
    return activeCollectionFolders.value.filter(f => !f.parent_id);
});

const rootRequests = computed(() => {
    return requestsList.value.filter(r => !r.folder_id);
});

// Markdown pre-render computed properties
const introPreview = computed(() => {
    return parseMarkdown(markdownIntro.value);
});

const authPreview = computed(() => {
    return parseMarkdown(authInfo.value);
});

// Response Mock Example Creator State
const showAddExample = ref<boolean>(false);
const newExampleName = ref<string>('200 OK Success');
const newExampleStatus = ref<number>(200);
const newExampleBody = ref<string>('{\n  "status": "success"\n}');

// Keep track of edited descriptions
const editedRequestDescriptions = ref<Record<string, string>>({});

watch(selectedCollectionId, (newVal) => {
    if (!newVal) {
        docId.value = '';
        requestsList.value = [];
        return;
    }
    
    if (newVal !== props.selectedCollectionId) {
        isLoading.value = true;
        router.get('/documentation', { collection_id: newVal }, {
            preserveState: true,
            preserveScroll: true,
            only: ['documentation', 'requestsList', 'selectedCollectionId', 'environments'],
            onFinish: () => isLoading.value = false
        });
    }
});

watch(() => props.documentation, (newDoc) => {
    if (newDoc) {
        docId.value = newDoc.id;
        isPublic.value = newDoc.is_public;
        publicSlug.value = newDoc.public_slug;
        version.value = newDoc.version;
        markdownIntro.value = newDoc.markdown_intro || '';
        authInfo.value = newDoc.auth_info || '';
        docSettings.value = newDoc.settings || {};
        environmentId.value = newDoc.environment_id || '';
    } else {
        docId.value = '';
        environmentId.value = '';
    }
}, { immediate: true });

watch(() => props.requestsList, (newList) => {
    requestsList.value = newList ? JSON.parse(JSON.stringify(newList)) : [];
    editedRequestDescriptions.value = {};
    if (requestsList.value.length > 0) {
        if (!requestsList.value.find(r => r.id === selectedRequestId.value)) {
            selectedRequestId.value = requestsList.value[0].id;
        }
    } else {
        selectedRequestId.value = '';
    }
}, { immediate: true });

// Save Documentation
const handleSave = () => {
    if (!selectedCollectionId.value) return;
    
    isLoading.value = true;

    router.post(`/documentation/collection/${selectedCollectionId.value}`, {
        is_public: isPublic.value,
        public_slug: publicSlug.value,
        version: version.value,
        markdown_intro: markdownIntro.value,
        auth_info: authInfo.value,
        settings: docSettings.value,
        environment_id: environmentId.value || null,
        requests_descriptions: { ...editedRequestDescriptions.value }
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Documentation saved successfully');
        },
        onError: (errors: any) => {
            if (errors.public_slug) {
                toast.error(errors.public_slug);
            } else {
                toast.error('Failed to save documentation');
            }
        },
        onFinish: () => isLoading.value = false
    });
};

// Add Mock Response Example
const handleAddExample = () => {
    if (!selectedRequestId.value) return;
    
    router.post(`/documentation/request/${selectedRequestId.value}/response-examples`, {
        name: newExampleName.value,
        status_code: newExampleStatus.value,
        body: newExampleBody.value,
        headers: { 'Content-Type': 'application/json' }
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Response example added!');
            newExampleName.value = '200 OK Success';
            newExampleStatus.value = 200;
            newExampleBody.value = '{\n  "status": "success"\n}';
            showAddExample.value = false;
        },
        onError: () => toast.error('Failed to add example')
    });
};

// Delete Response Example
const handleDeleteExample = (exampleId: string) => {
    router.delete(`/documentation/response-examples/${exampleId}`, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => toast.success('Example deleted successfully'),
        onError: () => toast.error('Failed to delete example')
    });
};

// Track modified request description
const updateRequestDescription = (desc: string) => {
    if (!selectedRequestId.value) {
return;
}

    editedRequestDescriptions.value[selectedRequestId.value] = desc;
    
    // Also keep local reactive object updated
    const req = requestsList.value.find(r => r.id === selectedRequestId.value);

    if (req) {
        req.description = desc;
    }
};

import { getMethodBadgeColors as getMethodColor } from '@/lib/method-colors';
</script>

<template>
    <Head title="API Documentation Generator" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-y-auto p-6">
        <!-- Title and Save bar -->
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-foreground flex items-center gap-2">
                    <BookOpen class="h-6 w-6 text-primary" />
                    API Documentation Generator
                </h1>
                <p class="text-sm text-muted-foreground">
                    Generate beautiful, interactive, shareable public or private documentation from collections.
                </p>
            </div>
            
            <div class="flex items-center gap-3">
                <select
                    v-model="selectedCollectionId"
                    class="h-9 w-64 rounded-md border border-input bg-background px-3 py-1.5 text-sm ring-offset-background focus:outline-hidden focus:ring-2 focus:ring-ring focus:ring-offset-2"
                >
                    <option value="">Select a Collection...</option>
                    <option
                        v-for="collection in props.collections"
                        :key="collection.id"
                        :value="collection.id"
                    >
                        {{ collection.name }}
                    </option>
                </select>

                <Button
                    v-if="selectedCollectionId"
                    @click="handleSave"
                    :disabled="isLoading"
                    class="gap-2"
                >
                    <Save class="h-4 w-4" />
                    Save Documentation
                </Button>
            </div>
        </div>

        <div v-if="!selectedCollectionId" class="flex-1 flex flex-col items-center justify-center border border-dashed rounded-xl p-12 bg-card text-center min-h-[400px]">
            <div class="rounded-full bg-primary/10 p-4 mb-4">
                <BookOpen class="h-10 w-10 text-primary" />
            </div>
            <h3 class="text-lg font-bold text-foreground mb-1">No Collection Selected</h3>
            <p class="text-sm text-muted-foreground max-w-sm mb-6">
                Choose a collection from the dropdown in the header to start generating gorgeous web docs and code snippets.
            </p>
        </div>

        <!-- Selected Collection Panel -->
        <div v-else class="grid grid-cols-1 lg:grid-cols-4 gap-6 flex-1 items-start">
            <!-- Tabs Sidebar -->
            <div class="flex flex-col gap-2 bg-card rounded-xl p-3 border border-border lg:col-span-1 shadow-sm">
                <button
                    @click="activeTab = 'settings'"
                    class="flex items-center gap-2.5 px-3 py-2 text-sm font-semibold rounded-md transition-colors text-left"
                    :class="activeTab === 'settings' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                >
                    <Settings class="h-4 w-4" />
                    General Settings
                </button>
                <button
                    @click="activeTab = 'intro'"
                    class="flex items-center gap-2.5 px-3 py-2 text-sm font-semibold rounded-md transition-colors text-left"
                    :class="activeTab === 'intro' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                >
                    <FileText class="h-4 w-4" />
                    Introduction Guide
                </button>
                <button
                    @click="activeTab = 'requests'"
                    class="flex items-center gap-2.5 px-3 py-2 text-sm font-semibold rounded-md transition-colors text-left"
                    :class="activeTab === 'requests' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                >
                    <Play class="h-4 w-4" />
                    Request Parameters
                </button>

                <div class="mt-4 border-t pt-4 px-2" v-if="isPublic && publicSlug">
                    <a
                        :href="`/docs/${selectedCollectionId}/${publicSlug}`"
                        target="_blank"
                        class="inline-flex w-full items-center justify-center gap-1.5 px-3 py-2 rounded-md bg-emerald-500/10 text-emerald-500 hover:bg-emerald-500/20 text-xs font-bold transition-colors"
                    >
                        <ExternalLink class="h-3.5 w-3.5" />
                        View Live Docs portal
                    </a>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="lg:col-span-3 flex flex-col gap-6">
                <!-- General Settings -->
                <Card v-if="activeTab === 'settings'" class="shadow-sm">
                    <CardHeader>
                        <CardTitle class="text-lg">General Settings</CardTitle>
                        <CardDescription>Configure path visibility, shareable link settings, version logs, and global auth variables.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Portal Visibility Toggle -->
                        <div class="flex items-center justify-between p-4 border rounded-lg bg-muted/20">
                            <div class="flex gap-3 items-start">
                                <div class="rounded-full bg-primary/10 p-2 mt-0.5">
                                    <Globe v-if="isPublic" class="h-4 w-4 text-emerald-500" />
                                    <Lock v-else class="h-4 w-4 text-amber-500" />
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-foreground">Public Access Link</h4>
                                    <p class="text-xs text-muted-foreground">Publish documentation to the web so anyone can read it.</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="isPublicToggle"
                                    v-model="isPublic"
                                    class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                />
                                <Label for="isPublicToggle" class="text-xs font-semibold cursor-pointer">
                                    {{ isPublic ? 'Publicly Visible' : 'Private Only' }}
                                </Label>
                            </div>
                        </div>

                        <!-- Attached Environment Selector -->
                        <div class="space-y-3 p-4 border rounded-lg bg-muted/20">
                            <div class="flex items-center justify-between flex-wrap gap-4">
                                <div>
                                    <h4 class="text-sm font-bold text-foreground">Attached Environment</h4>
                                    <p class="text-xs text-muted-foreground mt-0.5">Link an environment to resolve dynamic variables inside public documentation URLs and code snippets.</p>
                                </div>
                                <select
                                    id="environmentId"
                                    v-model="environmentId"
                                    class="rounded-md border border-input bg-background px-3 py-1.5 text-xs font-semibold focus:outline-hidden focus:ring-2 focus:ring-primary min-w-[220px] text-foreground"
                                >
                                    <option value="">-- No Environment Attached --</option>
                                    <option
                                        v-for="env in props.environments"
                                        :key="env.id"
                                        :value="env.id"
                                    >
                                        {{ env.name }} ({{ env.variables?.length || 0 }} vars)
                                    </option>
                                </select>
                            </div>

                            <!-- Preview of Attached Variables -->
                            <div v-if="environmentId && props.environments?.find(e => e.id === environmentId)" class="mt-3 pt-3 border-t border-border/60">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: props.environments.find(e => e.id === environmentId)?.color || '#10b981' }"></span>
                                    <span class="text-xs font-bold text-foreground">{{ props.environments.find(e => e.id === environmentId)?.name }} Variables Preview</span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 max-h-[160px] overflow-y-auto pr-1">
                                    <div
                                        v-for="varItem in props.environments.find(e => e.id === environmentId)?.variables || []"
                                        :key="varItem.key"
                                        class="flex items-center justify-between bg-background border px-2.5 py-1.5 rounded-md text-xs font-mono shadow-2xs"
                                    >
                                        <span class="font-bold text-primary truncate mr-2">{{ '{' + varItem.key + '}' }}</span>
                                        <span class="text-muted-foreground truncate max-w-[120px]" :title="varItem.value">{{ varItem.value || 'empty' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Public Slug Customizer -->
                        <div class="space-y-2" v-if="isPublic">
                            <Label for="slug" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Custom Link Slug</Label>
                            <div class="flex rounded-md shadow-xs w-full">
                                <span 
                                    class="inline-flex items-center rounded-l-md border border-r-0 border-input bg-muted px-3 text-sm text-muted-foreground select-none whitespace-nowrap shrink-0"
                                    :title="`/docs/${selectedCollectionId}/`"
                                >
                                    {{ `/docs/${selectedCollectionId}/` }}
                                </span>
                                <Input
                                    id="slug"
                                    v-model="publicSlug"
                                    placeholder="your-api-slug"
                                    class="rounded-l-none"
                                />
                            </div>
                            <p class="text-xs text-muted-foreground">This generates the public URL for sharing this collection's documentation page.</p>
                        </div>

                        <!-- Versioning Input -->
                        <div class="space-y-2 max-w-xs">
                            <Label for="version" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Version Tag</Label>
                            <Input id="version" v-model="version" placeholder="1.0.0" />
                            <p class="text-xs text-muted-foreground">Specify the API specification version (e.g. 1.2.0, v2).</p>
                        </div>

                        <!-- Global Authentication info -->
                        <div class="space-y-2">
                            <Label for="authInfo" class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                Global Authentication Info
                                <span class="text-[10px] bg-muted px-1.5 py-0.5 rounded text-muted-foreground font-semibold font-mono">Markdown Supported</span>
                            </Label>
                            <textarea
                                id="authInfo"
                                v-model="authInfo"
                                rows="5"
                                placeholder="Describe global API Authentication, e.g.:&#10;To authorize with the API, include a `Bearer token` in the `Authorization` header:&#10;&#10;```&#10;Authorization: Bearer <your_jwt_token>&#10;```"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            ></textarea>
                            <div class="text-xs text-muted-foreground">Document headers, parameters, or tokens needed globally.</div>
                        </div>

                        <!-- Auth Preview -->
                        <div v-if="authInfo.trim()" class="border rounded-md p-4 bg-muted/10 mt-2">
                            <h5 class="text-xs font-bold text-muted-foreground uppercase tracking-wider mb-2">Auth Preview</h5>
                            <div class="prose prose-sm dark:prose-invert max-w-none text-sm" v-html="authPreview"></div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Intro Guide Editor -->
                <Card v-if="activeTab === 'intro'" class="shadow-sm">
                    <CardHeader>
                        <CardTitle class="text-lg">Introduction Guide</CardTitle>
                        <CardDescription>Write rich Markdown introductions, tutorials, and summaries to welcome API developers.</CardDescription>
                    </CardHeader>
                    <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Markdown Editor -->
                        <div class="flex flex-col gap-2">
                            <Label for="markdownIntro" class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                                Markdown Content
                                <span class="text-[10px] bg-muted px-1.5 py-0.5 rounded text-muted-foreground font-semibold font-mono">MD Editor</span>
                            </Label>
                            <textarea
                                id="markdownIntro"
                                v-model="markdownIntro"
                                rows="18"
                                placeholder="# Welcome to our API Docs&#10;&#10;Use this API to query requests, manage workspace variables, and fetch analytical metrics instantly.&#10;&#10;## Base URL&#10;&#10;`https://api.yourdomain.com/v1`&#10;&#10;### Getting Started&#10;&#10;- Obtain your client token in Settings&#10;- Send request with Authorization header"
                                class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 font-mono"
                            ></textarea>
                        </div>

                        <!-- Live Markdown Preview -->
                        <div class="flex flex-col gap-2">
                            <Label class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                                Live HTML Preview
                                <span class="text-[10px] bg-primary/10 text-primary px-1.5 py-0.5 rounded font-semibold">Rendered View</span>
                            </Label>
                            <div class="flex-1 min-h-[350px] max-h-[400px] overflow-y-auto rounded-md border p-4 bg-muted/10 prose prose-sm dark:prose-invert max-w-none">
                                <div v-if="markdownIntro.trim()" v-html="introPreview"></div>
                                <div v-else class="text-muted-foreground text-xs italic flex flex-col items-center justify-center h-full">
                                    <FileText class="h-6 w-6 mb-1 text-muted-foreground/40" />
                                    No content yet. Type markdown on the left to see live preview.
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Requests Documentation Manager -->
                <div v-if="activeTab === 'requests'" class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
                    <!-- Requests Sub-sidebar -->
                    <div class="md:col-span-1 bg-card rounded-xl p-3 border border-border flex flex-col gap-2 max-h-[calc(100vh-220px)] sticky top-6">
                        <div class="flex items-center justify-between px-2 mb-1 shrink-0">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Requests list</h4>
                            <span class="text-[10px] bg-muted px-1.5 py-0.5 rounded font-bold">{{ requestsList.length }} items</span>
                        </div>
                        <div class="overflow-y-auto pr-1 space-y-1.5 max-h-[calc(100vh-260px)] min-h-[200px]">
                            <!-- Root Folders -->
                            <DocFolderNode
                                v-for="folder in rootFolders"
                                :key="folder.id"
                                :folder="folder"
                                :folders="activeCollectionFolders"
                                :requests="requestsList"
                                :selected-request-id="selectedRequestId"
                                :get-method-color="getMethodColor"
                                @select-request="id => selectedRequestId = id"
                            />

                            <!-- Root Requests (not inside any folder) -->
                            <button
                                v-for="req in rootRequests"
                                :key="req.id"
                                @click="selectedRequestId = req.id"
                                class="w-full flex items-center gap-2 p-1.5 rounded-md transition-colors text-left group"
                                :class="selectedRequestId === req.id ? 'bg-sidebar-accent font-semibold text-sidebar-accent-foreground border border-border/50 shadow-sm' : 'hover:bg-muted text-muted-foreground'"
                            >
                                <span
                                    class="inline-flex shrink-0 items-center justify-center rounded border px-1 text-[8px] leading-none font-bold uppercase py-0.5 w-10 text-center select-none"
                                    :class="getMethodColor(req.method)"
                                >
                                    {{ req.method }}
                                </span>
                                <span class="text-xs truncate flex-1">{{ req.name }}</span>
                                <ChevronRight class="h-3 w-3 opacity-50 shrink-0 group-hover:opacity-100 transition-opacity" />
                            </button>

                            <div v-if="requestsList.length === 0 && rootFolders.length === 0" class="text-xs text-muted-foreground text-center py-6">
                                No requests found in this collection.
                            </div>
                        </div>
                    </div>

                    <!-- Request Edit Workarea -->
                    <div class="md:col-span-2 flex flex-col gap-6">
                        <Card v-if="selectedRequest" class="shadow-sm">
                            <CardHeader class="pb-4">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex shrink-0 items-center justify-center rounded border px-1.5 py-0.5 text-[9px] leading-none font-bold uppercase select-none"
                                        :class="getMethodColor(selectedRequest.method)"
                                    >
                                        {{ selectedRequest.method }}
                                    </span>
                                    <CardTitle class="text-base truncate">{{ selectedRequest.name }}</CardTitle>
                                </div>
                                <CardDescription class="font-mono text-xs text-foreground/80 break-all select-all mt-1 bg-muted px-2 py-1 rounded">
                                    {{ selectedRequest.url || 'No URL configured' }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-6">
                                <!-- Request Description Markdown -->
                                <div class="space-y-2">
                                    <Label for="reqDesc" class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center justify-between">
                                        Request description
                                        <span class="text-[10px] bg-muted px-1.5 py-0.5 rounded font-mono font-semibold">Markdown Supported</span>
                                    </Label>
                                    <textarea
                                        id="reqDesc"
                                        :value="selectedRequest.description || ''"
                                        @input="updateRequestDescription(($event.target as HTMLTextAreaElement).value)"
                                        rows="6"
                                        placeholder="Add markdown descriptive notes about parameters, headers, or business logic specific to this request endpoint."
                                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    ></textarea>
                                </div>

                                <!-- Response Mock Examples Section -->
                                <div class="space-y-4 pt-4 border-t">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-sm font-bold text-foreground">Response Examples</h4>
                                            <p class="text-xs text-muted-foreground">Add static JSON response mocks representing distinct HTTP status conditions.</p>
                                        </div>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            @click="showAddExample = !showAddExample"
                                            class="gap-1.5"
                                        >
                                            <X v-if="showAddExample" class="h-3.5 w-3.5" />
                                            <Plus v-else class="h-3.5 w-3.5" />
                                            {{ showAddExample ? 'Cancel' : 'Add Example' }}
                                        </Button>
                                    </div>

                                    <!-- Add Example Inline Form -->
                                    <div v-if="showAddExample" class="p-4 border rounded-lg bg-muted/20 space-y-4 animate-in fade-in duration-200">
                                        <h5 class="text-xs font-bold text-foreground uppercase tracking-wider">New Response Example</h5>
                                        
                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="space-y-1">
                                                <Label for="exName" class="text-xs">Example Name</Label>
                                                <Input id="exName" v-model="newExampleName" placeholder="e.g. 200 Success Response" />
                                            </div>
                                            <div class="space-y-1">
                                                <Label for="exStatus" class="text-xs">HTTP Status Code</Label>
                                                <Input id="exStatus" type="number" v-model="newExampleStatus" placeholder="200" />
                                            </div>
                                        </div>

                                        <div class="space-y-1">
                                            <Label for="exBody" class="text-xs">Mock JSON Response Body</Label>
                                            <textarea
                                                id="exBody"
                                                v-model="newExampleBody"
                                                rows="6"
                                                placeholder="JSON response content..."
                                                class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 font-mono text-xs"
                                            ></textarea>
                                        </div>

                                        <Button size="sm" @click="handleAddExample" class="gap-1">
                                            <Check class="h-3.5 w-3.5" />
                                            Save Example
                                        </Button>
                                    </div>

                                    <!-- Examples List -->
                                    <div v-if="selectedRequest.examples && selectedRequest.examples.length > 0" class="space-y-2">
                                        <div
                                            v-for="example in selectedRequest.examples"
                                            :key="example.id"
                                            class="flex items-center justify-between border rounded-lg p-3 bg-muted/10 hover:bg-muted/20 transition-colors"
                                        >
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <span
                                                    class="inline-flex items-center justify-center rounded px-2 py-0.5 text-xs font-bold text-white shrink-0 select-none"
                                                    :class="example.status_code >= 200 && example.status_code < 300 ? 'bg-emerald-500' : 'bg-rose-500'"
                                                >
                                                    {{ example.status_code }}
                                                </span>
                                                <span class="text-sm font-semibold truncate">{{ example.name }}</span>
                                            </div>
                                            <button
                                                @click="handleDeleteExample(example.id)"
                                                class="rounded p-1 text-muted-foreground hover:bg-sidebar-border hover:text-red-500 transition-colors"
                                                title="Delete Example"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </div>

                                    <div v-else class="text-xs italic text-muted-foreground/75 p-6 border border-dashed rounded-lg text-center">
                                        No response examples registered for this request yet. Add one above!
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <div v-else class="flex-1 flex flex-col items-center justify-center border border-dashed rounded-xl p-12 bg-card text-center min-h-[300px]">
                            <Info class="h-8 w-8 mb-2 text-muted-foreground/55 animate-bounce" />
                            <h4 class="text-sm font-bold text-foreground">No Request Selected</h4>
                            <p class="text-xs text-muted-foreground max-w-xs mt-1">Select a request from the sidebar list to document details and response examples.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
