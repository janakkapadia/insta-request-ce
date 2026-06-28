<script setup lang="ts">
import { VueMonacoEditor } from '@guolao/vue-monaco-editor';
import { Head, router } from '@inertiajs/vue3';
import { Trash2, Calendar, Clock, Database, User, ArrowRight, ExternalLink, Loader2 } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription } from '@/components/ui/sheet';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import history from '@/routes/history';
import type { Team } from '@/types';

interface HistoryRecord {
    id: string;
    team_id: number;
    user_id: number;
    request_id: string | null;
    method: string;
    url: string;
    status: number;
    time_ms: number;
    size_bytes: number;
    request_payload: {
        headers: Array<{key: string, value: string, enabled?: boolean}>;
        query_params: Array<{key: string, value: string, enabled?: boolean}>;
        body: any;
    };
    response_meta: {
        status_text?: string;
        headers?: Record<string, string[]>;
        body?: string;
    };
    created_at: string;
    user: {
        id: number;
        name: string;
        email: string;
    };
}

interface PaginatedHistory {
    data: HistoryRecord[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    total: number;
}

const props = defineProps<{
    currentTeam?: Team;
    history: PaginatedHistory;
}>();

const records = ref<HistoryRecord[]>([...props.history.data]);
const currentPage = ref(props.history.current_page);
const lastPage = ref(props.history.last_page);
const hasMore = computed(() => currentPage.value < lastPage.value);
const loadingMore = ref(false);

const selectedRecord = ref<HistoryRecord | null>(null);

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => {
        return {
            breadcrumbs: [
                {
                    title: 'History',
                    href: '#',
                },
            ],
        };
    },
});

const showClearConfirm = ref(false);

const handleClearHistory = () => {
    showClearConfirm.value = true;
};

const confirmClearHistory = () => {
    router.delete(history.destroy().url, {
        onFinish: () => {
            showClearConfirm.value = false;
            records.value = [];
            currentPage.value = 1;
            lastPage.value = 1;
            selectedIds.value = [];
        }
    });
};

const selectedIds = ref<string[]>([]);
const showDeleteSelectedConfirm = ref(false);

const isAllSelected = computed(() => {
    return records.value.length > 0 && selectedIds.value.length === records.value.length;
});

const isPartiallySelected = computed(() => {
    return selectedIds.value.length > 0 && selectedIds.value.length < records.value.length;
});

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedIds.value = records.value.map(r => r.id);
    } else {
        selectedIds.value = [];
    }
};

const toggleSelectRecord = (id: string) => {
    const idx = selectedIds.value.indexOf(id);

    if (idx > -1) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(id);
    }
};

const handleDeleteSelected = () => {
    showDeleteSelectedConfirm.value = true;
};

const confirmDeleteSelected = () => {
    router.delete(history.destroy().url, {
        data: {
            ids: selectedIds.value
        },
        onFinish: () => {
            showDeleteSelectedConfirm.value = false;
            records.value = records.value.filter(r => !selectedIds.value.includes(r.id));
            selectedIds.value = [];
        }
    });
};

const loadMore = () => {
    if (!hasMore.value || loadingMore.value) {
return;
}

    loadingMore.value = true;
    const nextPage = currentPage.value + 1;
    router.visit(history.index({ query: { page: nextPage } }).url, {
        preserveState: true,
        preserveScroll: true,
        only: ['history'],
        onSuccess: (page) => {
            const paginated = (page.props as any).history as PaginatedHistory;
            records.value.push(...paginated.data);
            currentPage.value = paginated.current_page;
            lastPage.value = paginated.last_page;
            loadingMore.value = false;
        },
        onError: () => {
            loadingMore.value = false;
        },
    });
};

import { getMethodBadgeColors as getMethodColor } from '@/lib/method-colors';

const getStatusColor = (status: number) => {
    if (status === 0) {
return 'text-muted-foreground bg-muted';
}

    if (status >= 200 && status < 300) {
return 'text-emerald-500 bg-emerald-500/10';
}

    if (status >= 300 && status < 400) {
return 'text-amber-500 bg-amber-500/10';
}

    return 'text-rose-500 bg-rose-500/10';
};

const getInitials = (name: string) => {
    return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
};

