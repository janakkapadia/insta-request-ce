<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Plus,
    Trash2,
    SlidersHorizontal,
    Search,
    Save,
    Check,
    AlertCircle,
    MoreVertical,
    SearchCode,
    Download,
    Upload,
    FileJson,
} from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useWorkspaceStore } from '@/stores/workspace';
import type {
    EnvironmentItem,
    EnvironmentVariableItem,
} from '@/stores/workspace';

const store = useWorkspaceStore();
const searchQuery = ref('');
const selectedEnvId = ref<string | null>(null);

// Editable copy of the active environment variables
const envName = ref('');
const localVariables = ref<EnvironmentVariableItem[]>([]);
const isSaving = ref(false);

const showImportDialog = ref(false);
const importFile = ref<File | null>(null);
const importJsonText = ref('');
const isImporting = ref(false);

const handleImportFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;

    if (target.files && target.files.length > 0) {
        importFile.value = target.files[0];
    } else {
        importFile.value = null;
    }
};

const dragActive = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);

const handleImportDrop = (event: DragEvent) => {
    dragActive.value = false;

    if (event.dataTransfer?.files && event.dataTransfer.files.length > 0) {
        importFile.value = event.dataTransfer.files[0];
    }
};

const executeImport = async () => {
    if (!importFile.value && !importJsonText.value.trim()) {
        toast.error('Please select a JSON file or paste raw JSON content');

        return;
    }

    isImporting.value = true;

    try {
        const payload: any = {};

        if (importFile.value) {
            payload.file = importFile.value;
        } else {
            payload.content = importJsonText.value.trim();
        }

        const success = await store.importEnvironment(payload);

        if (success) {
            showImportDialog.value = false;
            importFile.value = null;
            importJsonText.value = '';
        }
    } finally {
        isImporting.value = false;
    }
};

defineOptions({
    layout: () => ({
        breadcrumbs: [{ title: 'Environments', href: '/environments' }],
    }),
});

const props = defineProps<{
    environments: EnvironmentItem[];
}>();

// Initialize and fetch environments on mount using Inertia props
onMounted(() => {
    store.setEnvironments(props.environments);

    // Fallback: Restore active environment from localStorage if present
    const savedId = localStorage.getItem('active_environment_id');

    if (savedId && !store.activeEnvironment) {
        const found = store.environments.find((e) => e.id === savedId);

        if (found) {
            store.activeEnvironment = found;
        }
    }

    if (store.environments.length > 0) {
        selectEnv(store.environments[0]);
    }
});

const filteredEnvironments = computed(() => {
    if (!searchQuery.value) {
        return store.environments;
    }

    const q = searchQuery.value.toLowerCase();

    return store.environments.filter((env) =>
        env.name.toLowerCase().includes(q),
    );
});

const activeEnv = computed(() => {
    return store.environments.find((e) => e.id === selectedEnvId.value) || null;
});

const selectEnv = (env: EnvironmentItem) => {
    selectedEnvId.value = env.id;
    envName.value = env.name;

    // Deep clone variables and ensure there is always at least one empty row at the bottom
    localVariables.value = env.variables.map((v) => ({ ...v }));
    ensureEmptyRow();
};

const ensureEmptyRow = () => {
    const vars = localVariables.value;

    if (
        vars.length === 0 ||
        vars[vars.length - 1].key ||
        vars[vars.length - 1].value
    ) {
        localVariables.value.push({
            key: '',
            value: '',
            enabled: true,
        });
    }
};

const handleVariableInput = () => {
    ensureEmptyRow();
};

const removeVariable = (index: number) => {
    localVariables.value.splice(index, 1);
    ensureEmptyRow();
};

const createNewEnvironment = async () => {
    const name = `New Environment ${store.environments.length + 1}`;
    const success = await store.createEnvironment(name);

    if (success) {
        toast.success('Environment created successfully');
        const newEnv = store.environments.find((e) => e.name === name);

        if (newEnv) {
            selectEnv(newEnv);
        }
    }
};

const saveEnvironmentDetails = async () => {
    if (!selectedEnvId.value) {
        return;
    }

    if (!envName.value.trim()) {
        toast.error('Environment name cannot be empty');

        return;
    }

    isSaving.value = true;
    // Strip trailing empty rows before saving
    const cleanVars = localVariables.value.filter((v) => v.key.trim() !== '');

    try {
        const success = await store.updateEnvironment(
            selectedEnvId.value,
            envName.value,
            cleanVars,
        );

        if (success) {
            toast.success('Environment saved successfully');
            const updated = store.environments.find(
                (e) => e.id === selectedEnvId.value,
            );

            if (updated) {
                selectEnv(updated);
            }
        }
    } catch {
        toast.error('Failed to save environment');
    } finally {
        isSaving.value = false;
    }
};

