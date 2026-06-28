<script setup lang="ts">
import { Head, usePage, router } from '@inertiajs/vue3';
import { 
    Plus, 
    Trash2, 
    SlidersHorizontal, 
    Search, 
    Save, 
    Play, 
    Folder,
    Check,
    AlertCircle,
    MoreVertical,
    SearchCode
} from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
import type { EnvironmentItem, EnvironmentVariableItem } from '@/stores/workspace';

const store = useWorkspaceStore();
const searchQuery = ref('');
const selectedEnvId = ref<string | null>(null);

// Editable copy of the active environment variables
const envName = ref('');
const localVariables = ref<EnvironmentVariableItem[]>([]);
const isSaving = ref(false);

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Environments', href: '/environments' }
        ],
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
        const found = store.environments.find(e => e.id === savedId);

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

    return store.environments.filter(env => env.name.toLowerCase().includes(q));
});

const activeEnv = computed(() => {
    return store.environments.find(e => e.id === selectedEnvId.value) || null;
});

const selectEnv = (env: EnvironmentItem) => {
    selectedEnvId.value = env.id;
    envName.value = env.name;
    
    // Deep clone variables and ensure there is always at least one empty row at the bottom
    localVariables.value = env.variables.map(v => ({ ...v }));
    ensureEmptyRow();
};