const formatDate = (dateStr: string) => {
    const date = new Date(dateStr);

    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const formattedRequestBody = computed(() => {
    if (!selectedRecord.value || !selectedRecord.value.request_payload.body) {
return '';
}

    const bodyStr = selectedRecord.value.request_payload.body;
    let bodyObj;
    
    if (typeof bodyStr === 'string') {
        try {
            bodyObj = JSON.parse(bodyStr);
        } catch (e) {
            return bodyStr;
        }
    } else {
        bodyObj = bodyStr;
    }

    // Check if it's our internal bodyConfig format
    if (bodyObj && typeof bodyObj === 'object' && 'mode' in bodyObj) {
        if (bodyObj.mode === 'none') {
            return '';
        } else if (bodyObj.mode === 'raw') {
            if (bodyObj.raw?.language === 'json') {
                try {
                    return JSON.stringify(JSON.parse(bodyObj.raw.content), null, 2);
                } catch {
                    return bodyObj.raw.content || '';
                }
            }

            return bodyObj.raw?.content || '';
        } else if (bodyObj.mode === 'formdata') {
            const formDataStr = bodyObj.formdata
                ?.filter((f: any) => f.enabled && f.key)
                ?.map((f: any) => `${f.key}: ${f.type === 'file' ? '[File]' : f.value}`)
                .join('\n');

            return formDataStr || '';
        } else if (bodyObj.mode === 'urlencoded') {
            const urlEncodedStr = bodyObj.urlencoded
                ?.filter((u: any) => u.enabled && u.key)
                ?.map((u: any) => `${u.key}: ${u.value}`)
                .join('\n');

            return urlEncodedStr || '';
        } else if (bodyObj.mode === 'graphql') {
            const gqlObj = {
                query: bodyObj.graphql?.query || '',
                variables: bodyObj.graphql?.variables ? JSON.parse(bodyObj.graphql.variables) : {}
            };

            return JSON.stringify(gqlObj, null, 2);
        }
    }

    // Fallback if not our known format
    return JSON.stringify(bodyObj, null, 2);
});

// Format payload headers/params as formatted JSON for editor if they're complex
const formattedRequestDetails = computed(() => {
    if (!selectedRecord.value) {
return '';
}

    return JSON.stringify({
        headers: selectedRecord.value.request_payload.headers || [],
        query_params: selectedRecord.value.request_payload.query_params || [],
    }, null, 2);
});

const formattedResponseBody = computed(() => {
    if (!selectedRecord.value || !selectedRecord.value.response_meta.body) {
return '';
}

    const body = selectedRecord.value.response_meta.body;

    try {
        return JSON.stringify(JSON.parse(body), null, 2);
    } catch (e) {
        return body;
    }
});

const isHtmlResponse = computed(() => {
    if (!selectedRecord.value || !selectedRecord.value.response_meta) {
return false;
}
    
    const body = selectedRecord.value.response_meta.body;

    const headers = selectedRecord.value.response_meta.headers;
    let isHtmlContentType = false;

    if (headers) {
        for (const key in headers) {
            if (key.toLowerCase() === 'content-type') {
                const val = headers[key];

                if (Array.isArray(val) && val.some(v => typeof v === 'string' && v.toLowerCase().includes('text/html'))) {
isHtmlContentType = true;
}

                if (typeof val === 'string' && val.toLowerCase().includes('text/html')) {
isHtmlContentType = true;
}
            }
        }
    }

    if (typeof body === 'string') {
        const trimmed = body.trim();

        // If content type says HTML, or body strongly looks like HTML and not JSON
        if (isHtmlContentType || trimmed.toLowerCase().startsWith('<!doctype html>') || trimmed.toLowerCase().startsWith('<html')) {
            return true;
        }
    }
    
    return isHtmlContentType;
});
</script>

<template>
    <Head title="Request History" />
    <div class="flex flex-1 h-full flex-col min-h-0 overflow-hidden p-6 gap-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <h1 class="text-xl font-bold tracking-tight">Request History</h1>
                <p class="text-xs text-muted-foreground">
                    Logs of all API execution requests performed within the <strong>{{ currentTeam?.name || 'Workspace' }}</strong> team.
                </p>
            </div>
            <Button 
                v-if="records.length" 
                variant="destructive" 
                size="sm" 
                class="gap-2 text-xs" 
                @click="handleClearHistory"
            >
                <Trash2 class="w-3.5 h-3.5" />
                Clear Logs
            </Button>
        </div>

        <!-- Main Logs Content -->
        <div class="flex-1 min-h-0 border rounded-xl bg-background flex flex-col overflow-hidden">
            <!-- Header/Batch action bar -->
            <div v-if="records.length" class="flex items-center justify-between px-4 py-3 border-b bg-muted/20 shrink-0 text-xs text-muted-foreground">
                <div class="flex items-center gap-3">
                    <Checkbox
                        :checked="isAllSelected"
                        @update:checked="toggleSelectAll"
                        class="h-4 w-4 rounded border-muted-foreground/30 shrink-0"
                    />
                    <span v-if="selectedIds.length > 0" class="font-medium text-foreground">
                        {{ selectedIds.length }} items selected
                    </span>
                    <span v-else>
                        Select execution logs to delete
                    </span>
                </div>
                
                <div class="flex items-center gap-2">
                    <Button
                        v-if="selectedIds.length > 0"
                        variant="destructive"
                        size="sm"
                        class="h-7 text-[11px] gap-1 px-2.5"
                        @click="handleDeleteSelected"
                    >
                        <Trash2 class="w-3.5 h-3.5" />
                        Delete Selected
                    </Button>
                </div>
            </div>

            <ScrollArea class="flex-1">
                <template v-if="records.length">
                    <div class="divide-y divide-border/60">
                        <div 
                            v-for="record in records" 
                            :key="record.id"
                            class="group flex items-center justify-between p-4 hover:bg-muted/40 transition-colors cursor-pointer"
                            @click="selectedRecord = record"
                        >
                            <div class="flex items-center gap-4 min-w-0 flex-1">
                                <!-- Checkbox to multi-select -->
                                <Checkbox 
                                    :checked="selectedIds.includes(record.id)"
                                    @update:checked="(val) => toggleSelectRecord(record.id)"
                                    @click.stop
                                    class="h-4 w-4 rounded border-muted-foreground/30 shrink-0"
                                />

                                <!-- Status Code -->
                                <span :class="['text-[11px] font-bold px-2 py-0.5 rounded font-mono shrink-0', getStatusColor(record.status)]">
                                    {{ record.status || 'ERR' }}
                                </span>
                                
                                <!-- Method -->
                                <span :class="['text-[10px] font-bold px-2 py-0.5 rounded border shrink-0 font-mono', getMethodColor(record.method)]">
                                    {{ record.method }}
                                </span>

                                <!-- URL Path -->
                                <span class="text-xs font-mono font-medium truncate text-foreground/90 max-w-[40vw]">
                                    {{ record.url }}
                                </span>
                            </div>

                            <!-- Meta Details & Operator -->
                            <div class="flex items-center gap-6 text-[11px] text-muted-foreground shrink-0">
                                <!-- Latency -->
                                <span class="flex items-center gap-1 font-mono">
                                    <Clock class="w-3.5 h-3.5" />
                                    {{ record.time_ms }} ms
                                </span>

                                <!-- Payload Size -->
                                <span class="flex items-center gap-1 font-mono">
                                    <Database class="w-3.5 h-3.5" />
                                    {{ (record.size_bytes / 1024).toFixed(2) }} KB
                                </span>

                                <!-- Time badge -->
                                <span class="flex items-center gap-1">
                                    <Calendar class="w-3.5 h-3.5" />
                                    {{ formatDate(record.created_at) }}
                                </span>

                                <!-- Team User Initials -->
                                <div class="flex items-center gap-2 border-l pl-4">
                                    <Avatar class="w-5 h-5" :title="record.user.name">
                                        <AvatarFallback class="text-[9px] font-bold bg-muted-foreground/15 text-muted-foreground">
                                            {{ getInitials(record.user.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span class="max-w-[80px] truncate text-xs">{{ record.user.name.split(' ')[0] }}</span>
                                </div>

                                <ArrowRight class="w-4 h-4 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity" />
                            </div>
                        </div>
                    </div>

                    <!-- Load More Button -->
                    <div v-if="hasMore" class="flex justify-center py-4 border-t border-border/40">
                        <Button 
                            variant="outline" 
                            size="sm" 
                            class="gap-2 text-xs" 
                            :disabled="loadingMore"
                            @click="loadMore"
                        >
                            <Loader2 v-if="loadingMore" class="w-3.5 h-3.5 animate-spin" />
                            {{ loadingMore ? 'Loading...' : 'Load More' }}
                        </Button>
                    </div>
                </template>
                <div v-else class="h-[50vh] flex flex-col items-center justify-center text-center p-8">
                    <div class="w-12 h-12 rounded-2xl bg-muted/40 flex items-center justify-center text-muted-foreground mb-4">
                        <Trash2 class="w-6 h-6" />
                    </div>
                    <h3 class="text-sm font-semibold">No Execution Logs Yet</h3>
                    <p class="text-xs text-muted-foreground max-w-sm mt-1">
                        Any API requests sent from the Collections editor will be safely recorded here for auditing and fast reviews.
                    </p>
                </div>
            </ScrollArea>
        </div>

        <!-- Detail Sheet / Sidebar Panel -->
        <Sheet :open="!!selectedRecord" @update:open="selectedRecord = null">
            <SheetContent side="right" class="w-[85vw] sm:w-[500px] md:w-[650px] p-0 flex flex-col h-full bg-background border-l">
                <div v-if="selectedRecord" class="flex-1 flex flex-col h-full overflow-hidden">
                    <!-- Sheet Header -->
                    <div class="p-6 border-b space-y-3">
                        <div class="flex items-center justify-between">
                            <span :class="['text-xs font-bold px-2 py-0.5 rounded font-mono', getStatusColor(selectedRecord.status)]">
                                STATUS: {{ selectedRecord.status || 'ERROR' }} {{ selectedRecord.response_meta.status_text }}
                            </span>
                            <span class="text-xs text-muted-foreground font-mono flex items-center gap-1.5">
                                <Clock class="w-3.5 h-3.5" /> {{ selectedRecord.time_ms }} ms
                            </span>
                        </div>
                        
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span :class="['text-[11px] font-bold px-2 py-0.5 rounded border font-mono', getMethodColor(selectedRecord.method)]">
                                    {{ selectedRecord.method }}
                                </span>
                                <span class="text-xs font-mono font-semibold truncate flex-1">{{ selectedRecord.url }}</span>
                            </div>
                            <p class="text-[10px] text-muted-foreground flex items-center gap-1 pt-1">
                                <User class="w-3 h-3 text-primary" /> Executed by {{ selectedRecord.user.name }} ({{ selectedRecord.user.email }}) on {{ formatDate(selectedRecord.created_at) }}
                            </p>
                        </div>
                    </div>

                    <!-- Details Tabs -->
                    <div class="flex-1 flex flex-col min-h-0">
                        <Tabs default-value="request" class="flex-1 flex flex-col h-full min-h-0">
                            <div class="px-6 pt-2 pb-0">
                                <TabsList class="w-full h-auto flex-wrap justify-start bg-muted/50">
                                    <TabsTrigger value="request" class="text-xs flex-1 whitespace-nowrap">Req Headers</TabsTrigger>
                                    <TabsTrigger value="req_body" class="text-xs flex-1 whitespace-nowrap">Req Body</TabsTrigger>
                                    <TabsTrigger value="res_headers" class="text-xs flex-1 whitespace-nowrap">Res Headers</TabsTrigger>
                                    <TabsTrigger value="res_body" class="text-xs flex-1 whitespace-nowrap">Res Body</TabsTrigger>
                                </TabsList>
                            </div>

                            <div class="flex-1 min-h-0 relative p-4 bg-muted/5">
                                <TabsContent value="request" class="m-0 h-full">
                                    <div class="h-full border rounded-md overflow-hidden bg-background">
                                        <ScrollArea class="h-full p-4">
                                            <!-- Headers -->
                                            <div class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Headers</div>
                                            <div v-if="selectedRecord.request_payload.headers?.length" class="space-y-3 font-mono text-[11px]">
                                                <div 
                                                    v-for="(header, idx) in selectedRecord.request_payload.headers" 
                                                    :key="idx"
                                                    class="pb-2 border-b border-border/50 last:border-0"
                                                >
                                                    <div class="font-semibold text-primary truncate">{{ header.key }}</div>
                                                    <div class="text-muted-foreground break-all mt-0.5">{{ header.value }}</div>
                                                </div>
                                            </div>
                                            <div v-else class="text-xs text-muted-foreground italic">No headers sent.</div>

                                            <!-- Query Params -->
                                            <div class="mt-5 pt-4 border-t border-border/50">
                                                <div class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Query Parameters</div>
                                                <div v-if="selectedRecord.request_payload.query_params?.length" class="space-y-3 font-mono text-[11px]">
                                                    <div 
                                                        v-for="(param, idx) in selectedRecord.request_payload.query_params" 
                                                        :key="idx"
                                                        class="pb-2 border-b border-border/50 last:border-0"
                                                    >
                                                        <div class="font-semibold text-primary truncate">{{ param.key }}</div>
                                                        <div class="text-muted-foreground break-all mt-0.5">{{ param.value }}</div>
                                                    </div>
                                                </div>
                                                <div v-else class="text-xs text-muted-foreground italic">No query parameters sent.</div>
                                            </div>
                                        </ScrollArea>
                                    </div>
                                </TabsContent>

                                <TabsContent value="req_body" class="m-0 h-full flex flex-col overflow-hidden border rounded-md">
                                    <div v-if="!formattedRequestBody" class="flex-1 flex items-center justify-center bg-background">
                                        <span class="text-xs text-muted-foreground italic">No request body sent.</span>
                                    </div>
                                    <div v-else class="flex-1 relative min-h-0 bg-background">
                                        <VueMonacoEditor
                                            :value="formattedRequestBody"
                                            theme="vs-dark"
                                            language="json"
                                            :options="{
                                                readOnly: true,
                                                minimap: { enabled: false },
                                                automaticLayout: true,
                                                scrollBeyondLastLine: false,
                                            }"
                                        />
                                    </div>
                                </TabsContent>

                                <TabsContent value="res_headers" class="m-0 h-full">
                                    <div class="h-full border rounded-md overflow-hidden bg-background">
                                        <ScrollArea class="h-full p-4">
                                            <div v-if="selectedRecord.response_meta.headers && Object.keys(selectedRecord.response_meta.headers).length" class="space-y-3 font-mono text-[11px]">
                                                <div 
                                                    v-for="(vals, key) in selectedRecord.response_meta.headers" 
                                                    :key="key"
                                                    class="pb-2 border-b border-border/50 last:border-0"
                                                >
                                                    <div class="font-semibold text-primary truncate">{{ key }}</div>
                                                    <div class="text-muted-foreground break-all mt-0.5">{{ vals.join(', ') }}</div>
                                                </div>
                                            </div>
                                            <div v-else class="text-xs text-muted-foreground italic">No headers present.</div>
                                        </ScrollArea>
                                    </div>
                                </TabsContent>
                                
                                <TabsContent value="res_body" class="m-0 h-full flex flex-col overflow-hidden border rounded-md">
                                    <div v-if="!selectedRecord.response_meta.body" class="flex-1 flex items-center justify-center bg-background">
                                        <span class="text-xs text-muted-foreground italic">No response body captured.</span>
                                    </div>
                                    <div v-else-if="isHtmlResponse" class="flex-1 relative min-h-0 bg-white">
                                        <iframe
                                            :key="selectedRecord.id"
                                            class="w-full h-full border-none"
                                            :srcdoc="selectedRecord.response_meta.body"
                                            sandbox="allow-same-origin"
                                        ></iframe>
                                    </div>
                                    <div v-else class="flex-1 relative min-h-0 bg-background">
                                        <VueMonacoEditor
                                            :value="formattedResponseBody"
                                            theme="vs-dark"
                                            language="json"
                                            :options="{
                                                readOnly: true,
                                                minimap: { enabled: false },
                                                automaticLayout: true,
                                                scrollBeyondLastLine: false,
                                                wordWrap: 'on'
                                            }"
                                        />
                                    </div>
                                </TabsContent>
                            </div>
                        </Tabs>
                    </div>

                    <!-- Footer Action -->
                    <div class="p-4 border-t bg-muted/15 flex justify-end">
                        <Button size="sm" variant="outline" class="gap-1.5 text-xs" @click="selectedRecord = null">
                            Close Details
                        </Button>
                    </div>
                </div>
            </SheetContent>
        </Sheet>

        <!-- Clear History Confirmation Dialog -->
        <Dialog :open="showClearConfirm" @update:open="showClearConfirm = $event">
            <DialogContent class="sm:max-w-[420px]">
                <DialogHeader>
                    <DialogTitle>Clear Execution Logs</DialogTitle>
                    <DialogDescription class="text-xs">
                        Are you sure you want to clear all request execution history? This action is permanent and cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex gap-2 sm:justify-end mt-4">
                    <DialogClose as-child>
                        <Button variant="secondary" size="sm">Cancel</Button>
                    </DialogClose>
                    <Button variant="destructive" size="sm" @click="confirmClearHistory">
                        Clear Logs
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Selected Confirmation Dialog -->
        <Dialog :open="showDeleteSelectedConfirm" @update:open="showDeleteSelectedConfirm = $event">
            <DialogContent class="sm:max-w-[420px]">
                <DialogHeader>
                    <DialogTitle>Delete Selected Logs</DialogTitle>
                    <DialogDescription class="text-xs">
                        Are you sure you want to delete the {{ selectedIds.length }} selected request execution history items? This action is permanent and cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex gap-2 sm:justify-end mt-4">
                    <DialogClose as-child>
                        <Button variant="secondary" size="sm">Cancel</Button>
                    </DialogClose>
                    <Button variant="destructive" size="sm" @click="confirmDeleteSelected">
                        Delete Selected
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
