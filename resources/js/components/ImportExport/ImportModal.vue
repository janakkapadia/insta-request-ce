<script setup lang="ts">
import { router } from '@inertiajs/vue3';

import {
    Upload,
    FileJson,
    FolderOpen,
    ChevronRight,
    Loader2,
    CheckCircle2,
    XCircle,
    ArrowLeft,
    ArrowRight,
    Zap,
    GitMerge,
    SkipForward,
    Plus,
    Globe,
    Link as LinkIcon,
    Minus,
    Check,
    ChevronDown,
    AlertTriangle,
    RefreshCw,
    Trash2,
} from 'lucide-vue-next';
import { ref, reactive, computed, watch, nextTick } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useWorkspaceStore } from '@/stores/workspace';
import type {
    ImportFormat,
    ImportPreview,
    ImportRecord,
    MergeStrategy,
    ConflictItem,
    ParsedFolder,
} from '@/types';
import FormatBadge from './FormatBadge.vue';
import ValidationReport from './ValidationReport.vue';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
    (e: 'imported'): void;
}>();

const store = useWorkspaceStore();

const fileInput = ref<HTMLInputElement | null>(null);

watch(
    () => props.open,
    (newVal) => {
        if (newVal) {
            reset();
        }
    },
);

const scrollContainer = ref<HTMLElement | null>(null);

const scrollToTop = () => {
    nextTick(() => {
        if (scrollContainer.value) {
            scrollContainer.value.scrollTop = 0;
        }
    });
};

// ── State ─────────────────────────────────────────────────────────────
type Step = 'upload' | 'preview' | 'options' | 'confirming' | 'done';

const step = ref<Step>('upload');

watch(step, () => {
    scrollToTop();
});
const isLoading = ref(false);
const error = ref('');
// Folder collapse state (keyed by string path now)
const folderOpen = reactive<Record<string, boolean>>({});
// Watch preview to initialize folder collapse state (moved below after preview definition)

// Upload step
const dragActive = ref(false);
const curlInput = ref('');
const urlInput = ref('');
const selectedFormat = ref<ImportFormat | ''>('');

// Preview step
const importRecord = ref<ImportRecord | null>(null);
const preview = ref<ImportPreview | null>(null);
const conflicts = ref<ConflictItem[]>([]);
const deletions = ref<any[]>([]);
const selections = reactive<Record<string, boolean>>({});
// Search term for filtering requests and folders
const searchTerm = ref('');
interface FlatFolder {
    folder: ParsedFolder;
    path: string;
    displayName: string;
    level: number;
}

const getFlatFolders = (
    folders: ParsedFolder[],
    prefix = 'folder',
    parentName = '',
    level = 0,
): FlatFolder[] => {
    let result: FlatFolder[] = [];
    folders.forEach((f, i) => {
        const path = `${prefix}:${i}`;
        const displayName = parentName ? `${parentName} / ${f.name}` : f.name;
        result.push({ folder: f, path, displayName, level });

        if (f.folders && f.folders.length > 0) {
            result = result.concat(
                getFlatFolders(f.folders, path, displayName, level + 1),
            );
        }
    });

    return result;
};

const flatFoldersList = computed(() => {
    if (!preview.value) {
        return [];
    }

    return getFlatFolders(preview.value.folders);
});

const filteredFolders = computed(() => {
    if (!preview.value) {
        return [];
    }

    const term = searchTerm.value.toLowerCase();

    return flatFoldersList.value
        .map(({ folder: f, path, displayName, level }) => {
            const matchingRequests = f.requests
                .map((r, rIndex) => ({ request: r, originalIndex: rIndex }))
                .filter(
                    (rObj) =>
                        rObj.request.name.toLowerCase().includes(term) ||
                        displayName.toLowerCase().includes(term),
                );

            return {
                folder: f,
                path,
                displayName,
                level,
                filteredRequests: matchingRequests,
            };
        })
        .filter(
            (fObj) =>
                fObj.displayName.toLowerCase().includes(term) ||
                fObj.filteredRequests.length > 0,
        );
});
const filteredRootRequests = computed(() => {
    if (!preview.value) {
        return [];
    }

    const term = searchTerm.value.toLowerCase();

    return preview.value.requests
        .map((r, index) => ({ request: r, originalIndex: index }))
        .filter(({ request }) => request.name.toLowerCase().includes(term));
});

// Options step
watch(preview, (newPreview) => {
    // Reset all folders to collapsed when preview changes
    Object.keys(folderOpen).forEach((k) => delete folderOpen[k]);

    if (newPreview) {
        flatFoldersList.value.forEach(({ path }) => {
            folderOpen[path] = false;
        });
    }
});
const mergeStrategy = ref<MergeStrategy>('create_new');
const targetCollectionId = ref<string>('');
const targetFolderId = ref<string>('root');

watch(targetCollectionId, (newVal) => {
    targetFolderId.value = 'root';

    if (newVal && importRecord.value) {
        router.get(
            `/import/${importRecord.value.id}/preview`,
            {
                target_collection_id: newVal,
            },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['flash'],
                onSuccess: (page) => {
                    const flash = (page.props.flash || {}) as any;
                    conflicts.value = flash.conflicts || [];
                    deletions.value = flash.deletions || [];
                },
            },
        );
    } else {
        conflicts.value = [];
        deletions.value = [];
    }
});

