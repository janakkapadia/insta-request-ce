<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    Download,
    Loader2,
    CheckCircle2,
    XCircle,
    FileJson,
    FileText,
    FileCode,
    Globe,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useWorkspaceStore } from '@/stores/workspace';
import type { ExportFormat } from '@/types';

const props = defineProps<{
    open: boolean;
    preselectedCollectionId?: string;
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
}>();

const store = useWorkspaceStore();

const selectedFormat = ref<ExportFormat>('postman_v2');
const selectedCollectionId = ref(props.preselectedCollectionId || '');
const error = ref('');
const success = ref(false);

const form = useForm({
    collection_id: '',
    format: '' as ExportFormat,
});

const collectionOptions = computed(() =>
    store.collections.map((c) => ({ label: c.name, value: c.id })),
);

const formats: Array<{
    value: ExportFormat;
    label: string;
    desc: string;
    icon: typeof FileJson;
}> = [
    {
        value: 'postman_v2',
        label: 'Postman v2',
        desc: 'Postman Collection v2.1 JSON',
        icon: FileJson,
    },
    {
        value: 'openapi_3',
        label: 'OpenAPI 3.0',
        desc: 'OpenAPI specification JSON',
        icon: Globe,
    },
    {
        value: 'curl',
        label: 'cURL',
        desc: 'cURL commands text file',
        icon: FileCode,
    },
    { value: 'har', label: 'HAR', desc: 'HTTP Archive format', icon: FileText },
];

const handleExport = () => {
    if (!selectedCollectionId.value) {
        return;
    }

    error.value = '';
    success.value = false;

    form.collection_id = selectedCollectionId.value;
    form.format = selectedFormat.value;

    form.post('/export', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            success.value = true;
        },
        onError: (errors) => {
            error.value =
                errors.error || Object.values(errors)[0] || 'Export failed';
        },
    });
};

const close = () => {
    emit('update:open', false);
    setTimeout(() => {
        error.value = '';
        success.value = false;
    }, 300);
};
</script>

<template>
    <Dialog
        :open="open"
        @update:open="(val: boolean) => (val ? null : close())"
    >
        <DialogContent class="sm:max-w-[480px]">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-base">
                    <Download class="h-4 w-4 text-primary" />
                    Export Collection
                </DialogTitle>
                <DialogDescription class="text-xs text-muted-foreground">
                    Export to Postman, OpenAPI, cURL, or HAR format
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-5 py-2">
                <!-- Collection selector -->
                <div>
                    <label
                        class="mb-1.5 block text-xs font-medium text-muted-foreground"
                        >Collection</label
                    >
                    <Select v-model="selectedCollectionId">
                        <SelectTrigger class="h-8 text-xs">
                            <SelectValue placeholder="Select a collection..." />
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

                <!-- Format cards -->
                <div>
                    <label
                        class="mb-2 block text-xs font-medium text-muted-foreground"
                        >Format</label
                    >
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="fmt in formats"
                            :key="fmt.value"
                            class="flex items-center gap-2.5 rounded-lg border px-3 py-2.5 text-left transition-all hover:bg-muted/30"
                            :class="
                                selectedFormat === fmt.value
                                    ? 'border-primary bg-primary/5 ring-1 ring-primary/30'
                                    : 'border-border'
                            "
                            @click="selectedFormat = fmt.value"
                        >
                            <component
                                :is="fmt.icon"
                                class="h-4 w-4 shrink-0"
                                :class="
                                    selectedFormat === fmt.value
                                        ? 'text-primary'
                                        : 'text-muted-foreground'
                                "
                            />
                            <div class="min-w-0">
                                <div class="truncate text-xs font-medium">
                                    {{ fmt.label }}
                                </div>
                                <div
                                    class="truncate text-[10px] text-muted-foreground"
                                >
                                    {{ fmt.desc }}
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Status -->
                <div
                    v-if="error"
                    class="flex items-center gap-2 rounded-md border border-red-500/20 bg-red-500/10 px-3 py-2 text-xs text-red-500"
                >
                    <XCircle class="h-3.5 w-3.5 shrink-0" />
                    {{ error }}
                </div>

                <div
                    v-if="success"
                    class="flex items-center gap-2 rounded-md border border-green-500/20 bg-green-500/10 px-3 py-2 text-xs text-green-600"
                >
                    <CheckCircle2 class="h-3.5 w-3.5 shrink-0" />
                    Export downloaded successfully!
                </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-end gap-2 pt-2">
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 text-xs"
                    @click="close"
                >
                    Cancel
                </Button>
                <Button
                    size="sm"
                    class="h-8 text-xs"
                    :disabled="form.processing || !selectedCollectionId"
                    @click="handleExport"
                >
                    <Loader2
                        v-if="form.processing"
                        class="mr-1 h-3 w-3 animate-spin"
                    />
                    <Download v-else class="mr-1 h-3 w-3" />
                    Export
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