const ensureEmptyRow = () => {
    const vars = localVariables.value;

    if (vars.length === 0 || vars[vars.length - 1].key || vars[vars.length - 1].value) {
        localVariables.value.push({
            key: '',
            value: '',
            enabled: true
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
        const newEnv = store.environments.find(e => e.name === name);

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
    const cleanVars = localVariables.value.filter(v => v.key.trim() !== '');
    
    try {
        const success = await store.updateEnvironment(selectedEnvId.value, envName.value, cleanVars);

        if (success) {
            toast.success('Environment saved successfully');
            const updated = store.environments.find(e => e.id === selectedEnvId.value);

            if (updated) {
selectEnv(updated);
}
        }
    } catch (e) {
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
const replaceVariable = ref<{key: string, value: string} | null>(null);
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
        router.post(`/environments/${selectedEnvId.value}/replace-value`, {
            key: replaceVariable.value.key,
            value: replaceVariable.value.value,
            collection_id: replaceCollectionId.value
        }, {
            onSuccess: () => {
                toast.success('Values replaced in collection successfully');
                showReplaceDialog.value = false;
                store.refreshCollections();
                isReplacing.value = false;
            },
            onError: (errors: any) => {
                toast.error(errors?.message || 'Failed to replace values');
                isReplacing.value = false;
            }
        });
    } catch (e: any) {
        toast.error(e.message || 'Failed to replace values');
        isReplacing.value = false;
    }
};
</script>

<template>
    <Head title="Environments - Postman" />
    
    <div class="flex flex-1 h-full min-h-0 overflow-hidden bg-background">
            <!-- Left Pane: Environments sidebar list -->
            <div class="w-80 border-r flex flex-col h-full bg-card/40 shrink-0">
                <div class="p-4 border-b flex items-center justify-between gap-2 bg-card/60">
                    <div class="relative flex-1">
                        <Search class="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
                        <Input 
                            v-model="searchQuery"
                            placeholder="Search environments..." 
                            class="pl-8 h-8 text-xs bg-background/50 border-input/60 focus-visible:ring-1" 
                        />
                    </div>
                    <Button 
                        size="icon" 
                        variant="outline" 
                        class="h-8 w-8 text-muted-foreground hover:text-foreground shrink-0 border-dashed"
                        @click="createNewEnvironment"
                    >
                        <Plus class="h-4 w-4" />
                    </Button>
                </div>

                <ScrollArea class="flex-1">
                    <div class="p-2 space-y-0.5">
                        <button
                            v-for="env in filteredEnvironments"
                            :key="env.id"
                            type="button"
                            @click="selectEnv(env)"
                            :class="[
                                'w-full text-left px-3 py-2.5 rounded-md flex items-center justify-between transition-colors group',
                                selectedEnvId === env.id 
                                    ? 'bg-accent text-accent-foreground font-medium shadow-sm border border-accent/20' 
                                    : 'hover:bg-accent/50 text-muted-foreground hover:text-foreground'
                            ]"
                        >
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="h-2 w-2 rounded-full shrink-0 bg-primary/70" />
                                <span class="text-xs truncate font-mono">{{ env.name }}</span>
                            </div>
                            <span 
                                v-if="store.activeEnvironment?.id === env.id"
                                class="text-[10px] bg-primary/10 text-primary px-1.5 py-0.5 rounded font-medium border border-primary/20 shrink-0"
                            >
                                Active
                            </span>
                        </button>
                        
                        <div 
                            v-if="filteredEnvironments.length === 0" 
                            class="py-12 text-center px-4"
                        >
                            <SlidersHorizontal class="h-8 w-8 text-muted-foreground/30 mx-auto mb-2" />
                            <p class="text-xs text-muted-foreground">No environments found</p>
                        </div>
                    </div>
                </ScrollArea>
            </div>

            <!-- Right Pane: Active Selected Environment Detail Editor -->
            <div class="flex-1 flex flex-col h-full bg-background">
                <template v-if="activeEnv">
                    <!-- Environment Info Header Row -->
                    <div class="p-6 border-b flex items-center justify-between bg-card/25 gap-4">
                            <input 
                                v-model="envName"
                                placeholder="Environment Name" 
                                class="w-full h-10 text-lg font-semibold bg-transparent border-none outline-none focus:outline-none focus:ring-0 placeholder:text-muted-foreground/30 text-foreground px-0 shadow-none" 
                            />
                        <div class="flex items-center gap-2">
                            <Button 
                                :variant="store.activeEnvironment?.id === activeEnv.id ? 'default' : 'outline'" 
                                size="sm" 
                                :class="[
                                    'h-9 px-3 text-xs gap-1.5 transition-all duration-200',
                                    store.activeEnvironment?.id === activeEnv.id 
                                        ? 'bg-primary text-primary-foreground hover:bg-primary/90 shadow-sm border-transparent' 
                                        : 'text-muted-foreground hover:text-foreground hover:bg-accent border-input/60'
                                ]"
                                @click="store.setActiveEnvironment(store.activeEnvironment?.id === activeEnv.id ? null : activeEnv)"
                            >
                                <Check class="h-3.5 w-3.5" :class="store.activeEnvironment?.id === activeEnv.id ? 'text-primary-foreground' : 'text-primary'" />
                                <span>{{ store.activeEnvironment?.id === activeEnv.id ? 'Active' : 'Set Active' }}</span>
                            </Button>
                            <Button 
                                variant="default" 
                                size="sm" 
                                class="h-9 px-4 text-xs gap-1.5"
                                :disabled="isSaving"
                                @click="saveEnvironmentDetails"
                            >
                                <Save class="h-3.5 w-3.5" v-if="!isSaving" />
                                <span v-else class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-background border-t-transparent" />
                                Save
                            </Button>
                            <Button 
                                variant="outline" 
                                size="sm" 
                                class="h-9 px-3 text-xs gap-1.5 border-destructive/20 text-destructive hover:bg-destructive/10 hover:text-destructive shrink-0"
                                @click="deleteActiveEnvironment"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                                Delete
                            </Button>
                        </div>
                    </div>

                    <!-- Variables Interactive Table -->
                    <div class="flex-1 flex flex-col overflow-hidden">
                        <div class="flex items-center justify-between px-6 py-4 bg-muted/20 shrink-0 border-b">
                            <div class="flex items-center gap-2">
                                <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Environment Variables</h3>
                                <span class="text-[10px] bg-primary/10 text-primary border border-primary/20 px-1.5 py-0.5 rounded font-mono">
                                    {{ localVariables.filter(v => v.key).length }} Active
                                </span>
                            </div>
                            <span class="text-[10px] text-muted-foreground flex items-center gap-1">
                                <AlertCircle class="h-3.5 w-3.5 text-muted-foreground/70" />
                                Reference in requests using <code class="bg-muted px-1 py-0.5 rounded text-[10px] font-mono text-primary font-semibold">&#123;variable_name&#125;</code>
                            </span>
                        </div>

                        <ScrollArea class="flex-1">
                            <div class="p-6">
                                <div class="border rounded-lg overflow-hidden bg-card/30">
                                    <table class="w-full border-collapse">
                                        <thead>
                                            <tr class="bg-muted/40 border-b text-[11px] text-muted-foreground uppercase font-semibold">
                                                <th class="w-12 text-center py-2.5 px-3 border-r"></th>
                                                <th class="text-left py-2.5 px-4 border-r w-1/3">Variable Key</th>
                                                <th class="text-left py-2.5 px-4 border-r">Value</th>
                                                <th class="w-20 text-center py-2.5 px-3">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr 
                                                v-for="(variable, index) in localVariables" 
                                                :key="index"
                                                class="border-b last:border-b-0 hover:bg-muted/10 transition-colors group"
                                            >
                                                <!-- Checkbox selector / Enable variable -->
                                                <td class="text-center py-2 px-3 border-r">
                                                    <Checkbox 
                                                        :checked="variable.enabled"
                                                        @update:checked="toggleVariableEnabled(index)"
                                                        class="h-3.5 w-3.5 rounded border-muted-foreground/30 focus-visible:ring-1 focus-visible:ring-offset-0 shrink-0"
                                                        :disabled="!variable.key"
                                                    />
                                                </td>

                                                <!-- Variable Key input -->
                                                <td class="p-0 border-r">
                                                    <input 
                                                        v-model="variable.key"
                                                        @input="handleVariableInput"
                                                        placeholder="Add variable key"
                                                        class="w-full h-10 px-4 text-xs bg-transparent border-none shadow-none outline-none focus:ring-0 focus:outline-none font-mono text-foreground placeholder:text-muted-foreground/40"
                                                    />
                                                </td>

                                                <!-- Variable Value input -->
                                                <td class="p-0 border-r">
                                                    <input 
                                                        v-model="variable.value"
                                                        @input="handleVariableInput"
                                                        placeholder="Value"
                                                        class="w-full h-10 px-4 text-xs bg-transparent border-none shadow-none outline-none focus:ring-0 focus:outline-none font-mono text-foreground placeholder:text-muted-foreground/40"
                                                    />
                                                </td>

                                                <!-- Actions -->
                                                <td class="text-center py-2 px-3">
                                                    <div class="flex items-center justify-center gap-1">
                                                        <Button 
                                                            variant="ghost" 
                                                            size="icon" 
                                                            class="h-7 w-7 rounded-md text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors"
                                                            @click="removeVariable(index)"
                                                            :disabled="index === localVariables.length - 1 && !variable.key"
                                                            title="Delete"
                                                        >
                                                            <Trash2 class="h-3.5 w-3.5" />
                                                        </Button>
                                                        <DropdownMenu v-if="variable.key">
                                                            <DropdownMenuTrigger as-child>
                                                                <Button 
                                                                    variant="ghost" 
                                                                    size="icon" 
                                                                    class="h-7 w-7 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
                                                                >
                                                                    <MoreVertical class="h-3.5 w-3.5" />
                                                                </Button>
                                                            </DropdownMenuTrigger>
                                                            <DropdownMenuContent align="end">
                                                                <DropdownMenuItem @click="openReplaceDialog(variable)" class="whitespace-nowrap">
                                                                    <SearchCode class="mr-2 h-4 w-4" />
                                                                    Replace in Collection
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
                    <div class="flex-1 flex flex-col items-center justify-center p-8 bg-card/10">
                        <div class="h-16 w-16 rounded-2xl bg-muted/40 flex items-center justify-center mb-4 border border-dashed border-muted-foreground/30">
                            <SlidersHorizontal class="h-7 w-7 text-muted-foreground/70" />
                        </div>
                        <h2 class="text-sm font-semibold text-foreground mb-1">Manage Workspace Environments</h2>
                        <p class="text-xs text-muted-foreground text-center max-w-sm mb-6 leading-relaxed">
                            Create variables to securely store settings like server URLs, authentication tokens, and keys. Use single braces to swap values inside requests.
                        </p>
                        <Button 
                            variant="default" 
                            size="sm" 
                            class="h-9 px-4 text-xs gap-1.5"
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
                        Search for the value <code class="bg-muted px-1 py-0.5 rounded text-xs font-mono text-primary">{{ replaceVariable?.value }}</code> in a collection and replace it with <code class="bg-muted px-1 py-0.5 rounded text-xs font-mono text-primary">&#123;{{ replaceVariable?.key }}&#125;</code>.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4 py-4">
                    <div class="space-y-2">
                        <label class="text-xs font-semibold text-foreground">Select Collection</label>
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
                    <Button variant="outline" @click="showReplaceDialog = false">Cancel</Button>
                    <Button 
                        :disabled="!replaceCollectionId || isReplacing"
                        @click="executeReplace"
                    >
                        <SearchCode class="mr-2 h-4 w-4" v-if="!isReplacing" />
                        <span v-else class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-background border-t-transparent" />
                        Replace All
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
</template>
