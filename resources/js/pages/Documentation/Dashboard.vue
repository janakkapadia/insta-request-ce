<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    BookOpen,
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
    Info,
    Search,
    ExternalLink,
    X,
    Copy,
    ArrowLeft,
    Image,
    UploadCloud,
} from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import { toast } from 'vue-sonner';
import DocFolderNode from '@/components/Documentation/DocFolderNode.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { parseMarkdown } from '@/lib/markdown';

// Props passed from Inertia controller
const props = defineProps<{
    collections: Array<{
        id: string;
        name: string;
        description: string | null;
        documentation?: {
            id: string;
            collection_id: string;
            is_public: boolean;
            public_slug: string;
            version: string;
            markdown_intro?: string | null;
            auth_info?: string | null;
            settings?: any;
            environment_id?: string | null;
        } | null;
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
        folders?: Array<any>;
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

// Overview state & helpers
const overviewSearch = ref('');
const overviewFilter = ref<'all' | 'public' | 'private'>('all');
const copiedUrlId = ref<string | null>(null);

const filteredOverviewCollections = computed(() => {
    return props.collections.filter((c) => {
        const matchesSearch =
            !overviewSearch.value.trim() ||
            c.name.toLowerCase().includes(overviewSearch.value.toLowerCase()) ||
            (c.documentation?.public_slug || '')
                .toLowerCase()
                .includes(overviewSearch.value.toLowerCase());

        if (!matchesSearch) {
            return false;
        }

        if (overviewFilter.value === 'public') {
            return c.documentation && c.documentation.is_public;
        }

        if (overviewFilter.value === 'private') {
            return !c.documentation || !c.documentation.is_public;
        }

        return true;
    });
});

const totalPublicCount = computed(() => {
    return props.collections.filter(
        (c) => c.documentation && c.documentation.is_public,
    ).length;
});

const totalPrivateCount = computed(() => {
    return props.collections.filter(
        (c) => !c.documentation || !c.documentation.is_public,
    ).length;
});

const getPublicUrl = (col: any) => {
    const origin = typeof window !== 'undefined' ? window.location.origin : '';

    return `${origin}/docs/${col.id}/${col.documentation?.public_slug || ''}`;
};

const currentPublicUrl = computed(() => {
    const origin = typeof window !== 'undefined' ? window.location.origin : '';

    return `${origin}/docs/${selectedCollectionId.value}/${publicSlug.value}`;
});

const currentPublicBaseUrl = computed(() => {
    const origin = typeof window !== 'undefined' ? window.location.origin : '';

    return `${origin}/docs/${selectedCollectionId.value}/`;
});

const copyPublicUrl = (col: any) => {
    if (!col.documentation?.public_slug) {
        return;
    }

    const url = getPublicUrl(col);

    const doCopy = () => {
        copiedUrlId.value = col.id;
        toast.success('Public URL copied to clipboard');
        setTimeout(() => {
            if (copiedUrlId.value === col.id) {
                copiedUrlId.value = null;
            }
        }, 2000);
    };

    if (navigator.clipboard) {
        navigator.clipboard
            .writeText(url)
            .then(doCopy)
            .catch(() => {
                // fallback on clipboard permission denied
                fallbackCopy(url, doCopy);
            });
    } else {
        fallbackCopy(url, doCopy);
    }
};

const fallbackCopy = (text: string, onSuccess: () => void) => {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();

    try {
        document.execCommand('copy');
        onSuccess();
    } catch {
        toast.error('Failed to copy URL');
    } finally {
        document.body.removeChild(textarea);
    }
};

const quickTogglePublic = (col: any) => {
    isLoading.value = true;
    const newStatus = !col.documentation?.is_public;
    router.post(
        `/documentation/collection/${col.id}`,
        {
            is_public: newStatus,
            public_slug:
                col.documentation?.public_slug ||
                `${col.name.toLowerCase().replace(/[^a-z0-9]+/g, '-')}-${Math.random().toString(36).substring(2, 8)}`,
            version: col.documentation?.version || '1.0.0',
            markdown_intro: col.documentation?.markdown_intro || '',
            auth_info: col.documentation?.auth_info || '',
            settings: col.documentation?.settings || {},
            environment_id: col.documentation?.environment_id || null,
        },
        {
            preserveScroll: true,
            onSuccess: () =>
                toast.success(
                    `Documentation is now ${newStatus ? 'Public' : 'Private'}`,
                ),
            onFinish: () => (isLoading.value = false),
        },
    );
};

// State vars
const selectedCollectionId = ref<string>(props.selectedCollectionId || '');
const isLoading = ref<boolean>(false);
const activeTab = ref<'settings' | 'intro' | 'requests' | 'branding'>(
    'settings',
);

const getObjectUrl = (file: File) => {
    return URL.createObjectURL(file);
};

// Documentation details
const docId = ref<string>('');
const isPublic = ref<boolean>(false);
const publicSlug = ref<string>('');
const version = ref<string>('1.0.0');
const markdownIntro = ref<string>('');
const authInfo = ref<string>('');
const docSettings = ref<any>({});
const environmentId = ref<string>('');

const logoFile = ref<File | null>(null);
const faviconFile = ref<File | null>(null);
const removeLogo = ref<boolean>(false);
const removeFavicon = ref<boolean>(false);

const handleLogoUpload = (e: Event) => {
    const target = e.target as HTMLInputElement;

    if (target.files && target.files.length > 0) {
        logoFile.value = target.files[0];
        removeLogo.value = false;
    }
};

const handleFaviconUpload = (e: Event) => {
    const target = e.target as HTMLInputElement;

    if (target.files && target.files.length > 0) {
        faviconFile.value = target.files[0];
        removeFavicon.value = false;
    }
};

const clearLogo = () => {
    logoFile.value = null;
    removeLogo.value = true;
};

const clearFavicon = () => {
    faviconFile.value = null;
    removeFavicon.value = true;
};

// Requests of selected collection with descriptions and mock response examples
const editableRequestsList = ref<Array<any>>([]);
const selectedRequestId = ref<string>('');

// Selected request details
const selectedRequest = computed(() => {
    return (
        editableRequestsList.value.find(
            (r) => r.id === selectedRequestId.value,
        ) || null
    );
});

const activeCollectionFolders = computed(() => {
    if (!selectedCollectionId.value) {
        return [];
    }

    const col = props.collections.find(
        (c) => c.id === selectedCollectionId.value,
    );

    return col?.folders || [];
});

const rootFolders = computed(() => {
    return activeCollectionFolders.value.filter((f: any) => !f.parent_id);
});

const rootRequests = computed(() => {
    return editableRequestsList.value.filter((r) => !r.folder_id);
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
        editableRequestsList.value = [];

        return;
    }

    if (newVal !== props.selectedCollectionId) {
        isLoading.value = true;
        router.get(
            '/documentation',
            { collection_id: newVal },
            {
                preserveState: true,
                preserveScroll: true,
                only: [
                    'documentation',
                    'requestsList',
                    'selectedCollectionId',
                    'environments',
                ],
                onFinish: () => (isLoading.value = false),
            },
        );
    }
});

watch(
    () => props.documentation,
    (newDoc) => {
        if (newDoc) {
            docId.value = newDoc.id;
            isPublic.value = newDoc.is_public;
            publicSlug.value = newDoc.public_slug;
            version.value = newDoc.version;
            markdownIntro.value = newDoc.markdown_intro || '';
            authInfo.value = newDoc.auth_info || '';
            docSettings.value = newDoc.settings || {};
            environmentId.value = newDoc.environment_id || '';

            logoFile.value = null;
            faviconFile.value = null;
            removeLogo.value = false;
            removeFavicon.value = false;
        } else {
            docId.value = '';
            environmentId.value = '';
            logoFile.value = null;
            faviconFile.value = null;
            removeLogo.value = false;
            removeFavicon.value = false;
        }
    },
    { immediate: true },
);

watch(
    () => props.requestsList,
    (newList) => {
        editableRequestsList.value = newList
            ? JSON.parse(JSON.stringify(newList))
            : [];
        editedRequestDescriptions.value = {};

        if (editableRequestsList.value.length > 0) {
            if (
                !editableRequestsList.value.find(
                    (r) => r.id === selectedRequestId.value,
                )
            ) {
                selectedRequestId.value = editableRequestsList.value[0].id;
            }
        } else {
            selectedRequestId.value = '';
        }
    },
    { immediate: true },
);

// Save Documentation
const handleSave = () => {
    if (!selectedCollectionId.value) {
        return;
    }

    isLoading.value = true;

    const formData = new FormData();
    formData.append('is_public', isPublic.value ? '1' : '0');
    formData.append('public_slug', publicSlug.value);
    formData.append('version', version.value);
    formData.append('markdown_intro', markdownIntro.value);
    formData.append('auth_info', authInfo.value);

    if (environmentId.value) {
        formData.append('environment_id', environmentId.value);
    }

    if (logoFile.value) {
        formData.append('logo', logoFile.value);
    }

    if (faviconFile.value) {
        formData.append('favicon', faviconFile.value);
    }

    if (removeLogo.value) {
        formData.append('remove_logo', '1');
    }

    if (removeFavicon.value) {
        formData.append('remove_favicon', '1');
    }

    // Add nested arrays/objects properly for formData
    Object.keys(docSettings.value).forEach((key) => {
        formData.append(`settings[${key}]`, docSettings.value[key]);
    });

    Object.keys(editedRequestDescriptions.value).forEach((key) => {
        formData.append(
            `requests_descriptions[${key}]`,
            editedRequestDescriptions.value[key],
        );
    });

    router.post(
        `/documentation/collection/${selectedCollectionId.value}`,
        formData,
        {
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
            onFinish: () => (isLoading.value = false),
        },
    );
};

// Add Mock Response Example
const handleAddExample = () => {
    if (!selectedRequestId.value) {
        return;
    }

    router.post(
        `/documentation/request/${selectedRequestId.value}/response-examples`,
        {
            name: newExampleName.value,
            status_code: newExampleStatus.value,
            body: newExampleBody.value,
            headers: { 'Content-Type': 'application/json' },
        },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Response example added!');
                newExampleName.value = '200 OK Success';
                newExampleStatus.value = 200;
                newExampleBody.value = '{\n  "status": "success"\n}';
                showAddExample.value = false;
            },
            onError: () => toast.error('Failed to add example'),
        },
    );
};