const setTargetCollection = (val: any) => {
    setTimeout(() => {
        targetCollectionId.value = val ? String(val) : '';
    }, 50);
};

// Select All State
const allSelected = computed(() => {
    if (!preview.value) {
        return false;
    }

    let allCount = 0;
    let selectedCount = 0;

    allCount += preview.value.requests.length;
    preview.value.requests.forEach((r, i) => {
        if (selections[`root:${i}`]) {
            selectedCount++;
        }
    });

    flatFoldersList.value.forEach(({ folder: f, path }) => {
        if (f.requests.length === 0) {
            allCount++;

            if (selections[path]) {
                selectedCount++;
            }
        } else {
            allCount += f.requests.length;
            f.requests.forEach((r, ri) => {
                if (selections[`${path}:req:${ri}`]) {
                    selectedCount++;
                }
            });
        }
    });

    if (selectedCount === 0) {
        return false;
    }

    if (selectedCount === allCount) {
        return true;
    }

    return 'indeterminate';
});

const toggleAll = (checked: boolean) => {
    if (!preview.value) {
        return;
    }

    preview.value.requests.forEach((r, i) => {
        if (checked) {
            selections[`root:${i}`] = true;
        } else {
            delete selections[`root:${i}`];
        }
    });
    flatFoldersList.value.forEach(({ folder: f, path }) => {
        if (checked) {
            selections[path] = true;
        } else {
            delete selections[path];
        }

        f.requests.forEach((r, ri) => {
            if (checked) {
                selections[`${path}:req:${ri}`] = true;
            } else {
                delete selections[`${path}:req:${ri}`];
            }
        });
    });
};

// Root Requests State
const rootRequestsSelected = computed(() => {
    if (!preview.value || preview.value.requests.length === 0) {
        return false;
    }

    let selectedCount = 0;
    preview.value.requests.forEach((r, i) => {
        if (selections[`root:${i}`]) {
            selectedCount++;
        }
    });

    if (selectedCount === 0) {
        return false;
    }

    if (selectedCount === preview.value.requests.length) {
        return true;
    }

    return 'indeterminate';
});

const toggleRootRequests = (checked: boolean) => {
    preview.value?.requests.forEach((r, i) => {
        if (checked) {
            selections[`root:${i}`] = true;
        } else {
            delete selections[`root:${i}`];
        }
    });
};

const toggleRootRequest = (index: number, checked: boolean) => {
    if (checked) {
        selections[`root:${index}`] = true;
    } else {
        delete selections[`root:${index}`];
    }
};

// Folder State
const folderSelected = (path: string, f: ParsedFolder) => {
    if (f.requests.length === 0) {
        return !!selections[path];
    }

    let selectedCount = 0;
    f.requests.forEach((r, ri) => {
        if (selections[`${path}:req:${ri}`]) {
            selectedCount++;
        }
    });

    if (selectedCount === 0) {
        return false;
    }

    if (selectedCount === f.requests.length) {
        return true;
    }

    return 'indeterminate';
};

const toggleFolder = (path: string, f: ParsedFolder, checked: boolean) => {
    if (checked) {
        selections[path] = true;
    } else {
        delete selections[path];
    }

    f.requests.forEach((r, ri) => {
        if (checked) {
            selections[`${path}:req:${ri}`] = true;
        } else {
            delete selections[`${path}:req:${ri}`];
        }
    });
};

const toggleFolderRequest = (path: string, ri: number, checked: boolean) => {
    if (checked) {
        selections[`${path}:req:${ri}`] = true;
        selections[path] = true;
    } else {
        delete selections[`${path}:req:${ri}`];
    }
};

const isRequestSelected = (path: string) => {
    return !!selections[path];
};

// Populate selections on preview change
watch(
    preview,
    (newPreview) => {
        for (const key of Object.keys(selections)) {
            delete selections[key];
        }

        if (newPreview) {
            newPreview.requests.forEach(
                (r, i) => (selections[`root:${i}`] = true),
            );
            flatFoldersList.value.forEach(({ folder: f, path }) => {
                selections[path] = true;
                f.requests.forEach(
                    (r, ri) => (selections[`${path}:req:${ri}`] = true),
                );
            });
        }
    },
    { immediate: true },
);

// Done step
const finalResult = ref<ImportRecord | null>(null);

// ── Computed ──────────────────────────────────────────────────────────
const collectionOptions = computed(() =>
    store.collections.map((c) => ({ label: c.name, value: c.id })),
);

const folderOptions = computed(() => {
    if (!targetCollectionId.value) {
        return [];
    }

    const col = store.collections.find(
        (c) => c.id === targetCollectionId.value,
    );

    return col?.folders?.map((f) => ({ label: f.name, value: f.id })) || [];
});

const totalRequests = computed(() => {
    if (!preview.value) {
        return 0;
    }

    let count = preview.value.requests.length;

    for (const { folder } of flatFoldersList.value) {
        count += folder.requests.length;
    }

    return count;
});

// ── Upload handlers ──────────────────────────────────────────────────
const handleDrop = (e: DragEvent) => {
    e.preventDefault();
    dragActive.value = false;
    const files = e.dataTransfer?.files;

    if (files && files.length > 0) {
        uploadFile(files[0]);
    }
};