const deleteActiveEnvironment = async () => {
    if (!selectedEnvId.value) {
        return;
    }

    if (confirm('Are you sure you want to delete this environment?')) {
        const idToDelete = selectedEnvId.value;
        selectedEnvId.value = null;
        await store.deleteEnvironment(idToDelete);
        toast.success('Environment deleted successfully');

        if (store.environments.length > 0) {
            selectEnv(store.environments[0]);
        }
    }
};

const toggleVariableEnabled = (index: number) => {
    localVariables.value[index].enabled = !localVariables.value[index].enabled;
};

const showReplaceDialog = ref(false);
const replaceVariable = ref<{ key: string; value: string } | null>(null);
const replaceCollectionId = ref('');
const isReplacing = ref(false);

const openReplaceDialog = (variable: EnvironmentVariableItem) => {
    if (!variable.key || !variable.value) {
        toast.error('Variable must have a key and value to replace');

        return;
    }

    replaceVariable.value = variable;
    replaceCollectionId.value = '';
    showReplaceDialog.value = true;
};

const executeReplace = async () => {
    if (!replaceCollectionId.value || !replaceVariable.value) {
        return;
    }

    isReplacing.value = true;

    try {
        router.post(
            `/environments/${selectedEnvId.value}/replace-value`,
            {
                key: replaceVariable.value.key,
                value: replaceVariable.value.value,
                collection_id: replaceCollectionId.value,
            },
            {
                onSuccess: () => {
                    toast.success('Values replaced in collection successfully');
                    showReplaceDialog.value = false;
                    store.refreshCollections();
                    isReplacing.value = false;
                },
                onError: (errors: any) => {
                    toast.error(errors?.message || 'Failed to replace values');
                    isReplacing.value = false;
                },
            },
        );
    } catch (e: any) {
        toast.error(e.message || 'Failed to replace values');
        isReplacing.value = false;
    }
};
</script>