// Delete Response Example
const handleDeleteExample = (exampleId: string) => {
    router.delete(`/documentation/response-examples/${exampleId}`, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => toast.success('Example deleted successfully'),
        onError: () => toast.error('Failed to delete example'),
    });
};

// Track modified request description
const updateRequestDescription = (desc: string) => {
    if (!selectedRequestId.value) {
        return;
    }

    editedRequestDescriptions.value[selectedRequestId.value] = desc;

    // Also keep local reactive object updated
    const req = editableRequestsList.value.find(
        (r) => r.id === selectedRequestId.value,
    );

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
        <div
            class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
        >
            <div>
                <h1
                    class="flex items-center gap-2 text-2xl font-extrabold tracking-tight text-foreground"
                >
                    <BookOpen class="h-6 w-6 text-primary" />
                    API Documentation Generator
                </h1>
                <p class="text-sm text-muted-foreground">
                    Generate beautiful, interactive, shareable public or private
                    documentation from collections.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <Button
                    v-if="selectedCollectionId"
                    variant="outline"
                    @click="selectedCollectionId = ''"
                    class="h-9 cursor-pointer gap-1.5 text-xs"
                >
                    <ArrowLeft class="h-3.5 w-3.5" />
                    All Docs Hub
                </Button>

                <select
                    v-model="selectedCollectionId"
                    class="h-9 w-64 rounded-md border border-input bg-background px-3 py-1.5 text-sm ring-offset-background focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-hidden"
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

        <!-- All Documentation & Public Portals Hub -->
        <div v-if="!selectedCollectionId" class="flex flex-1 flex-col gap-6">
            <!-- Stats & Quick Actions Banner -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Card class="border border-border bg-card shadow-xs">
                    <CardContent class="flex items-center justify-between p-4">
                        <div>
                            <p
                                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Total Collections
                            </p>
                            <h3
                                class="mt-1 text-2xl font-extrabold text-foreground"
                            >
                                {{ props.collections.length }}
                            </h3>
                        </div>
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary"
                        >
                            <BookOpen class="h-5 w-5" />
                        </div>
                    </CardContent>
                </Card>

                <Card class="border border-border bg-card shadow-xs">
                    <CardContent class="flex items-center justify-between p-4">
                        <div>
                            <p
                                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Public Portals Active
                            </p>
                            <h3
                                class="mt-1 text-2xl font-extrabold text-emerald-600 dark:text-emerald-400"
                            >
                                {{ totalPublicCount }}
                            </h3>
                        </div>
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                        >
                            <Globe class="h-5 w-5" />
                        </div>
                    </CardContent>
                </Card>

                <Card class="border border-border bg-card shadow-xs">
                    <CardContent class="flex items-center justify-between p-4">
                        <div>
                            <p
                                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Private / Draft Docs
                            </p>
                            <h3
                                class="mt-1 text-2xl font-extrabold text-muted-foreground"
                            >
                                {{ totalPrivateCount }}
                            </h3>
                        </div>
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-muted text-muted-foreground"
                        >
                            <Lock class="h-5 w-5" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Search & Filters -->
            <div
                class="flex flex-col items-stretch justify-between gap-4 rounded-xl border border-border bg-card p-4 shadow-2xs sm:flex-row sm:items-center"
            >
                <div class="relative max-w-md flex-1">
                    <Search
                        class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground"
                    />
                    <Input
                        v-model="overviewSearch"
                        placeholder="Search documentation or public slug..."
                        class="h-9 pl-9 text-xs"
                    />
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div
                        class="flex items-center rounded-lg bg-muted p-0.5 text-xs font-semibold"
                    >
                        <button
                            @click="overviewFilter = 'all'"
                            class="cursor-pointer rounded-md px-3 py-1 transition-all"
                            :class="
                                overviewFilter === 'all'
                                    ? 'bg-background text-foreground shadow-2xs'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                        >
                            All ({{ props.collections.length }})
                        </button>
                        <button
                            @click="overviewFilter = 'public'"
                            class="flex cursor-pointer items-center gap-1 rounded-md px-3 py-1 transition-all"
                            :class="
                                overviewFilter === 'public'
                                    ? 'bg-background text-foreground shadow-2xs'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                        >
                            <Globe class="h-3 w-3 text-emerald-500" />
                            Public ({{ totalPublicCount }})
                        </button>
                        <button
                            @click="overviewFilter = 'private'"
                            class="flex cursor-pointer items-center gap-1 rounded-md px-3 py-1 transition-all"
                            :class="
                                overviewFilter === 'private'
                                    ? 'bg-background text-foreground shadow-2xs'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                        >
                            <Lock class="h-3 w-3 text-muted-foreground" />
                            Private ({{ totalPrivateCount }})
                        </button>
                    </div>

                    <a
                        v-if="totalPublicCount > 0"
                        href="/docs"
                        target="_blank"
                        class="ml-1 inline-flex items-center gap-1.5 rounded-lg border border-emerald-500/30 bg-emerald-500/15 px-3 py-1.5 text-xs font-semibold text-emerald-600 transition-all hover:bg-emerald-500/25 dark:text-emerald-400"
                    >
                        <Globe class="h-3.5 w-3.5" />
                        Live Public Portal
                        <ExternalLink class="h-3 w-3 opacity-70" />
                    </a>
                </div>
            </div>

            <!-- Collections Overview Grid -->
            <div
                v-if="filteredOverviewCollections.length === 0"
                class="flex min-h-[300px] flex-col items-center justify-center rounded-xl border border-dashed bg-card p-12 text-center"
            >
                <div class="mb-3 rounded-full bg-muted p-4">
                    <Search class="h-8 w-8 text-muted-foreground" />
                </div>
                <h3 class="text-base font-bold text-foreground">
                    No matching documentation collections
                </h3>
                <p class="mt-1 max-w-sm text-xs text-muted-foreground">
                    No collections match your current search query or status
                    filter. Try resetting your filters.
                </p>
                <Button
                    variant="outline"
                    size="sm"
                    @click="
                        overviewSearch = '';
                        overviewFilter = 'all';
                    "
                    class="mt-4 text-xs"
                >
                    Reset Filters
                </Button>
            </div>

            <div
                v-else
                class="flex flex-col divide-y divide-border overflow-hidden rounded-xl border border-border bg-card shadow-xs"
            >
                <div
                    v-for="col in filteredOverviewCollections"
                    :key="col.id"
                    class="group flex items-center gap-4 px-5 py-4 transition-colors hover:bg-muted/30"
                >
                    <!-- Icon + Name + Description -->
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <BookOpen class="h-4 w-4" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="truncate text-sm font-bold text-foreground"
                                >{{ col.name }}</span
                            >
                            <!-- Public / Private Badge -->
                            <span
                                v-if="
                                    col.documentation &&
                                    col.documentation.is_public
                                "
                                class="inline-flex items-center gap-1 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-600 dark:text-emerald-400"
                            >
                                <span
                                    class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"
                                ></span>
                                Public Portal · v{{ col.documentation.version }}
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-full border border-border bg-muted px-2 py-0.5 text-[10px] font-semibold text-muted-foreground"
                            >
                                <Lock class="h-2.5 w-2.5" />
                                Private / Draft
                            </span>
                            <!-- Endpoint Count -->
                            <span
                                class="rounded-full border border-border bg-muted px-2 py-0.5 font-mono text-[10px] font-bold text-muted-foreground"
                            >
                                {{ col.requests?.length || 0 }} Endpoints
                            </span>
                        </div>
                        <p
                            class="mt-0.5 truncate text-xs text-muted-foreground"
                        >
                            {{ col.description || 'No description provided' }}
                        </p>
                        <!-- Public URL (shown when public) -->
                        <div
                            v-if="
                                col.documentation && col.documentation.is_public
                            "
                            class="mt-1.5 flex items-center gap-1.5 font-mono text-[11px] text-muted-foreground"
                        >
                            <Globe class="h-3 w-3 shrink-0 text-emerald-500" />
                            <span class="truncate">{{
                                getPublicUrl(col)
                            }}</span>
                            <button
                                @click="copyPublicUrl(col)"
                                class="shrink-0 cursor-pointer rounded p-0.5 transition-colors hover:text-foreground"
                                title="Copy Public URL"
                            >
                                <Check
                                    v-if="copiedUrlId === col.id"
                                    class="h-3 w-3 text-emerald-500"
                                />
                                <Copy v-else class="h-3 w-3" />
                            </button>
                            <a
                                :href="
                                    '/docs/' +
                                    col.id +
                                    '/' +
                                    col.documentation.public_slug
                                "
                                target="_blank"
                                class="shrink-0 rounded p-0.5 transition-colors hover:text-primary"
                                title="Open Public Portal"
                            >
                                <ExternalLink class="h-3 w-3" />
                            </a>
                        </div>
                        <div
                            v-else
                            class="mt-1.5 flex items-center gap-2 text-[11px] text-muted-foreground"
                        >
                            <span>Not published to public portal</span>
                            <button
                                @click="quickTogglePublic(col)"
                                :disabled="isLoading"
                                class="cursor-pointer font-bold text-primary hover:underline"
                            >
                                Quick Publish
                            </button>
                        </div>
                    </div>

                    <!-- Actions (right-aligned) -->
                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            @click="quickTogglePublic(col)"
                            :disabled="isLoading"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition-all"
                            :class="
                                col.documentation && col.documentation.is_public
                                    ? 'border-rose-500/20 bg-rose-500/10 text-rose-600 hover:bg-rose-500/20 dark:text-rose-400'
                                    : 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20 dark:text-emerald-400'
                            "
                        >
                            <Lock
                                v-if="
                                    col.documentation &&
                                    col.documentation.is_public
                                "
                                class="h-3 w-3"
                            />
                            <Globe v-else class="h-3 w-3" />
                            {{
                                col.documentation && col.documentation.is_public
                                    ? 'Make Private'
                                    : 'Make Public'
                            }}
                        </button>

                        <Button
                            @click="selectedCollectionId = col.id"
                            size="sm"
                            variant="outline"
                            class="h-8 cursor-pointer gap-1.5 text-xs"
                        >
                            <Settings class="h-3.5 w-3.5" />
                            Configure & Edit
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Selected Collection Panel -->
        <div
            v-else
            class="grid flex-1 grid-cols-1 items-start gap-6 lg:grid-cols-4"
        >
            <!-- Tabs Sidebar -->
            <div
                class="flex flex-col gap-2 rounded-xl border border-border bg-card p-3 shadow-sm lg:col-span-1"
            >
                <button
                    @click="activeTab = 'settings'"
                    class="flex items-center gap-2.5 rounded-md px-3 py-2 text-left text-sm font-semibold transition-colors"
                    :class="
                        activeTab === 'settings'
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted'
                    "
                >
                    <Settings class="h-4 w-4" />
                    General Settings
                </button>
                <button
                    @click="activeTab = 'branding'"
                    class="flex items-center gap-2.5 rounded-md px-3 py-2 text-left text-sm font-semibold transition-colors"
                    :class="
                        activeTab === 'branding'
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted'
                    "
                >
                    <Image class="h-4 w-4" />
                    Branding & Auth
                </button>
                <button
                    @click="activeTab = 'intro'"
                    class="flex items-center gap-2.5 rounded-md px-3 py-2 text-left text-sm font-semibold transition-colors"
                    :class="
                        activeTab === 'intro'
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted'
                    "
                >
                    <FileText class="h-4 w-4" />
                    Introduction Guide
                </button>
                <button
                    @click="activeTab = 'requests'"
                    class="flex items-center gap-2.5 rounded-md px-3 py-2 text-left text-sm font-semibold transition-colors"
                    :class="
                        activeTab === 'requests'
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted'
                    "
                >
                    <Play class="h-4 w-4" />
                    Request Parameters
                </button>

                <div
                    class="mt-4 border-t px-2 pt-4"
                    v-if="isPublic && publicSlug"
                >
                    <a
                        :href="currentPublicUrl"
                        target="_blank"
                        class="inline-flex w-full items-center justify-center gap-1.5 rounded-md bg-emerald-500/10 px-3 py-2 text-xs font-bold text-emerald-500 transition-colors hover:bg-emerald-500/20"
                    >
                        <ExternalLink class="h-3.5 w-3.5" />
                        View Live Docs portal
                    </a>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="flex flex-col gap-6 lg:col-span-3">
                <!-- General Settings -->
                <Card v-if="activeTab === 'settings'" class="shadow-sm">
                    <CardHeader>
                        <CardTitle class="text-lg">General Settings</CardTitle>
                        <CardDescription
                            >Configure path visibility, shareable link settings,
                            version logs, and global auth
                            variables.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Portal Visibility Toggle -->
                        <div
                            class="flex items-center justify-between rounded-lg border bg-muted/20 p-4"
                        >
                            <div class="flex items-start gap-3">
                                <div
                                    class="mt-0.5 rounded-full bg-primary/10 p-2"
                                >
                                    <Globe
                                        v-if="isPublic"
                                        class="h-4 w-4 text-emerald-500"
                                    />
                                    <Lock
                                        v-else
                                        class="h-4 w-4 text-amber-500"
                                    />
                                </div>
                                <div>
                                    <h4
                                        class="text-sm font-bold text-foreground"
                                    >
                                        Public Access Link
                                    </h4>
                                    <p class="text-xs text-muted-foreground">
                                        Publish documentation to the web so
                                        anyone can read it.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="isPublicToggle"
                                    v-model="isPublic"
                                    class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                />
                                <Label
                                    for="isPublicToggle"
                                    class="cursor-pointer text-xs font-semibold"
                                >
                                    {{
                                        isPublic
                                            ? 'Publicly Visible'
                                            : 'Private Only'
                                    }}
                                </Label>
                            </div>
                        </div>

                        <!-- Attached Environment Selector -->
                        <div
                            class="space-y-3 rounded-lg border bg-muted/20 p-4"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-4"
                            >
                                <div>
                                    <h4
                                        class="text-sm font-bold text-foreground"
                                    >
                                        Attached Environment
                                    </h4>
                                    <p
                                        class="mt-0.5 text-xs text-muted-foreground"
                                    >
                                        Link an environment to resolve dynamic
                                        variables inside public documentation
                                        URLs and code snippets.
                                    </p>
                                </div>
                                <select
                                    id="environmentId"
                                    v-model="environmentId"
                                    class="min-w-[220px] rounded-md border border-input bg-background px-3 py-1.5 text-xs font-semibold text-foreground focus:ring-2 focus:ring-primary focus:outline-hidden"
                                >
                                    <option value="">
                                        -- No Environment Attached --
                                    </option>
                                    <option
                                        v-for="env in props.environments"
                                        :key="env.id"
                                        :value="env.id"
                                    >
                                        {{ env.name }} ({{
                                            env.variables?.length || 0
                                        }}
                                        vars)
                                    </option>
                                </select>
                            </div>

                            <!-- Preview of Attached Variables -->
                            <div
                                v-if="
                                    environmentId &&
                                    props.environments?.find(
                                        (e) => e.id === environmentId,
                                    )
                                "
                                class="mt-3 border-t border-border/60 pt-3"
                            >
                                <div class="mb-2 flex items-center gap-2">
                                    <span
                                        class="h-2.5 w-2.5 rounded-full"
                                        :style="{
                                            backgroundColor:
                                                props.environments.find(
                                                    (e) =>
                                                        e.id === environmentId,
                                                )?.color || '#10b981',
                                        }"
                                    ></span>
                                    <span
                                        class="text-xs font-bold text-foreground"
                                        >{{
                                            props.environments.find(
                                                (e) => e.id === environmentId,
                                            )?.name
                                        }}
                                        Variables Preview</span
                                    >
                                </div>
                                <div
                                    class="grid max-h-[160px] grid-cols-1 gap-2 overflow-y-auto pr-1 sm:grid-cols-2 md:grid-cols-3"
                                >
                                    <div
                                        v-for="varItem in props.environments.find(
                                            (e) => e.id === environmentId,
                                        )?.variables || []"
                                        :key="varItem.key"
                                        class="flex items-center justify-between rounded-md border bg-background px-2.5 py-1.5 font-mono text-xs shadow-2xs"
                                    >
                                        <span
                                            class="mr-2 truncate font-bold text-primary"
                                            >{{ '{' + varItem.key + '}' }}</span
                                        >
                                        <span
                                            class="max-w-[120px] truncate text-muted-foreground"
                                            :title="varItem.value"
                                            >{{
                                                varItem.value || 'empty'
                                            }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Public Slug Customizer -->
                        <div class="space-y-2" v-if="isPublic">
                            <Label
                                for="slug"
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                >Custom Slug</Label
                            >
                            <div class="flex w-full rounded-md shadow-xs">
                                <span
                                    class="inline-flex shrink-0 items-center rounded-l-md border border-r-0 border-input bg-muted px-3 text-sm whitespace-nowrap text-muted-foreground select-none"
                                    :title="currentPublicBaseUrl"
                                >
                                    {{ currentPublicBaseUrl }}
                                </span>
                                <Input
                                    id="slug"
                                    v-model="publicSlug"
                                    placeholder="your-api-slug"
                                    class="rounded-l-none"
                                />
                            </div>
                            <p class="text-xs text-muted-foreground">
                                This generates the public URL for sharing this
                                collection's documentation page.
                            </p>
                        </div>

                        <!-- Versioning Input -->
                        <div class="max-w-xs space-y-2">
                            <Label
                                for="version"
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                >Version Tag</Label
                            >
                            <Input
                                id="version"
                                v-model="version"
                                placeholder="1.0.0"
                            />
                            <p class="text-xs text-muted-foreground">
                                Specify the API specification version (e.g.
                                1.2.0, v2).
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Branding & Auth Settings -->
                <Card v-if="activeTab === 'branding'" class="shadow-sm">
                    <CardHeader>
                        <CardTitle class="text-lg"
                            >Branding & Auth Settings</CardTitle
                        >
                        <CardDescription
                            >Customize the look and feel of your public
                            documentation portal and setup global
                            authentication.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-8">
                        <!-- Logo and Favicon Settings -->
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <!-- Custom Logo -->
                            <div class="space-y-3">
                                <Label
                                    class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    <Image class="h-3.5 w-3.5" />
                                    Custom Logo
                                </Label>
                                <div class="flex items-start gap-4">
                                    <div
                                        class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-muted/50"
                                    >
                                        <img
                                            v-if="logoFile"
                                            :src="getObjectUrl(logoFile)"
                                            class="h-full w-full object-contain p-1"
                                        />
                                        <img
                                            v-else-if="
                                                !removeLogo &&
                                                docSettings?.logo_path
                                            "
                                            :src="`/storage/${docSettings.logo_path}`"
                                            class="h-full w-full object-contain p-1"
                                        />
                                        <Image
                                            v-else
                                            class="h-6 w-6 text-muted-foreground"
                                        />
                                    </div>
                                    <div class="flex-1 space-y-2">
                                        <div class="flex items-center gap-2">
                                            <Label
                                                for="logo-upload"
                                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-md bg-secondary px-3 py-1.5 text-xs font-semibold text-secondary-foreground transition-colors hover:bg-secondary/80"
                                            >
                                                <UploadCloud
                                                    class="h-3.5 w-3.5"
                                                />
                                                Upload Logo
                                            </Label>
                                            <input
                                                id="logo-upload"
                                                type="file"
                                                accept="image/*"
                                                class="hidden"
                                                @change="handleLogoUpload"
                                            />

                                            <button
                                                v-if="
                                                    logoFile ||
                                                    (!removeLogo &&
                                                        docSettings?.logo_path)
                                                "
                                                @click="clearLogo"
                                                class="text-xs font-semibold text-destructive hover:underline"
                                            >
                                                Remove
                                            </button>
                                        </div>
                                        <p
                                            class="text-[11px] text-muted-foreground"
                                        >
                                            Recommended: Transparent PNG/SVG,
                                            max height 64px, up to 5MB.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Custom Favicon -->
                            <div class="space-y-3">
                                <Label
                                    class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    <Globe class="h-3.5 w-3.5" />
                                    Custom Favicon
                                </Label>
                                <div class="flex items-start gap-4">
                                    <div
                                        class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-md border border-border bg-muted/50"
                                    >
                                        <img
                                            v-if="faviconFile"
                                            :src="getObjectUrl(faviconFile)"
                                            class="h-full w-full object-contain p-1"
                                        />
                                        <img
                                            v-else-if="
                                                !removeFavicon &&
                                                docSettings?.favicon_path
                                            "
                                            :src="`/storage/${docSettings.favicon_path}`"
                                            class="h-full w-full object-contain p-1"
                                        />
                                        <Globe
                                            v-else
                                            class="h-5 w-5 text-muted-foreground"
                                        />
                                    </div>
                                    <div class="flex-1 space-y-2">
                                        <div class="flex items-center gap-2">
                                            <Label
                                                for="favicon-upload"
                                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-md bg-secondary px-3 py-1.5 text-xs font-semibold text-secondary-foreground transition-colors hover:bg-secondary/80"
                                            >
                                                <UploadCloud
                                                    class="h-3.5 w-3.5"
                                                />
                                                Upload Favicon
                                            </Label>
                                            <input
                                                id="favicon-upload"
                                                type="file"
                                                accept=".ico,.png,.svg"
                                                class="hidden"
                                                @change="handleFaviconUpload"
                                            />

                                            <button
                                                v-if="
                                                    faviconFile ||
                                                    (!removeFavicon &&
                                                        docSettings?.favicon_path)
                                                "
                                                @click="clearFavicon"
                                                class="text-xs font-semibold text-destructive hover:underline"
                                            >
                                                Remove
                                            </button>
                                        </div>
                                        <p
                                            class="text-[11px] text-muted-foreground"
                                        >
                                            Recommended: 32x32 ICO or PNG, up to
                                            1MB.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Global Authentication info -->
                        <div class="space-y-2 border-t border-border pt-6">
                            <Label
                                for="authInfo"
                                class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Global Authentication Info
                                <span
                                    class="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] font-semibold text-muted-foreground"
                                    >Markdown Supported</span
                                >
                            </Label>
                            <textarea
                                id="authInfo"
                                v-model="authInfo"
                                rows="5"
                                placeholder="Describe global API Authentication, e.g.:&#10;To authorize with the API, include a `Bearer token` in the `Authorization` header:&#10;&#10;```&#10;Authorization: Bearer <your_jwt_token>&#10;```"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden disabled:cursor-not-allowed disabled:opacity-50"
                            ></textarea>
                            <div class="text-xs text-muted-foreground">
                                Document headers, parameters, or tokens needed
                                globally.
                            </div>
                        </div>

                        <!-- Auth Preview -->
                        <div
                            v-if="authInfo.trim()"
                            class="mt-2 rounded-md border bg-muted/10 p-4"
                        >
                            <h5
                                class="mb-2 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Auth Preview
                            </h5>
                            <div
                                class="prose prose-sm dark:prose-invert max-w-none text-sm"
                                v-html="authPreview"
                            ></div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Intro Guide Editor -->
                <Card v-if="activeTab === 'intro'" class="shadow-sm">
                    <CardHeader>
                        <CardTitle class="text-lg"
                            >Introduction Guide</CardTitle
                        >
                        <CardDescription
                            >Write rich Markdown introductions, tutorials, and
                            summaries to welcome API
                            developers.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Markdown Editor -->
                        <div class="flex flex-col gap-2">
                            <Label
                                for="markdownIntro"
                                class="flex items-center justify-between text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Markdown Content
                                <span
                                    class="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] font-semibold text-muted-foreground"
                                    >MD Editor</span
                                >
                            </Label>
                            <textarea
                                id="markdownIntro"
                                v-model="markdownIntro"
                                rows="18"
                                placeholder="# Welcome to our API Docs&#10;&#10;Use this API to query requests, manage workspace variables, and fetch analytical metrics instantly.&#10;&#10;## Base URL&#10;&#10;`https://api.yourdomain.com/v1`&#10;&#10;### Getting Started&#10;&#10;- Obtain your client token in Settings&#10;- Send request with Authorization header"
                                class="flex w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden"
                            ></textarea>
                        </div>

                        <!-- Live Markdown Preview -->
                        <div class="flex flex-col gap-2">
                            <Label
                                class="flex items-center justify-between text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Live HTML Preview
                                <span
                                    class="rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-semibold text-primary"
                                    >Rendered View</span
                                >
                            </Label>
                            <div
                                class="prose prose-sm dark:prose-invert max-h-[400px] min-h-[350px] max-w-none flex-1 overflow-y-auto rounded-md border bg-muted/10 p-4"
                            >
                                <div
                                    v-if="markdownIntro.trim()"
                                    v-html="introPreview"
                                ></div>
                                <div
                                    v-else
                                    class="flex h-full flex-col items-center justify-center text-xs text-muted-foreground italic"
                                >
                                    <FileText
                                        class="mb-1 h-6 w-6 text-muted-foreground/40"
                                    />
                                    No content yet. Type markdown on the left to
                                    see live preview.
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Requests Documentation Manager -->
                <div
                    v-if="activeTab === 'requests'"
                    class="grid grid-cols-1 items-start gap-6 md:grid-cols-3"
                >
                    <!-- Requests Sub-sidebar -->
                    <div
                        class="sticky top-6 flex max-h-[calc(100vh-220px)] flex-col gap-2 rounded-xl border border-border bg-card p-3 md:col-span-1"
                    >
                        <div
                            class="mb-1 flex shrink-0 items-center justify-between px-2"
                        >
                            <h4
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Requests list
                            </h4>
                            <span
                                class="rounded bg-muted px-1.5 py-0.5 text-[10px] font-bold"
                                >{{ editableRequestsList.length }} items</span
                            >
                        </div>
                        <div
                            class="max-h-[calc(100vh-260px)] min-h-[200px] space-y-1.5 overflow-y-auto pr-1"
                        >
                            <!-- Root Folders -->
                            <DocFolderNode
                                v-for="folder in rootFolders"
                                :key="folder.id"
                                :folder="folder"
                                :folders="activeCollectionFolders"
                                :requests="editableRequestsList"
                                :selected-request-id="selectedRequestId"
                                :get-method-color="getMethodColor"
                                @select-request="
                                    (id) => (selectedRequestId = id)
                                "
                            />

                            <!-- Root Requests (not inside any folder) -->
                            <button
                                v-for="req in rootRequests"
                                :key="req.id"
                                @click="selectedRequestId = req.id"
                                class="group flex w-full items-center gap-2 rounded-md p-1.5 text-left transition-colors"
                                :class="
                                    selectedRequestId === req.id
                                        ? 'border border-border/50 bg-sidebar-accent font-semibold text-sidebar-accent-foreground shadow-sm'
                                        : 'text-muted-foreground hover:bg-muted'
                                "
                            >
                                <span
                                    class="inline-flex w-10 shrink-0 items-center justify-center rounded border px-1 py-0.5 text-center text-[8px] leading-none font-bold uppercase select-none"
                                    :class="getMethodColor(req.method)"
                                >
                                    {{ req.method }}
                                </span>
                                <span class="flex-1 truncate text-xs">{{
                                    req.name
                                }}</span>
                                <ChevronRight
                                    class="h-3 w-3 shrink-0 opacity-50 transition-opacity group-hover:opacity-100"
                                />
                            </button>

                            <div
                                v-if="
                                    editableRequestsList.length === 0 &&
                                    rootFolders.length === 0
                                "
                                class="py-6 text-center text-xs text-muted-foreground"
                            >
                                No requests found in this collection.
                            </div>
                        </div>
                    </div>

                    <!-- Request Edit Workarea -->
                    <div class="flex flex-col gap-6 md:col-span-2">
                        <Card v-if="selectedRequest" class="shadow-sm">
                            <CardHeader class="pb-4">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex shrink-0 items-center justify-center rounded border px-1.5 py-0.5 text-[9px] leading-none font-bold uppercase select-none"
                                        :class="
                                            getMethodColor(
                                                selectedRequest.method,
                                            )
                                        "
                                    >
                                        {{ selectedRequest.method }}
                                    </span>
                                    <CardTitle class="truncate text-base">{{
                                        selectedRequest.name
                                    }}</CardTitle>
                                </div>
                                <CardDescription
                                    class="mt-1 rounded bg-muted px-2 py-1 font-mono text-xs break-all text-foreground/80 select-all"
                                >
                                    {{
                                        selectedRequest.url ||
                                        'No URL configured'
                                    }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-6">
                                <!-- Request Description Markdown -->
                                <div class="space-y-2">
                                    <Label
                                        for="reqDesc"
                                        class="flex items-center justify-between text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                    >
                                        Request description
                                        <span
                                            class="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] font-semibold"
                                            >Markdown Supported</span
                                        >
                                    </Label>
                                    <textarea
                                        id="reqDesc"
                                        :value="
                                            selectedRequest.description || ''
                                        "
                                        @input="
                                            updateRequestDescription(
                                                (
                                                    $event.target as HTMLTextAreaElement
                                                ).value,
                                            )
                                        "
                                        rows="6"
                                        placeholder="Add markdown descriptive notes about parameters, headers, or business logic specific to this request endpoint."
                                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden"
                                    ></textarea>
                                </div>

                                <!-- Response Mock Examples Section -->
                                <div class="space-y-4 border-t pt-4">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div>
                                            <h4
                                                class="text-sm font-bold text-foreground"
                                            >
                                                Response Examples
                                            </h4>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                Add static JSON response mocks
                                                representing distinct HTTP
                                                status conditions.
                                            </p>
                                        </div>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            @click="
                                                showAddExample = !showAddExample
                                            "
                                            class="gap-1.5"
                                        >
                                            <X
                                                v-if="showAddExample"
                                                class="h-3.5 w-3.5"
                                            />
                                            <Plus v-else class="h-3.5 w-3.5" />
                                            {{
                                                showAddExample
                                                    ? 'Cancel'
                                                    : 'Add Example'
                                            }}
                                        </Button>
                                    </div>

                                    <!-- Add Example Inline Form -->
                                    <div
                                        v-if="showAddExample"
                                        class="animate-in space-y-4 rounded-lg border bg-muted/20 p-4 duration-200 fade-in"
                                    >
                                        <h5
                                            class="text-xs font-bold tracking-wider text-foreground uppercase"
                                        >
                                            New Response Example
                                        </h5>

                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="space-y-1">
                                                <Label
                                                    for="exName"
                                                    class="text-xs"
                                                    >Example Name</Label
                                                >
                                                <Input
                                                    id="exName"
                                                    v-model="newExampleName"
                                                    placeholder="e.g. 200 Success Response"
                                                />
                                            </div>
                                            <div class="space-y-1">
                                                <Label
                                                    for="exStatus"
                                                    class="text-xs"
                                                    >HTTP Status Code</Label
                                                >
                                                <Input
                                                    id="exStatus"
                                                    type="number"
                                                    v-model="newExampleStatus"
                                                    placeholder="200"
                                                />
                                            </div>
                                        </div>

                                        <div class="space-y-1">
                                            <Label for="exBody" class="text-xs"
                                                >Mock JSON Response Body</Label
                                            >
                                            <textarea
                                                id="exBody"
                                                v-model="newExampleBody"
                                                rows="6"
                                                placeholder="JSON response content..."
                                                class="flex w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm text-xs ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden"
                                            ></textarea>
                                        </div>

                                        <Button
                                            size="sm"
                                            @click="handleAddExample"
                                            class="gap-1"
                                        >
                                            <Check class="h-3.5 w-3.5" />
                                            Save Example
                                        </Button>
                                    </div>

                                    <!-- Examples List -->
                                    <div
                                        v-if="
                                            selectedRequest.examples &&
                                            selectedRequest.examples.length > 0
                                        "
                                        class="space-y-2"
                                    >
                                        <div
                                            v-for="example in selectedRequest.examples"
                                            :key="example.id"
                                            class="flex items-center justify-between rounded-lg border bg-muted/10 p-3 transition-colors hover:bg-muted/20"
                                        >
                                            <div
                                                class="flex min-w-0 items-center gap-2.5"
                                            >
                                                <span
                                                    class="inline-flex shrink-0 items-center justify-center rounded px-2 py-0.5 text-xs font-bold text-white select-none"
                                                    :class="
                                                        example.status_code >=
                                                            200 &&
                                                        example.status_code <
                                                            300
                                                            ? 'bg-emerald-500'
                                                            : 'bg-rose-500'
                                                    "
                                                >
                                                    {{ example.status_code }}
                                                </span>
                                                <span
                                                    class="truncate text-sm font-semibold"
                                                    >{{ example.name }}</span
                                                >
                                            </div>
                                            <button
                                                @click="
                                                    handleDeleteExample(
                                                        example.id,
                                                    )
                                                "
                                                class="rounded p-1 text-muted-foreground transition-colors hover:bg-sidebar-border hover:text-red-500"
                                                title="Delete Example"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </div>

                                    <div
                                        v-else
                                        class="rounded-lg border border-dashed p-6 text-center text-xs text-muted-foreground/75 italic"
                                    >
                                        No response examples registered for this
                                        request yet. Add one above!
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <div
                            v-else
                            class="flex min-h-[300px] flex-1 flex-col items-center justify-center rounded-xl border border-dashed bg-card p-12 text-center"
                        >
                            <Info
                                class="mb-2 h-8 w-8 animate-bounce text-muted-foreground/55"
                            />
                            <h4 class="text-sm font-bold text-foreground">
                                No Request Selected
                            </h4>
                            <p
                                class="mt-1 max-w-xs text-xs text-muted-foreground"
                            >
                                Select a request from the sidebar list to
                                document details and response examples.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