const handleFileSelect = (e: Event) => {
    const input = e.target as HTMLInputElement;

    if (input.files && input.files.length > 0) {
        uploadFile(input.files[0]);
    }
};

const uploadFile = async (file: File) => {
    // Cancel any in-flight Inertia visit (e.g. the router.get from a previous
    // import's onSuccess) so it doesn't auto-cancel this new upload request.
    router.cancelAll();

    isLoading.value = true;
    error.value = '';

    const formData = new FormData();
    formData.append('file', file);

    if (selectedFormat.value) {
        formData.append('format', selectedFormat.value);
    }

    router.post('/import/upload', formData, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            const flash = page.props.flash || ({} as any);

            if (flash.import) {
                handleUploadResponse(flash);
            }
        },
        onError: (errors) => {
            error.value =
                errors.error || Object.values(errors)[0] || 'Upload failed';
        },
        onFinish: () => {
            isLoading.value = false;

            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
};

const handleCurlImport = async () => {
    if (!curlInput.value.trim()) {
        return;
    }

    // Cancel any in-flight Inertia visit so it doesn't cancel this request.
    router.cancelAll();

    isLoading.value = true;
    error.value = '';

    router.post(
        '/import/upload',
        {
            content: curlInput.value,
            filename: 'curl-import.txt',
            format: 'curl',
        },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = page.props.flash || ({} as any);

                if (flash.import) {
                    handleUploadResponse(flash);
                }
            },
            onError: (errors) => {
                error.value =
                    errors.error || Object.values(errors)[0] || 'Import failed';
            },
            onFinish: () => {
                isLoading.value = false;
            },
        },
    );
};

const handleUrlImport = async () => {
    if (!urlInput.value.trim()) {
        return;
    }

    // Cancel any in-flight Inertia visit so it doesn't cancel this request.
    router.cancelAll();

    isLoading.value = true;
    error.value = '';

    router.post(
        '/import/upload',
        {
            url: urlInput.value.trim(),
            format: selectedFormat.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = page.props.flash || ({} as any);

                if (flash.import) {
                    handleUploadResponse(flash);
                }
            },
            onError: (errors) => {
                error.value =
                    errors.error ||
                    Object.values(errors)[0] ||
                    'Failed to import from URL';
            },
            onFinish: () => {
                isLoading.value = false;
            },
        },
    );
};

const handleUploadResponse = (data: any) => {
    importRecord.value = data.import;
    preview.value = data.preview;
    conflicts.value = data.conflicts || [];
    deletions.value = data.deletions || [];
    step.value = 'preview';
};

// ── Confirm ──────────────────────────────────────────────────────────
const confirmImport = async () => {
    if (!importRecord.value) {
        return;
    }

    step.value = 'confirming';
    isLoading.value = true;
    error.value = '';

    router.post(
        `/import/${importRecord.value.id}/confirm`,
        {
            merge_strategy: mergeStrategy.value,
            target_collection_id:
                mergeStrategy.value !== 'create_new'
                    ? targetCollectionId.value
                    : null,
            target_folder_id:
                mergeStrategy.value !== 'create_new' &&
                targetFolderId.value &&
                targetFolderId.value !== 'root'
                    ? targetFolderId.value
                    : null,
            selections: Object.keys(selections),
        },
        {
            only: ['flash', 'collections'],
            preserveState: true,
            preserveScroll: true,
            onSuccess: async (page) => {
                const flash = page.props.flash || ({} as any);

                if (flash.import) {
                    finalResult.value = flash.import;
                    step.value = 'done';

                    if (flash.success) {
                        await store.refreshCollections();

                        if (flash.import?.target_collection_id) {
                            const newCol = store.collections.find(
                                (c) =>
                                    c.id === flash.import.target_collection_id,
                            );

                            if (newCol) {
                                store.selectCollection(newCol);
                                router.get(
                                    '/collections/' + newCol.id,
                                    {},
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        async: true,
                                        only: [
                                            'activeCollectionId',
                                            'activeRequestId',
                                        ],
                                    },
                                );
                            }
                        }

                        emit('imported');
                    }
                }
            },
            onError: (errors) => {
                error.value =
                    errors.error ||
                    Object.values(errors)[0] ||
                    'Confirm failed';
                step.value = 'options';
            },
            onFinish: () => {
                isLoading.value = false;
            },
        },
    );
};

// ── Helpers ──────────────────────────────────────────────────────────
const reset = () => {
    step.value = 'upload';
    isLoading.value = false;
    error.value = '';
    curlInput.value = '';
    urlInput.value = '';
    selectedFormat.value = '';
    importRecord.value = null;
    preview.value = null;
    conflicts.value = [];
    deletions.value = [];

    for (const key of Object.keys(selections)) {
        delete selections[key];
    }

    mergeStrategy.value = 'create_new';
    targetCollectionId.value = '';
    targetFolderId.value = 'root';
    finalResult.value = null;

    if (fileInput.value) {
        fileInput.value.value = '';
    }

    scrollToTop();
};

const close = () => {
    emit('update:open', false);
    setTimeout(reset, 300);
};