<template>
    <Head title="Environments - Postman" />

    <div class="flex h-full min-h-0 flex-1 overflow-hidden bg-background">
        <!-- Left Pane: Environments sidebar list -->
        <div class="flex h-full w-80 shrink-0 flex-col border-r bg-card/40">
            <div
                class="flex items-center justify-between gap-2 border-b bg-card/60 p-4"
            >
                <div class="relative flex-1">
                    <Search
                        class="absolute top-2.5 left-2.5 h-3.5 w-3.5 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        placeholder="Search environments..."
                        class="h-8 border-input/60 bg-background/50 pl-8 text-xs focus-visible:ring-1"
                    />
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <Button
                        size="icon"
                        variant="outline"
                        class="h-8 w-8 shrink-0 border-dashed text-muted-foreground hover:text-foreground"
                        title="Import Environment (JSON)"
                        @click="showImportDialog = true"
                    >
                        <Upload class="h-4 w-4" />
                    </Button>
                    <Button
                        size="icon"
                        variant="outline"
                        class="h-8 w-8 shrink-0 border-dashed text-muted-foreground hover:text-foreground"
                        title="Create New Environment"
                        @click="createNewEnvironment"
                    >
                        <Plus class="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <ScrollArea class="flex-1">
                <div class="space-y-0.5 p-2">
                    <button
                        v-for="env in filteredEnvironments"
                        :key="env.id"
                        type="button"
                        @click="selectEnv(env)"
                        :class="[
                            'group flex w-full items-center justify-between rounded-md px-3 py-2.5 text-left transition-colors',
                            selectedEnvId === env.id
                                ? 'border border-accent/20 bg-accent font-medium text-accent-foreground shadow-sm'
                                : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                        ]"
                    >
                        <div class="flex min-w-0 items-center gap-2.5">
                            <div
                                class="h-2 w-2 shrink-0 rounded-full bg-primary/70"
                            />
                            <span class="truncate font-mono text-xs">{{
                                env.name
                            }}</span>
                        </div>
                        <span
                            v-if="store.activeEnvironment?.id === env.id"
                            class="shrink-0 rounded border border-primary/20 bg-primary/10 px-1.5 py-0.5 text-[10px] font-medium text-primary"
                        >
                            Active
                        </span>
                    </button>

                    <div
                        v-if="filteredEnvironments.length === 0"
                        class="px-4 py-12 text-center"
                    >
                        <SlidersHorizontal
                            class="mx-auto mb-2 h-8 w-8 text-muted-foreground/30"
                        />
                        <p class="text-xs text-muted-foreground">
                            No environments found
                        </p>
                    </div>
                </div>
            </ScrollArea>
        </div>

        <!-- Right Pane: Active Selected Environment Detail Editor -->
        <div class="flex h-full flex-1 flex-col bg-background">
            <template v-if="activeEnv">
                <!-- Environment Info Header Row -->
                <div
                    class="flex items-center justify-between gap-4 border-b bg-card/25 p-6"
                >
                    <input
                        v-model="envName"
                        placeholder="Environment Name"
                        class="h-10 w-full border-none bg-transparent px-0 text-lg font-semibold text-foreground shadow-none outline-none placeholder:text-muted-foreground/30 focus:ring-0 focus:outline-none"
                    />
                    <div class="flex items-center gap-2">
                        <Button
                            :variant="
                                store.activeEnvironment?.id === activeEnv.id
                                    ? 'default'
                                    : 'outline'
                            "
                            size="sm"
                            :class="[
                                'h-9 gap-1.5 px-3 text-xs transition-all duration-200',
                                store.activeEnvironment?.id === activeEnv.id
                                    ? 'border-transparent bg-primary text-primary-foreground shadow-sm hover:bg-primary/90'
                                    : 'border-input/60 text-muted-foreground hover:bg-accent hover:text-foreground',
                            ]"
                            @click="
                                store.setActiveEnvironment(
                                    store.activeEnvironment?.id === activeEnv.id
                                        ? null
                                        : activeEnv,
                                )
                            "
                        >
                            <Check
                                class="h-3.5 w-3.5"
                                :class="
                                    store.activeEnvironment?.id === activeEnv.id
                                        ? 'text-primary-foreground'
                                        : 'text-primary'
                                "
                            />
                            <span>{{
                                store.activeEnvironment?.id === activeEnv.id
                                    ? 'Active'
                                    : 'Set Active'
                            }}</span>
                        </Button>
                        <Button
                            variant="default"
                            size="sm"
                            class="h-9 gap-1.5 px-4 text-xs"
                            :disabled="isSaving"
                            @click="saveEnvironmentDetails"
                        >
                            <Save class="h-3.5 w-3.5" v-if="!isSaving" />
                            <span
                                v-else
                                class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-background border-t-transparent"
                            />
                            Save
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-9 shrink-0 gap-1.5 border-input/60 px-3 text-xs text-muted-foreground hover:bg-accent hover:text-foreground"
                            @click="store.exportEnvironment(activeEnv.id)"
                        >
                            <Download class="h-3.5 w-3.5" />
                            Export
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-9 shrink-0 gap-1.5 border-destructive/20 px-3 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                            @click="deleteActiveEnvironment"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            Delete
                        </Button>
                    </div>
                </div>

                <!-- Variables Interactive Table -->
                <div class="flex flex-1 flex-col overflow-hidden">
                    <div
                        class="flex shrink-0 items-center justify-between border-b bg-muted/20 px-6 py-4"
                    >
                        <div class="flex items-center gap-2">
                            <h3
                                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Environment Variables
                            </h3>
                            <span
                                class="rounded border border-primary/20 bg-primary/10 px-1.5 py-0.5 font-mono text-[10px] text-primary"
                            >
                                {{
                                    localVariables.filter((v) => v.key).length
                                }}
                                Active
                            </span>
                        </div>
                        <span
                            class="flex items-center gap-1 text-[10px] text-muted-foreground"
                        >
                            <AlertCircle
                                class="h-3.5 w-3.5 text-muted-foreground/70"
                            />
                            Reference in requests using
                            <code
                                class="rounded bg-muted px-1 py-0.5 font-mono text-[10px] font-semibold text-primary"
                                >&#123;variable_name&#125;</code
                            >
                        </span>
                    </div>

                    <ScrollArea class="flex-1">
                        <div class="p-6">
                            <div
                                class="overflow-hidden rounded-lg border bg-card/30"
                            >
                                <table class="w-full border-collapse">
                                    <thead>
                                        <tr
                                            class="border-b bg-muted/40 text-[11px] font-semibold text-muted-foreground uppercase"
                                        >
                                            <th
                                                class="w-12 border-r px-3 py-2.5 text-center"
                                            ></th>
                                            <th
                                                class="w-1/3 border-r px-4 py-2.5 text-left"
                                            >
                                                Variable Key
                                            </th>
                                            <th
                                                class="border-r px-4 py-2.5 text-left"
                                            >
                                                Value
                                            </th>
                                            <th
                                                class="w-20 px-3 py-2.5 text-center"
                                            >
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(
                                                variable, index
                                            ) in localVariables"
                                            :key="index"
                                            class="group border-b transition-colors last:border-b-0 hover:bg-muted/10"
                                        >
                                            <!-- Checkbox selector / Enable variable -->
                                            <td
                                                class="border-r px-3 py-2 text-center"
                                            >
                                                <Checkbox
                                                    :checked="variable.enabled"
                                                    @update:checked="
                                                        toggleVariableEnabled(
                                                            index,
                                                        )
                                                    "
                                                    class="h-3.5 w-3.5 shrink-0 rounded border-muted-foreground/30 focus-visible:ring-1 focus-visible:ring-offset-0"
                                                    :disabled="!variable.key"
                                                />
                                            </td>

                                            <!-- Variable Key input -->
                                            <td class="border-r p-0">
                                                <input
                                                    v-model="variable.key"
                                                    @input="handleVariableInput"
                                                    placeholder="Add variable key"
                                                    class="h-10 w-full border-none bg-transparent px-4 font-mono text-xs text-foreground shadow-none outline-none placeholder:text-muted-foreground/40 focus:ring-0 focus:outline-none"
                                                />
                                            </td>

                                            <!-- Variable Value input -->
                                            <td class="border-r p-0">
                                                <input
                                                    v-model="variable.value"
                                                    @input="handleVariableInput"
                                                    placeholder="Value"
                                                    class="h-10 w-full border-none bg-transparent px-4 font-mono text-xs text-foreground shadow-none outline-none placeholder:text-muted-foreground/40 focus:ring-0 focus:outline-none"
                                                />
                                            </td>

                                            <!-- Actions -->
                                            <td class="px-3 py-2 text-center">
                                                <div
                                                    class="flex items-center justify-center gap-1"
                                                >
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        class="h-7 w-7 rounded-md text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                                        @click="
                                                            removeVariable(
                                                                index,
                                                            )
                                                        "
                                                        :disabled="
                                                            index ===
                                                                localVariables.length -
                                                                    1 &&
                                                            !variable.key
                                                        "
                                                        title="Delete"
                                                    >
                                                        <Trash2
                                                            class="h-3.5 w-3.5"
                                                        />
                                                    </Button>
                                                    <DropdownMenu
                                                        v-if="variable.key"
                                                    >
                                                        <DropdownMenuTrigger
                                                            as-child
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                class="h-7 w-7 rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                                            >
                                                                <MoreVertical
                                                                    class="h-3.5 w-3.5"
                                                                />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent
                                                            align="end"
                                                        >
                                                            <DropdownMenuItem
                                                                @click="
                                                                    openReplaceDialog(
                                                                        variable,
                                                                    )
                                                                "
                                                                class="whitespace-nowrap"
                                                            >
                                                                <SearchCode
                                                                    class="mr-2 h-4 w-4"
                                                                />
                                                                Replace in
                                                                Collection
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </ScrollArea>
                </div>
            </template>

            <!-- Empty State View -->
            <template v-else>
                <div
                    class="flex flex-1 flex-col items-center justify-center bg-card/10 p-8"
                >
                    <div
                        class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl border border-dashed border-muted-foreground/30 bg-muted/40"
                    >
                        <SlidersHorizontal
                            class="h-7 w-7 text-muted-foreground/70"
                        />
                    </div>
                    <h2 class="mb-1 text-sm font-semibold text-foreground">
                        Manage Workspace Environments
                    </h2>
                    <p
                        class="mb-6 max-w-sm text-center text-xs leading-relaxed text-muted-foreground"
                    >
                        Create variables to securely store settings like server
                        URLs, authentication tokens, and keys. Use single braces
                        to swap values inside requests.
                    </p>
                    <Button
                        variant="default"
                        size="sm"
                        class="h-9 gap-1.5 px-4 text-xs"
                        @click="createNewEnvironment"
                    >
                        <Plus class="h-3.5 w-3.5" />
                        Create Environment
                    </Button>
                </div>
            </template>
        </div>
    </div>

    <Dialog :open="showReplaceDialog" @update:open="showReplaceDialog = $event">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>Find & Replace in Collection</DialogTitle>
                <DialogDescription>
                    Search for the value
                    <code
                        class="rounded bg-muted px-1 py-0.5 font-mono text-xs text-primary"
                        >{{ replaceVariable?.value }}</code
                    >
                    in a collection and replace it with
                    <code
                        class="rounded bg-muted px-1 py-0.5 font-mono text-xs text-primary"
                        >&#123;{{ replaceVariable?.key }}&#125;</code
                    >.
                </DialogDescription>
            </DialogHeader>
            <div class="grid gap-4 py-4">
                <div class="space-y-2">
                    <label class="text-xs font-semibold text-foreground"
                        >Select Collection</label
                    >
                    <Select v-model="replaceCollectionId">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Select a collection" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="collection in store.collections"
                                :key="collection.id"
                                :value="collection.id"
                            >
                                {{ collection.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="showReplaceDialog = false"
                    >Cancel</Button
                >
                <Button
                    :disabled="!replaceCollectionId || isReplacing"
                    @click="executeReplace"
                >
                    <SearchCode class="mr-2 h-4 w-4" v-if="!isReplacing" />
                    <span
                        v-else
                        class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-background border-t-transparent"
                    />
                    Replace All
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
    <Dialog :open="showImportDialog" @update:open="showImportDialog = $event">
        <DialogContent class="sm:max-w-[480px]">
            <DialogHeader>
                <DialogTitle>Import Environment</DialogTitle>
                <DialogDescription>
                    Import a Postman environment or JSON environment file, or
                    paste raw JSON content below.
                </DialogDescription>
            </DialogHeader>
            <div class="grid gap-4 py-4">
                <div class="space-y-2">
                    <label class="text-xs font-semibold text-foreground"
                        >Environment File (.json)</label
                    >
                    <div
                        class="group relative cursor-pointer rounded-lg border-2 border-dashed p-5 text-center transition-all"
                        :class="
                            dragActive
                                ? 'scale-[1.01] border-primary bg-primary/5'
                                : 'border-border hover:border-primary/50 hover:bg-muted/30'
                        "
                        @dragover.prevent="dragActive = true"
                        @dragleave="dragActive = false"
                        @drop.prevent="handleImportDrop"
                        @click="fileInputRef?.click()"
                    >
                        <input
                            ref="fileInputRef"
                            type="file"
                            class="hidden"
                            accept=".json,application/json"
                            @change="handleImportFileSelect"
                        />
                        <div class="flex flex-col items-center gap-2">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 transition-colors group-hover:bg-primary/15"
                            >
                                <FileJson class="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <p class="text-sm font-medium">
                                    <span
                                        v-if="importFile"
                                        class="text-primary"
                                        >{{ importFile.name }}</span
                                    >
                                    <span v-else
                                        >Drop file here or
                                        <span class="text-primary"
                                            >browse</span
                                        ></span
                                    >
                                </p>
                                <p
                                    class="mt-1 text-[11px] text-muted-foreground"
                                >
                                    <span v-if="importFile"
                                        >{{
                                            (importFile.size / 1024).toFixed(1)
                                        }}
                                        KB</span
                                    >
                                    <span v-else
                                        >Postman or JSON environment file
                                        (.json)</span
                                    >
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="relative flex items-center py-1">
                    <div class="flex-grow border-t border-muted/60"></div>
                    <span
                        class="mx-3 flex-shrink text-[10px] font-semibold text-muted-foreground uppercase"
                        >Or Paste Raw JSON</span
                    >
                    <div class="flex-grow border-t border-muted/60"></div>
                </div>
                <div class="space-y-2">
                    <label class="text-xs font-semibold text-foreground"
                        >JSON Content</label
                    >
                    <textarea
                        v-model="importJsonText"
                        rows="6"
                        placeholder='{"name": "Production", "values": [{"key": "API_URL", "value": "https://api.example.com", "enabled": true}]}'
                        class="w-full rounded-md border border-input bg-background/50 px-3 py-2 font-mono text-xs shadow-sm placeholder:text-muted-foreground/40 focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    />
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="showImportDialog = false"
                    >Cancel</Button
                >
                <Button
                    :disabled="
                        (!importFile && !importJsonText.trim()) || isImporting
                    "
                    @click="executeImport"
                >
                    <Upload class="mr-2 h-4 w-4" v-if="!isImporting" />
                    <span
                        v-else
                        class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-background border-t-transparent"
                    />
                    Import
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