const methodColor = (method: string) => {
    const colors: Record<string, string> = {
        GET: '#22c55e',
        POST: '#f59e0b',
        PUT: '#3b82f6',
        PATCH: '#8b5cf6',
        DELETE: '#ef4444',
        OPTIONS: '#6b7280',
        HEAD: '#6b7280',
    };

    return colors[method] || '#6b7280';
};
</script>

<template>
    <Dialog
        :open="open"
        @update:open="(val: boolean) => (val ? null : close())"
    >
        <DialogContent
            class="flex max-h-[85vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-[640px]"
        >
            <!-- Header -->
            <DialogHeader
                class="shrink-0 border-b border-border/40 px-6 pt-6 pb-4"
            >
                <DialogTitle class="flex items-center gap-2 text-base">
                    <Upload class="h-4 w-4 text-primary" />
                    Import Collection
                </DialogTitle>
                <DialogDescription class="text-xs text-muted-foreground">
                    Import from Postman, OpenAPI, Swagger, cURL, HAR, Insomnia,
                    or a URL
                </DialogDescription>
            </DialogHeader>

            <!-- Step Indicator -->
            <div
                class="flex shrink-0 items-center gap-1 border-b border-border/20 px-6 py-3"
            >
                <template
                    v-for="(s, i) in ['Upload', 'Preview', 'Options']"
                    :key="s"
                >
                    <div
                        class="flex items-center gap-1.5 text-xs font-medium transition-colors"
                        :class="{
                            'text-primary':
                                (step === 'upload' && i === 0) ||
                                (step === 'preview' && i === 1) ||
                                (['options', 'confirming', 'done'].includes(
                                    step,
                                ) &&
                                    i === 2),
                            'text-muted-foreground/50': !(
                                (step === 'upload' && i === 0) ||
                                (step === 'preview' && i === 1) ||
                                (['options', 'confirming', 'done'].includes(
                                    step,
                                ) &&
                                    i === 2)
                            ),
                        }"
                    >
                        <span
                            class="flex h-5 w-5 items-center justify-center rounded-full border text-[10px] font-bold transition-colors"
                            :class="{
                                'border-primary bg-primary text-primary-foreground':
                                    (step === 'upload' && i === 0) ||
                                    (step === 'preview' && i === 1) ||
                                    (['options', 'confirming', 'done'].includes(
                                        step,
                                    ) &&
                                        i === 2),
                                'border-muted-foreground/30': !(
                                    (step === 'upload' && i === 0) ||
                                    (step === 'preview' && i === 1) ||
                                    (['options', 'confirming', 'done'].includes(
                                        step,
                                    ) &&
                                        i === 2)
                                ),
                            }"
                        >
                            {{ i + 1 }}
                        </span>
                        {{ s }}
                    </div>
                    <ChevronRight
                        v-if="i < 2"
                        class="h-3 w-3 text-muted-foreground/30"
                    />
                </template>
            </div>

            <!-- Content -->
            <div ref="scrollContainer" class="min-h-0 flex-1 overflow-y-auto">
                <div class="px-6 py-4">
                    <!-- UPLOAD STEP -->
                    <div v-if="step === 'upload'" class="space-y-3">
                        <!-- Format selector -->
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-medium text-muted-foreground"
                            >
                                Format (auto-detected if left empty)
                            </label>
                            <Select v-model="selectedFormat">
                                <SelectTrigger class="h-8 text-xs">
                                    <SelectValue
                                        placeholder="Auto-detect format..."
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="postman_v2"
                                        >Postman Collection v2</SelectItem
                                    >
                                    <SelectItem value="openapi_3"
                                        >OpenAPI 3.x</SelectItem
                                    >
                                    <SelectItem value="swagger_2"
                                        >Swagger 2.0</SelectItem
                                    >
                                    <SelectItem value="curl">cURL</SelectItem>
                                    <SelectItem value="har">HAR</SelectItem>
                                    <SelectItem value="insomnia"
                                        >Insomnia</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Drop Zone -->
                        <div
                            class="group relative cursor-pointer rounded-lg border-2 border-dashed p-5 text-center transition-all"
                            :class="
                                dragActive
                                    ? 'scale-[1.01] border-primary bg-primary/5'
                                    : 'border-border hover:border-primary/50 hover:bg-muted/30'
                            "
                            @dragover.prevent="dragActive = true"
                            @dragleave="dragActive = false"
                            @drop="handleDrop"
                            @click="fileInput?.click()"
                        >
                            <input
                                ref="fileInput"
                                type="file"
                                class="hidden"
                                accept=".json,.yaml,.yml,.har,.txt,.sh,.bash,.curl"
                                @change="handleFileSelect"
                            />
                            <div class="flex flex-col items-center gap-2">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 transition-colors group-hover:bg-primary/15"
                                >
                                    <FileJson class="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium">
                                        Drop file here or
                                        <span class="text-primary">browse</span>
                                    </p>
                                    <p
                                        class="mt-1 text-[11px] text-muted-foreground"
                                    >
                                        JSON, YAML, HAR, or text files
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- cURL Input -->
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-medium text-muted-foreground"
                            >
                                Or paste cURL command(s)
                            </label>
                            <textarea
                                v-model="curlInput"
                                class="min-h-[60px] w-full resize-y rounded-md border border-border bg-muted/30 px-3 py-2 font-mono text-xs focus:ring-1 focus:ring-primary focus:outline-none"
                                placeholder="curl -X GET 'https://api.example.com/users' -H 'Authorization: Bearer token'"
                            />
                            <Button
                                v-if="curlInput.trim()"
                                size="sm"
                                class="mt-2 h-7 text-xs"
                                :disabled="isLoading"
                                @click="handleCurlImport"
                            >
                                <Zap class="mr-1 h-3 w-3" />
                                Import cURL
                            </Button>
                        </div>

                        <!-- URL Import -->
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-medium text-muted-foreground"
                            >
                                Or import from URL
                            </label>
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <Globe
                                        class="pointer-events-none absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                                    />
                                    <input
                                        v-model="urlInput"
                                        type="url"
                                        class="w-full rounded-md border border-border bg-muted/30 py-2 pr-3 pl-8 text-xs focus:ring-1 focus:ring-primary focus:outline-none"
                                        placeholder="https://api.example.com/openapi.json"
                                        @keyup.enter="handleUrlImport"
                                    />
                                </div>
                                <Button
                                    v-if="urlInput.trim()"
                                    size="sm"
                                    class="h-[34px] shrink-0 text-xs"
                                    :disabled="isLoading"
                                    @click="handleUrlImport"
                                >
                                    <LinkIcon class="mr-1 h-3 w-3" />
                                    Fetch
                                </Button>
                            </div>
                        </div>

                        <!-- Error -->
                        <div
                            v-if="error"
                            class="flex items-center gap-2 rounded-md border border-red-500/20 bg-red-500/10 px-3 py-2 text-xs text-red-500"
                        >
                            <XCircle class="h-3.5 w-3.5 shrink-0" />
                            {{ error }}
                        </div>

                        <!-- Loading -->
                        <div
                            v-if="isLoading"
                            class="flex items-center justify-center py-4"
                        >
                            <Loader2
                                class="h-5 w-5 animate-spin text-primary"
                            />
                            <span class="ml-2 text-sm text-muted-foreground"
                                >Parsing file...</span
                            >
                        </div>
                    </div>

                    <!-- PREVIEW STEP -->
                    <div
                        v-else-if="step === 'preview' && preview"
                        class="space-y-4"
                    >
                        <!-- Summary -->
                        <div class="flex flex-wrap items-center gap-3">
                            <FormatBadge
                                v-if="importRecord?.source_format"
                                :format="importRecord.source_format"
                            />
                            <Badge variant="outline" class="gap-1 text-[10px]">
                                <FolderOpen class="h-3 w-3" />
                                {{ preview.folders.length }} folders
                            </Badge>
                            <Badge variant="outline" class="gap-1 text-[10px]">
                                <Zap class="h-3 w-3" />
                                {{ totalRequests }} requests
                            </Badge>
                        </div>

                        <!-- Collection Name -->
                        <div
                            class="flex items-start justify-between rounded-lg border border-border/60 bg-muted/20 p-3"
                        >
                            <div>
                                <div
                                    class="mb-0.5 text-xs font-medium text-muted-foreground"
                                >
                                    Collection
                                </div>
                                <div class="text-sm font-semibold">
                                    {{ preview.collection_name }}
                                </div>
                                <div
                                    v-if="preview.collection_description"
                                    class="mt-1 max-h-32 overflow-y-auto pr-1 text-xs text-muted-foreground"
                                >
                                    {{ preview.collection_description }}
                                </div>
                            </div>
                            <div class="mt-1 flex items-center gap-2">
                                <span
                                    class="text-xs font-medium text-muted-foreground"
                                    >Select All</span
                                >
                                <Checkbox
                                    :model-value="allSelected"
                                    @update:model-value="toggleAll(!!$event)"
                                >
                                    <template #default>
                                        <Minus
                                            v-if="
                                                allSelected === 'indeterminate'
                                            "
                                            class="size-3.5"
                                        />
                                        <Check v-else class="size-3.5" />
                                    </template>
                                </Checkbox>
                            </div>
                        </div>

                        <!-- Search Input -->
                        <div class="my-2 flex items-center gap-2">
                            <Input
                                v-model="searchTerm"
                                placeholder="Search folders and requests..."
                                class="w-full"
                            />
                        </div>

                        <!-- Tree Preview -->
                        <div class="space-y-1">
                            <!-- Folders -->
                            <div
                                v-for="{
                                    folder,
                                    path,
                                    displayName,
                                    level,
                                    filteredRequests,
                                } in filteredFolders"
                                :key="path"
                                class="overflow-hidden rounded-md border border-border/40"
                            >
                                <div
                                    class="flex items-center justify-between bg-muted/30 px-3 py-2"
                                    @click="
                                        folderOpen[path] = !folderOpen[path]
                                    "
                                    style="cursor: pointer"
                                >
                                    <div
                                        class="flex items-center gap-2 text-xs font-medium"
                                        :style="{ paddingLeft: `${level}rem` }"
                                    >
                                        <ChevronRight
                                            v-if="!folderOpen[path]"
                                            class="h-3.5 w-3.5"
                                        />
                                        <ChevronDown
                                            v-else
                                            class="h-3.5 w-3.5"
                                        />
                                        <FolderOpen
                                            class="h-3.5 w-3.5 text-amber-500"
                                        />
                                        {{ displayName }}
                                        <span
                                            class="font-normal text-muted-foreground"
                                            >({{
                                                folder.requests.length
                                            }})</span
                                        >
                                    </div>
                                    <Checkbox
                                        :model-value="
                                            folderSelected(path, folder)
                                        "
                                        @update:model-value="
                                            toggleFolder(path, folder, !!$event)
                                        "
                                        @click.stop
                                    >
                                        <template #default>
                                            <Minus
                                                v-if="
                                                    folderSelected(
                                                        path,
                                                        folder,
                                                    ) === 'indeterminate'
                                                "
                                                class="size-3.5"
                                            />
                                            <Check v-else class="size-3.5" />
                                        </template>
                                    </Checkbox>
                                </div>
                                <div
                                    v-show="folderOpen[path]"
                                    class="divide-y divide-border/20"
                                >
                                    <div
                                        v-for="{
                                            request: req,
                                            originalIndex: ri,
                                        } in filteredRequests"
                                        :key="req.name"
                                        class="flex items-center justify-between px-3 py-1.5 text-xs"
                                        :style="{
                                            paddingLeft: `calc(2rem + ${level}rem)`,
                                        }"
                                    >
                                        <div
                                            class="flex min-w-0 items-center gap-2"
                                        >
                                            <span
                                                class="w-12 shrink-0 rounded px-1 py-0.5 text-center font-mono text-[10px] font-bold"
                                                :style="{
                                                    color: methodColor(
                                                        req.method,
                                                    ),
                                                    background:
                                                        methodColor(
                                                            req.method,
                                                        ) + '18',
                                                }"
                                            >
                                                {{ req.method }}
                                            </span>
                                            <span
                                                class="truncate text-muted-foreground"
                                                >{{ req.name }}</span
                                            >
                                        </div>
                                        <Checkbox
                                            :model-value="
                                                isRequestSelected(
                                                    `${path}:req:${ri}`,
                                                )
                                            "
                                            @update:model-value="
                                                toggleFolderRequest(
                                                    path,
                                                    ri,
                                                    !!$event,
                                                )
                                            "
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Root Requests -->
                            <div
                                v-if="filteredRootRequests.length > 0"
                                class="overflow-hidden rounded-md border border-border/40"
                            >
                                <div
                                    class="flex items-center justify-between bg-muted/30 px-3 py-2"
                                >
                                    <div
                                        class="flex items-center gap-2 text-xs font-medium text-muted-foreground"
                                    >
                                        <FolderOpen
                                            class="h-3.5 w-3.5 text-amber-500"
                                        />
                                        Root Requests
                                    </div>
                                    <Checkbox
                                        :model-value="rootRequestsSelected"
                                        @update:model-value="
                                            toggleRootRequests(!!$event)
                                        "
                                    >
                                        <template #default>
                                            <Minus
                                                v-if="
                                                    rootRequestsSelected ===
                                                    'indeterminate'
                                                "
                                                class="size-3.5"
                                            />
                                            <Check v-else class="size-3.5" />
                                        </template>
                                    </Checkbox>
                                </div>
                                <div class="divide-y divide-border/20">
                                    <div
                                        v-for="{
                                            request: req,
                                            originalIndex: i,
                                        } in filteredRootRequests"
                                        :key="req.name"
                                        class="flex items-center justify-between px-3 py-1.5 text-xs"
                                    >
                                        <div
                                            class="flex min-w-0 items-center gap-2"
                                        >
                                            <span
                                                class="w-12 shrink-0 rounded px-1 py-0.5 text-center font-mono text-[10px] font-bold"
                                                :style="{
                                                    color: methodColor(
                                                        req.method,
                                                    ),
                                                    background:
                                                        methodColor(
                                                            req.method,
                                                        ) + '18',
                                                }"
                                            >
                                                {{ req.method }}
                                            </span>
                                            <span
                                                class="truncate text-muted-foreground"
                                                >{{ req.name }}</span
                                            >
                                        </div>
                                        <Checkbox
                                            :model-value="
                                                isRequestSelected(`root:${i}`)
                                            "
                                            @update:model-value="
                                                toggleRootRequest(i, !!$event)
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Validation Report -->
                        <div v-if="preview.validation_messages.length > 0">
                            <div
                                class="mb-2 text-xs font-medium text-muted-foreground"
                            >
                                Validation
                            </div>
                            <ValidationReport
                                :messages="preview.validation_messages"
                            />
                        </div>
                    </div>

                    <!-- OPTIONS STEP -->
                    <div v-else-if="step === 'options'" class="space-y-5">
                        <div class="text-sm font-medium">Import Options</div>

                        <!-- Merge Strategy Cards -->
                        <div class="grid gap-2">
                            <button
                                v-for="opt in [
                                    {
                                        value: 'create_new' as MergeStrategy,
                                        icon: Plus,
                                        label: 'Create New Collection',
                                        desc: 'Import as a fresh, standalone collection',
                                    },
                                    {
                                        value: 'merge_replace' as MergeStrategy,
                                        icon: GitMerge,
                                        label: 'Merge & Replace',
                                        desc: 'Merge into existing collection, replace duplicates',
                                    },
                                    {
                                        value: 'merge_skip' as MergeStrategy,
                                        icon: SkipForward,
                                        label: 'Merge & Skip',
                                        desc: 'Merge into existing collection, skip duplicates',
                                    },
                                    {
                                        value: 'mirror' as MergeStrategy,
                                        icon: RefreshCw,
                                        label: 'Mirror / Prune',
                                        desc: 'Sync exactly to spec: update/add requests, remove missing ones',
                                    },
                                ]"
                                :key="opt.value"
                                class="flex items-start gap-3 rounded-lg border px-4 py-3 text-left transition-all hover:bg-muted/30"
                                :class="
                                    mergeStrategy === opt.value
                                        ? 'border-primary bg-primary/5 ring-1 ring-primary/30'
                                        : 'border-border'
                                "
                                @click="mergeStrategy = opt.value"
                            >
                                <div
                                    class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                                    :class="
                                        mergeStrategy === opt.value
                                            ? 'bg-primary/15 text-primary'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    <component :is="opt.icon" class="h-4 w-4" />
                                </div>
                                <div>
                                    <div class="text-sm font-medium">
                                        {{ opt.label }}
                                    </div>
                                    <div
                                        class="mt-0.5 text-[11px] text-muted-foreground"
                                    >
                                        {{ opt.desc }}
                                    </div>
                                </div>
                            </button>
                        </div>

                        <!-- Target Collection (for merge strategies) -->
                        <div
                            v-if="mergeStrategy !== 'create_new'"
                            class="space-y-4"
                        >
                            <div class="space-y-2">
                                <label class="text-xs font-medium"
                                    >Target Collection</label
                                >
                                <Select
                                    :model-value="targetCollectionId"
                                    @update:model-value="setTargetCollection"
                                >
                                    <SelectTrigger class="h-8 text-xs">
                                        <SelectValue
                                            placeholder="Select a collection..."
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="col in collectionOptions"
                                            :key="col.value"
                                            :value="col.value"
                                        >
                                            {{ col.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div
                                class="space-y-2"
                                v-if="
                                    targetCollectionId &&
                                    folderOptions.length > 0
                                "
                            >
                                <label class="text-xs font-medium"
                                    >Target Folder (Optional)</label
                                >
                                <Select v-model="targetFolderId">
                                    <SelectTrigger class="h-8 text-xs">
                                        <SelectValue
                                            placeholder="Root of Collection"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="root"
                                            >Root of Collection</SelectItem
                                        >
                                        <SelectItem
                                            v-for="f in folderOptions"
                                            :key="f.value"
                                            :value="f.value"
                                        >
                                            {{ f.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <!-- Conflict Warning (Merge Replace / Merge Skip) -->
                        <div
                            v-if="
                                conflicts.length > 0 &&
                                (mergeStrategy === 'merge_replace' ||
                                    mergeStrategy === 'merge_skip')
                            "
                            class="rounded-md border border-amber-500/30 bg-amber-500/5 p-3"
                        >
                            <div
                                class="mb-2 flex items-center gap-2 text-xs font-medium text-amber-600"
                            >
                                <AlertTriangle class="h-3.5 w-3.5" />
                                {{ conflicts.length }} conflicting request(s)
                                found
                            </div>
                            <div class="space-y-1">
                                <div
                                    v-for="c in conflicts.slice(0, 5)"
                                    :key="c.request_name"
                                    class="flex items-center gap-1.5 text-[11px] text-muted-foreground"
                                >
                                    <span
                                        class="rounded px-1 font-mono text-[9px] font-bold"
                                        :style="{
                                            color: methodColor(c.method),
                                            background:
                                                methodColor(c.method) + '18',
                                        }"
                                        >{{ c.method }}</span
                                    >
                                    {{ c.request_name }}
                                </div>
                                <div
                                    v-if="conflicts.length > 5"
                                    class="text-[11px] text-muted-foreground"
                                >
                                    ...and {{ conflicts.length - 5 }} more
                                </div>
                            </div>
                        </div>

                        <!-- Sync/Update Info for Mirror Strategy -->
                        <div
                            v-if="
                                mergeStrategy === 'mirror' &&
                                conflicts.length > 0
                            "
                            class="rounded-md border border-blue-500/30 bg-blue-500/5 p-3"
                        >
                            <div
                                class="mb-1.5 flex items-center gap-2 text-xs font-semibold text-blue-600 dark:text-blue-400"
                            >
                                <RefreshCw class="h-3.5 w-3.5" />
                                {{ conflicts.length }} existing request(s) will
                                be synced & updated
                            </div>
                            <div class="mb-2 text-[11px] text-muted-foreground">
                                These requests already exist in the target
                                collection and will be updated to match the
                                imported spec:
                            </div>
                            <div class="space-y-1">
                                <div
                                    v-for="c in conflicts.slice(0, 5)"
                                    :key="
                                        c.existing_request_id || c.request_name
                                    "
                                    class="flex items-center gap-1.5 text-[11px] text-muted-foreground"
                                >
                                    <span
                                        class="rounded px-1 font-mono text-[9px] font-bold"
                                        :style="{
                                            color: methodColor(c.method),
                                            background:
                                                methodColor(c.method) + '18',
                                        }"
                                        >{{ c.method }}</span
                                    >
                                    {{ c.request_name }}
                                </div>
                                <div
                                    v-if="conflicts.length > 5"
                                    class="text-[11px] text-muted-foreground"
                                >
                                    ...and {{ conflicts.length - 5 }} more
                                </div>
                            </div>
                        </div>

                        <!-- Deletions Warning for Mirror Strategy -->
                        <div
                            v-if="
                                mergeStrategy === 'mirror' &&
                                deletions.length > 0
                            "
                            class="rounded-md border border-destructive/40 bg-destructive/10 p-3"
                        >
                            <div
                                class="mb-1.5 flex items-center gap-2 text-xs font-semibold text-destructive"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                                {{ deletions.length }} request(s) will be
                                deleted (pruned)
                            </div>
                            <div class="mb-2 text-[11px] text-muted-foreground">
                                Because they exist in the target collection but
                                are not present in the imported spec:
                            </div>
                            <div class="space-y-1">
                                <div
                                    v-for="d in deletions.slice(0, 5)"
                                    :key="
                                        d.existing_request_id || d.request_name
                                    "
                                    class="flex items-center gap-1.5 text-[11px] text-muted-foreground"
                                >
                                    <span
                                        class="rounded px-1 font-mono text-[9px] font-bold"
                                        :style="{
                                            color: methodColor(d.method),
                                            background:
                                                methodColor(d.method) + '18',
                                        }"
                                        >{{ d.method }}</span
                                    >
                                    {{ d.request_name }}
                                </div>
                                <div
                                    v-if="deletions.length > 5"
                                    class="text-[11px] text-muted-foreground"
                                >
                                    ...and {{ deletions.length - 5 }} more
                                </div>
                            </div>
                        </div>

                        <!-- Error -->
                        <div
                            v-if="error"
                            class="flex items-center gap-2 rounded-md border border-red-500/20 bg-red-500/10 px-3 py-2 text-xs text-red-500"
                        >
                            <XCircle class="h-3.5 w-3.5 shrink-0" />
                            {{ error }}
                        </div>
                    </div>

                    <!-- CONFIRMING -->
                    <div
                        v-else-if="step === 'confirming'"
                        class="flex flex-col items-center justify-center py-12"
                    >
                        <Loader2
                            class="mb-3 h-8 w-8 animate-spin text-primary"
                        />
                        <div class="text-sm font-medium">Importing...</div>
                        <div class="mt-1 text-xs text-muted-foreground">
                            Creating collections and requests
                        </div>
                    </div>

                    <!-- DONE -->
                    <div
                        v-else-if="step === 'done'"
                        class="flex flex-col items-center justify-center py-12"
                    >
                        <template v-if="finalResult?.status === 'completed'">
                            <div
                                class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-green-500/10"
                            >
                                <CheckCircle2 class="h-7 w-7 text-green-500" />
                            </div>
                            <div class="text-sm font-semibold">
                                Import Complete!
                            </div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                {{
                                    finalResult.summary?.requests ?? 0
                                }}
                                requests imported successfully
                            </div>
                        </template>
                        <template v-else>
                            <div
                                class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-red-500/10"
                            >
                                <XCircle class="h-7 w-7 text-red-500" />
                            </div>
                            <div class="text-sm font-semibold">
                                Import Failed
                            </div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                {{
                                    finalResult?.error_message ??
                                    'Unknown error occurred'
                                }}
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div
                class="flex shrink-0 items-center justify-between border-t border-border/40 px-6 py-4"
            >
                <Button
                    v-if="step === 'preview'"
                    variant="ghost"
                    size="sm"
                    class="h-8 text-xs"
                    @click="reset()"
                >
                    <ArrowLeft class="mr-1 h-3 w-3" />
                    Back
                </Button>
                <Button
                    v-else-if="step === 'options'"
                    variant="ghost"
                    size="sm"
                    class="h-8 text-xs"
                    @click="step = 'preview'"
                >
                    <ArrowLeft class="mr-1 h-3 w-3" />
                    Back
                </Button>
                <div v-else />

                <div class="flex items-center gap-2">
                    <Button
                        v-if="step === 'done'"
                        size="sm"
                        class="h-8 text-xs"
                        @click="close()"
                    >
                        Done
                    </Button>
                    <Button
                        v-else-if="step === 'preview'"
                        size="sm"
                        class="h-8 text-xs"
                        @click="step = 'options'"
                    >
                        Next
                        <ArrowRight class="ml-1 h-3 w-3" />
                    </Button>
                    <Button
                        v-else-if="step === 'options'"
                        size="sm"
                        class="h-8 text-xs"
                        :disabled="
                            isLoading ||
                            (mergeStrategy !== 'create_new' &&
                                !targetCollectionId)
                        "
                        @click="confirmImport"
                    >
                        <CheckCircle2 class="mr-1 h-3 w-3" />
                        Confirm Import
                    </Button>
                    <Button
                        v-if="!['done', 'confirming'].includes(step)"
                        variant="outline"
                        size="sm"
                        class="h-8 text-xs"
                        @click="close()"
                    >
                        Cancel
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
