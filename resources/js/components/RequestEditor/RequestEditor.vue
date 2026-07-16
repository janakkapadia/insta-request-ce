<script setup lang="ts">
import { VueMonacoEditor } from '@guolao/vue-monaco-editor';
import axios from 'axios';
import {
    Save,
    Sparkles,
    Terminal,
    Trash2,
    Loader2,
    Edit3,
    Eye,
} from 'lucide-vue-next';
import {
    ref,
    watch,
    shallowRef,
    nextTick,
    computed,
    onMounted,
    onUnmounted,
} from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    HoverCard,
    HoverCardContent,
    HoverCardTrigger,
} from '@/components/ui/hover-card';
import { Input } from '@/components/ui/input';
import {
    ResizableHandle,
    ResizablePanel,
    ResizablePanelGroup,
} from '@/components/ui/resizable';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import VariableInput from '@/components/VariableInput.vue';
import { parseMarkdown } from '@/lib/markdown';
import { getMethodTextColor as getMethodColor } from '@/lib/method-colors';
import { useWorkspaceStore } from '@/stores/workspace';

const store = useWorkspaceStore();

// ── Monaco theme: track the `dark` class on <html> ──────────────────────────
const isDark = ref(false);
const monacoTheme = computed(() => (isDark.value ? 'vs-dark' : 'vs'));

const isHtmlResponse = computed(() => {
    let isHtmlContentType = false;

    if (responseHeaders.value) {
        for (const key in responseHeaders.value) {
            if (key.toLowerCase() === 'content-type') {
                const val = responseHeaders.value[key] as any;

                if (
                    Array.isArray(val) &&
                    val.some(
                        (v) =>
                            typeof v === 'string' &&
                            v.toLowerCase().includes('text/html'),
                    )
                ) {
                    isHtmlContentType = true;
                }

                if (
                    typeof val === 'string' &&
                    val.toLowerCase().includes('text/html')
                ) {
                    isHtmlContentType = true;
                }
            }
        }
    }

    if (typeof responseBody.value === 'string') {
        const trimmed = responseBody.value.trim();

        if (
            isHtmlContentType ||
            trimmed.toLowerCase().startsWith('<!doctype html>') ||
            trimmed.toLowerCase().startsWith('<html')
        ) {
            return true;
        }
    }

    return isHtmlContentType;
});

let themeObserver: MutationObserver | null = null;

const handleBeforeUnload = (e: BeforeUnloadEvent) => {
    if (Object.keys(store.requestDrafts).length > 0) {
        e.preventDefault();
        e.returnValue = '';
    }
};

onMounted(() => {
    isDark.value =
        typeof document !== 'undefined' &&
        document.documentElement.classList.contains('dark');
    themeObserver = new MutationObserver(() => {
        isDark.value = document.documentElement.classList.contains('dark');
    });
    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });

    window.addEventListener('beforeunload', handleBeforeUnload);
});
onUnmounted(() => {
    themeObserver?.disconnect();
    window.removeEventListener('beforeunload', handleBeforeUnload);
});

const method = ref('GET');
const activeRequestTab = ref('params');

const requestHistoryItems = ref<any[]>([]);
const isFetchingHistory = ref(false);
const historyFetchedForRequestId = ref<string | null>(null);

const fetchRequestHistory = async () => {
    const currentId = store.selectedRequest?.id;

    if (!currentId) {
        return;
    }

    if (
        historyFetchedForRequestId.value === currentId &&
        requestHistoryItems.value.length > 0
    ) {
        return;
    }

    try {
        isFetchingHistory.value = true;
        const res = await axios.get('/requests/history', {
            params: { request_id: currentId },
        });
        requestHistoryItems.value = res.data;
        historyFetchedForRequestId.value = currentId;
    } catch (e) {
        console.error('Failed to fetch request history', e);
    } finally {
        isFetchingHistory.value = false;
    }
};

const graphPoints = computed(() => {
    const items = requestHistoryItems.value.slice(0, 20).reverse();

    if (items.length === 0) {
        return [];
    }

    const maxTime = Math.max(...items.map((i) => i.time_ms));
    const padding = 10; // 10% padding top and bottom

    return items.map((item, i, arr) => {
        const x = arr.length > 1 ? ((i + 0.5) / arr.length) * 100 : 50;
        const normalizedVal = maxTime > 0 ? item.time_ms / maxTime : 0;
        const y = 100 - (padding + normalizedVal * (100 - 2 * padding));

        return { x, y, ms: item.time_ms, item };
    });
});

const generateLinePath = (points: any[]) => {
    if (points.length === 0) {
        return '';
    }

    if (points.length === 1) {
        return `M 0,${points[0].y} L 100,${points[0].y}`;
    }

    let d = `M ${points[0].x},${points[0].y}`;

    for (let i = 0; i < points.length - 1; i++) {
        const p1 = points[i];
        const p2 = points[i + 1];
        const cp1x = (p1.x + p2.x) / 2;
        d += ` C ${cp1x},${p1.y} ${cp1x},${p2.y} ${p2.x},${p2.y}`;
    }

    return d;
};

const graphLinePath = computed(() => generateLinePath(graphPoints.value));
const graphAreaPath = computed(() => {
    if (graphPoints.value.length === 0) {
        return '';
    }

    const points = graphPoints.value;
    const startX = points.length === 1 ? 0 : points[0].x;
    const endX = points.length === 1 ? 100 : points[points.length - 1].x;

    return `${graphLinePath.value} L ${endX},100 L ${startX},100 Z`;
});

const methodSupportsBody = computed(() => {
    return !['GET', 'DELETE', 'HEAD', 'OPTIONS'].includes(
        method.value.toUpperCase(),
    );
});

watch(method, (newMethod) => {
    if (
        ['GET', 'DELETE', 'HEAD', 'OPTIONS'].includes(newMethod.toUpperCase())
    ) {
        if (activeRequestTab.value === 'body') {
            activeRequestTab.value = 'params';
        }
    }
});

defineProps<{
    requestId?: string;
}>();
const url = ref('');
const editorRef = shallowRef();
const responseBody = ref('');
const responseMeta = ref<{
    status?: number;
    statusText?: string;
    time?: number;
    size?: number;
}>({});
const responseHeaders = ref<Record<string, string[]>>({});
const isExecuting = ref(false);
const isSaving = ref(false);

const handleAutoCurlImport = async (curlCmd: string) => {
    try {
        const uploadRes = await axios.post('/import/upload', {
            content: curlCmd,
            filename: 'curl-import.txt',
            format: 'curl',
        });

        const importRecord = uploadRes.data.import;

        const targetCollectionId =
            store.selectedRequest?.collection_id ||
            store.selectedCollection?.id;
        const targetFolderId = store.selectedRequest?.folder_id || null;

        if (!targetCollectionId) {
            toast.error('No collection selected to import into.');

            return;
        }

        await axios.post(`/import/${importRecord.id}/confirm`, {
            merge_strategy: 'merge_replace',
            target_collection_id: targetCollectionId,
            target_folder_id: targetFolderId,
        });

        await store.refreshCollections();
        toast.success('cURL imported successfully!');

        // Auto-select the newly imported request
        if (uploadRes.data.preview?.requests?.length > 0) {
            const firstParsedReq = uploadRes.data.preview.requests[0];
            const targetCollection = store.collections.find(
                (c) => c.id === targetCollectionId,
            );

            if (targetCollection) {
                let foundReq = null;

                if (targetFolderId) {
                    const folder = targetCollection.folders?.find(
                        (f) => f.id === targetFolderId,
                    );

                    if (folder) {
                        foundReq = folder.requests?.find(
                            (r) =>
                                r.name.toLowerCase() ===
                                    firstParsedReq.name.toLowerCase() &&
                                r.method.toUpperCase() ===
                                    firstParsedReq.method.toUpperCase(),
                        );
                    }
                } else {
                    foundReq = targetCollection.requests?.find(
                        (r) =>
                            r.name.toLowerCase() ===
                                firstParsedReq.name.toLowerCase() &&
                            r.method.toUpperCase() ===
                                firstParsedReq.method.toUpperCase(),
                    );
                }

                if (foundReq) {
                    store.selectRequest(foundReq);
                }
            }
        }
    } catch (e: any) {
        console.error('Failed to auto-import curl', e);
        const msg =
            e.response?.data?.error ||
            'Failed to parse and import cURL command';
        toast.error(msg);
    }
};

watch(url, (v, oldV) => console.log('URL CHANGED:', { from: oldV, to: v }));

watch(url, (newVal) => {
    if (newVal && newVal.trim().toLowerCase().startsWith('curl ')) {
        const curlCmd = newVal.trim();
        url.value = store.selectedRequest?.url || '';
        handleAutoCurlImport(curlCmd);

        return;
    }

    syncPathVariablesFromUrl(newVal);
});

const syncPathVariablesFromUrl = (newVal: string) => {
    // Extract path variables (e.g. :id, :user_id)
    const regex = /\/:([a-zA-Z0-9_-]+)/g;
    const matches = [...(newVal || '').matchAll(regex)].map((m) => m[1]);

    // Remove variables that are no longer in the URL
    const filteredVars = pathVariables.value.filter((v) =>
        matches.includes(v.key),
    );

    if (filteredVars.length !== pathVariables.value.length) {
        pathVariables.value = filteredVars;
    }

    // Add new variables that appeared in the URL
    matches.forEach((key) => {
        if (!pathVariables.value.find((v) => v.key === key)) {
            pathVariables.value.push({
                key,
                value: '',
                enabled: true,
                description: '',
            });
        }
    });
};

const description = ref('');
const activeDocTab = ref<'write' | 'preview'>('write');

const queryParams = ref<
    Array<{ key: string; value: string; enabled: boolean; description: string }>
>([{ key: '', value: '', enabled: true, description: '' }]);
const pathVariables = ref<
    Array<{ key: string; value: string; enabled: boolean; description: string }>
>([]);

const currentRequestId = ref<string | null>(null);
const headersList = ref<
    Array<{ key: string; value: string; enabled: boolean; description: string }>
>([{ key: '', value: '', enabled: true, description: '' }]);
const authConfig = ref({
    type: 'noauth',
    bearerToken: '',
    basicUsername: '',
    basicPassword: '',
    apiKeyKey: '',
    apiKeyValue: '',
    apiKeyAddTo: 'header',
});

const bodyConfig = ref({
    mode: 'none', // none, formdata, urlencoded, raw, graphql
    raw: {
        language: 'json',
        content: '',
    },
    formdata: [
        { key: '', value: '', type: 'text', enabled: true, description: '' },
    ],
    urlencoded: [{ key: '', value: '', enabled: true, description: '' }],
    graphql: {
        query: '',
        variables: '',
    },
});

const handleFormDataInput = (index: number) => {
    if (index === bodyConfig.value.formdata.length - 1) {
        const last = bodyConfig.value.formdata[index];

        if (last.key || last.value) {
            bodyConfig.value.formdata.push({
                key: '',
                value: '',
                type: 'text',
                enabled: true,
                description: '',
            });
        }
    }
};

const removeFormData = (index: number) => {
    bodyConfig.value.formdata.splice(index, 1);

    if (bodyConfig.value.formdata.length === 0) {
        bodyConfig.value.formdata.push({
            key: '',
            value: '',
            type: 'text',
            enabled: true,
            description: '',
        });
    }
};

const handleUrlEncodedInput = (index: number) => {
    if (index === bodyConfig.value.urlencoded.length - 1) {
        const last = bodyConfig.value.urlencoded[index];

        if (last.key || last.value) {
            bodyConfig.value.urlencoded.push({
                key: '',
                value: '',
                enabled: true,
                description: '',
            });
        }
    }
};

const removeUrlEncoded = (index: number) => {
    bodyConfig.value.urlencoded.splice(index, 1);

    if (bodyConfig.value.urlencoded.length === 0) {
        bodyConfig.value.urlencoded.push({
            key: '',
            value: '',
            enabled: true,
            description: '',
        });
    }
};

const activeSuggestion = ref<{
    type: 'param-key' | 'header-key' | 'header-value';
    index: number;
    filter: string;
} | null>(null);

const activeInputEl = ref<HTMLInputElement | null>(null);
const suggestionCoords = ref({ top: 0, left: 0, width: 0 });

const updateCoords = () => {
    if (!activeInputEl.value) {
        return;
    }

    const rect = activeInputEl.value.getBoundingClientRect();
    suggestionCoords.value = {
        top: rect.bottom + window.scrollY,
        left: rect.left + window.scrollX,
        width: rect.width,
    };
};

const handleSuggestionFocus = (
    event: FocusEvent,
    type: 'param-key' | 'header-key' | 'header-value',
    index: number,
    filter: string,
) => {
    activeInputEl.value = event.currentTarget as HTMLInputElement;
    activeSuggestion.value = { type, index, filter };
    updateCoords();
};

watch(
    () => activeSuggestion.value,
    (newVal) => {
        if (newVal) {
            window.addEventListener('scroll', updateCoords, true);
            window.addEventListener('resize', updateCoords);
        } else {
            window.removeEventListener('scroll', updateCoords, true);
            window.removeEventListener('resize', updateCoords);
        }
    },
);

const commonHeaders = [
    'Accept',
    'Accept-Charset',
    'Accept-Encoding',
    'Accept-Language',
    'Authorization',
    'Cache-Control',
    'Connection',
    'Content-Length',
    'Content-Type',
    'Cookie',
    'Host',
    'Origin',
    'Pragma',
    'Referer',
    'User-Agent',
    'X-Requested-With',
    'X-Forwarded-For',
    'X-Forwarded-Proto',
];

const headerValuesMap: Record<string, string[]> = {
    accept: ['application/json', 'application/xml', 'text/plain', 'text/html'],
    'accept-charset': ['utf-8', 'iso-8859-1', 'us-ascii', '*'],
    'content-type': [
        'application/json',
        'application/x-www-form-urlencoded',
        'application/xml',
        'text/plain',
        'text/html',
        'multipart/form-data',
    ],
    authorization: ['Bearer '],
    'accept-encoding': ['gzip, deflate, br'],
    connection: ['keep-alive', 'close'],
    'cache-control': ['no-cache', 'no-store', 'max-age=0'],
};

const commonQueryParams = [
    'page',
    'limit',
    'offset',
    'search',
    'sort',
    'filter',
    'fields',
    'q',
    'query',
    'id',
    'user_id',
    'order',
];
const filteredSuggestions = computed(() => {
    if (!activeSuggestion.value) {
        return [];
    }

    let list: string[] = [];

    if (activeSuggestion.value.type === 'header-key') {
        list = commonHeaders;
    } else if (activeSuggestion.value.type === 'header-value') {
        const key = (headersList.value[activeSuggestion.value.index]?.key || '')
            .toLowerCase()
            .trim();
        list = headerValuesMap[key] || [];
    } else if (activeSuggestion.value.type === 'param-key') {
        list = commonQueryParams;
    }

    const filter = activeSuggestion.value.filter.toLowerCase();

    if (!filter) {
        return list;
    }

    const matched = list.filter((item) => item.toLowerCase().includes(filter));

    // Hide dropdown if the typed key is already exactly one of the values
    if (matched.length === 1 && matched[0].toLowerCase() === filter) {
        return [];
    }

    return matched;
});

const selectSuggestion = (val: string) => {
    if (!activeSuggestion.value) {
        return;
    }

    const { type, index } = activeSuggestion.value;

    if (type === 'param-key') {
        queryParams.value[index].key = val;
        handleParamInput(index);
    } else if (type === 'header-key') {
        headersList.value[index].key = val;
        handleHeaderInput(index);
    } else if (type === 'header-value') {
        headersList.value[index].value = val;
    }

    activeSuggestion.value = null;
    activeInputEl.value = null;
};

const cleanPayloadStr = ref('');

const getCurrentPayloadStr = () => {
    const cleanParams = queryParams.value.filter((p) => p.key || p.value);
    const cleanHeaders = headersList.value.filter((h) => h.key || h.value);

    return JSON.stringify({
        method: method.value,
        url: url.value,
        description: description.value,
        bodyConfig: bodyConfig.value,
        queryParams: cleanParams,
        pathVariables: pathVariables.value,
        headersList: cleanHeaders,
        authConfig: authConfig.value,
    });
};

watch(
    [
        method,
        url,
        description,
        bodyConfig,
        queryParams,
        pathVariables,
        headersList,
        authConfig,
    ],
    () => {
        if (!isSwitchingRequest && currentRequestId.value) {
            const currentStr = getCurrentPayloadStr();
            const dirty =
                currentRequestId.value.startsWith('new-') ||
                currentStr !== cleanPayloadStr.value;
            store.setRequestDraft(currentRequestId.value, currentStr, dirty);
        }
    },
    { deep: true },
);

let isSwitchingRequest = false;

// Watch for request selection changes to update local inputs
watch(
    () => store.selectedRequest,
    (newReq) => {
        if (newReq) {
            const isNewRequest = currentRequestId.value !== newReq.id;

            if (isNewRequest) {
                currentRequestId.value = newReq.id;
                isSwitchingRequest = true;
                historyFetchedForRequestId.value = null;
                requestHistoryItems.value = [];
                method.value = newReq.method || 'GET';
                url.value = newReq.url || '';
                description.value = newReq.description || '';
                // Populate bodyConfig
                const parsedBody =
                    typeof newReq.body === 'string'
                        ? (() => {
                              try {
                                  return JSON.parse(newReq.body);
                              } catch {
                                  return null;
                              }
                          })()
                        : newReq.body;

                if (
                    parsedBody &&
                    typeof parsedBody === 'object' &&
                    'mode' in parsedBody
                ) {
                    bodyConfig.value = {
                        mode: parsedBody.mode || 'none',
                        raw: {
                            language: parsedBody.raw?.language || 'json',
                            content: parsedBody.raw?.content || '',
                        },
                        formdata:
                            Array.isArray(parsedBody.formdata) &&
                            parsedBody.formdata.length > 0
                                ? parsedBody.formdata
                                : [
                                      {
                                          key: '',
                                          value: '',
                                          type: 'text',
                                          enabled: true,
                                          description: '',
                                      },
                                  ],
                        urlencoded:
                            Array.isArray(parsedBody.urlencoded) &&
                            parsedBody.urlencoded.length > 0
                                ? parsedBody.urlencoded
                                : [
                                      {
                                          key: '',
                                          value: '',
                                          enabled: true,
                                          description: '',
                                      },
                                  ],
                        graphql: {
                            query: parsedBody.graphql?.query || '',
                            variables: parsedBody.graphql?.variables || '',
                        },
                    };
                } else {
                    // Legacy / fallback body
                    let bodyText = '';

                    if (
                        parsedBody &&
                        typeof parsedBody === 'object' &&
                        'text' in parsedBody
                    ) {
                        bodyText =
                            typeof parsedBody.text === 'string'
                                ? parsedBody.text
                                : JSON.stringify(parsedBody.text, null, 2);
                    } else if (newReq.body) {
                        bodyText =
                            typeof newReq.body === 'string'
                                ? newReq.body
                                : JSON.stringify(newReq.body, null, 2);
                    }

                    bodyConfig.value = {
                        mode: bodyText ? 'raw' : 'none',
                        raw: {
                            language: 'json',
                            content: bodyText,
                        },
                        formdata: [
                            {
                                key: '',
                                value: '',
                                type: 'text',
                                enabled: true,
                                description: '',
                            },
                        ],
                        urlencoded: [
                            {
                                key: '',
                                value: '',
                                enabled: true,
                                description: '',
                            },
                        ],
                        graphql: {
                            query: '',
                            variables: '',
                        },
                    };
                }

                // Populate queryParams
                if (
                    Array.isArray(newReq.query_params) &&
                    newReq.query_params.length > 0
                ) {
                    queryParams.value = [
                        ...newReq.query_params.map((p) => ({
                            key: p.key || '',
                            value: p.value || '',
                            enabled: p.enabled !== false,
                            description: p.description || '',
                        })),
                        { key: '', value: '', enabled: true, description: '' },
                    ];
                } else {
                    queryParams.value = [
                        { key: '', value: '', enabled: true, description: '' },
                    ];
                }

                // Sync queryParams from the URL only if the DB had no params stored
                // (avoids overwriting imported params that aren't embedded in the URL string)
                const dbHadParams =
                    Array.isArray(newReq.query_params) &&
                    newReq.query_params.length > 0;

                if (!dbHadParams) {
                    const queryPortion = (newReq.url || '').includes('?')
                        ? (newReq.url || '').split('?')[1]
                        : '';
                    const currentSerialized = getParamsQueryString();

                    if (queryPortion !== currentSerialized) {
                        parseParamsFromUrl(newReq.url || '');
                    }
                }

                // Populate pathVariables
                if (
                    Array.isArray(newReq.path_variables) &&
                    newReq.path_variables.length > 0
                ) {
                    pathVariables.value = newReq.path_variables.map((p) => ({
                        key: p.key || '',
                        value: p.value || '',
                        enabled: p.enabled !== false,
                        description: p.description || '',
                    }));
                } else {
                    pathVariables.value = [];
                }

                // Sync with URL immediately to prevent flickering on missing DB values
                syncPathVariablesFromUrl(newReq.url || '');

                // Populate headersList
                if (
                    Array.isArray(newReq.headers) &&
                    newReq.headers.length > 0
                ) {
                    headersList.value = [
                        ...newReq.headers.map((h) => ({
                            key: h.key || '',
                            value: h.value || '',
                            enabled: h.enabled !== false,
                            description: h.description || '',
                        })),
                        { key: '', value: '', enabled: true, description: '' },
                    ];
                } else {
                    headersList.value = [
                        {
                            key: 'Accept',
                            value: '*/*',
                            enabled: true,
                            description: 'Accept all content types',
                        },
                        {
                            key: 'User-Agent',
                            value: 'InstaRequest/1.0.0',
                            enabled: true,
                            description: 'Client user agent',
                        },
                        {
                            key: 'Accept-Encoding',
                            value: 'gzip, deflate, br',
                            enabled: true,
                            description: 'Supported encodings',
                        },
                        {
                            key: 'Connection',
                            value: 'keep-alive',
                            enabled: true,
                            description: 'Keep connection active',
                        },
                        { key: '', value: '', enabled: true, description: '' },
                    ];
                }

                // Populate authConfig
                if (newReq.auth && typeof newReq.auth === 'object') {
                    authConfig.value = {
                        type: newReq.auth.type || 'noauth',
                        bearerToken: newReq.auth.bearerToken || '',
                        basicUsername: newReq.auth.basicUsername || '',
                        basicPassword: newReq.auth.basicPassword || '',
                        apiKeyKey: newReq.auth.apiKeyKey || '',
                        apiKeyValue: newReq.auth.apiKeyValue || '',
                        apiKeyAddTo: newReq.auth.apiKeyAddTo || 'header',
                    };
                } else {
                    authConfig.value = {
                        type: 'noauth',
                        bearerToken: '',
                        basicUsername: '',
                        basicPassword: '',
                        apiKeyKey: '',
                        apiKeyValue: '',
                        apiKeyAddTo: 'header',
                    };
                }

                responseBody.value = '';
                responseHeaders.value = {};
                responseMeta.value = {};
                // (Body variable parsing logic would go here if needed in the future)

                cleanPayloadStr.value = getCurrentPayloadStr();

                const draftStr = store.getRequestDraft(newReq.id);

                if (draftStr) {
                    try {
                        const draft = JSON.parse(draftStr);
                        method.value = draft.method || 'GET';
                        url.value = draft.url || '';

                        if (draft.description !== undefined) {
                            description.value = draft.description;
                        }

                        if (draft.bodyConfig) {
                            bodyConfig.value = draft.bodyConfig;
                        }

                        if (draft.queryParams) {
                            queryParams.value = draft.queryParams;
                        }

                        if (draft.pathVariables) {
                            pathVariables.value = draft.pathVariables;
                        }

                        if (draft.headersList) {
                            headersList.value = draft.headersList;
                        }

                        if (draft.authConfig) {
                            authConfig.value = draft.authConfig;
                        }
                    } catch (e) {
                        console.error('Failed to parse draft', e);
                    }
                }

                nextTick(() => {
                    isSwitchingRequest = false;
                });
            } // end isNewRequest
        } else {
            currentRequestId.value = null;
        }
    },
    { immediate: true },
);

function getParamsQueryString() {
    return queryParams.value
        .filter((p) => p.key && p.enabled)
        .map(
            (p) =>
                `${encodeURIComponent(p.key)}=${encodeURIComponent(p.value)}`,
        )
        .join('&');
}

function updateUrlFromParams() {
    const baseUrl = url.value.split('?')[0];
    const queryString = getParamsQueryString();

    if (!queryString) {
        url.value = baseUrl;
    } else {
        url.value = `${baseUrl}?${queryString}`;
    }
}

function parseParamsFromUrl(newUrl: string) {
    if (!newUrl.includes('?')) {
        if (
            queryParams.value.length === 0 ||
            (queryParams.value.length === 1 && !queryParams.value[0].key)
        ) {
            return;
        }

        queryParams.value = [
            { key: '', value: '', enabled: true, description: '' },
        ];

        return;
    }

    const queryString = newUrl.split('?')[1];
    const searchParams = new URLSearchParams(queryString);
    const parsed: any[] = [];

    searchParams.forEach((value, key) => {
        parsed.push({ key, value, enabled: true, description: '' });
    });

    parsed.push({ key: '', value: '', enabled: true, description: '' });
    queryParams.value = parsed;
}

// Sync queryParams -> url
watch(
    queryParams,
    () => {
        if (isSwitchingRequest) {
            return;
        }

        const queryPortion = url.value.includes('?')
            ? url.value.split('?')[1]
            : '';
        const currentSerialized = getParamsQueryString();

        if (queryPortion !== currentSerialized) {
            updateUrlFromParams();
        }
    },
    { deep: true },
);

// Sync url -> queryParams
watch(url, (v, oldV) => console.log('URL CHANGED:', { from: oldV, to: v }));

watch(url, (newVal) => {
    if (isSwitchingRequest) {
        return;
    }

    const queryPortion = newVal.includes('?') ? newVal.split('?')[1] : '';
    const currentSerialized = getParamsQueryString();

    if (queryPortion !== currentSerialized) {
        parseParamsFromUrl(newVal);
    }
});

const handleParamInput = (index: number) => {
    if (index === queryParams.value.length - 1) {
        const last = queryParams.value[index];

        if (last.key || last.value) {
            queryParams.value.push({
                key: '',
                value: '',
                enabled: true,
                description: '',
            });
        }
    }
};

const removeParam = (index: number) => {
    queryParams.value.splice(index, 1);

    if (queryParams.value.length === 0) {
        queryParams.value.push({
            key: '',
            value: '',
            enabled: true,
            description: '',
        });
    }
};

const handleHeaderInput = (index: number) => {
    if (index === headersList.value.length - 1) {
        const last = headersList.value[index];

        if (last.key || last.value) {
            headersList.value.push({
                key: '',
                value: '',
                enabled: true,
                description: '',
            });
        }
    }
};

const removeHeader = (index: number) => {
    headersList.value.splice(index, 1);

    if (headersList.value.length === 0) {
        headersList.value.push({
            key: '',
            value: '',
            enabled: true,
            description: '',
        });
    }
};

const handleEditorMount = (editor: any) => {
    editorRef.value = editor;
};

const methods = [
    'GET',
    'POST',
    'PUT',
    'PATCH',
    'DELETE',
    'OPTIONS',
    'HEAD',
    'QUERY',
];

const handleFileChange = (e: Event, item: any) => {
    const target = e.target as HTMLInputElement;

    if (target && target.files && target.files.length > 0) {
        item.value = target.files[0].name;
    }
};

const handleSave = async () => {
    if (!store.selectedRequest) {
        return;
    }

    isSaving.value = true;

    try {
        const cleanParams = queryParams.value.filter((p) => p.key || p.value);
        const cleanHeaders = headersList.value.filter((h) => h.key || h.value);

        const modalOpened = await store.saveRequest({
            method: method.value,
            url: url.value,
            description: description.value,
            body: JSON.stringify(bodyConfig.value),
            query_params: cleanParams,
            path_variables: pathVariables.value,
            headers: cleanHeaders,
            auth: authConfig.value,
        });

        if (modalOpened) {
            return;
        }

        cleanPayloadStr.value = getCurrentPayloadStr();
    } finally {
        isSaving.value = false;
    }
};

const executeRequest = async () => {
    if (!url.value || !store.selectedRequest) {
        return;
    }

    isExecuting.value = true;

    try {
        let targetUrl = url.value;

        if (!/^https?:\/\//i.test(targetUrl) && !targetUrl.includes('{')) {
            targetUrl = 'http://' + targetUrl;
        }

        const cleanParams = queryParams.value.filter((p) => p.key || p.value);
        const cleanHeaders = headersList.value.filter((h) => h.key || h.value);

        const validRequestId =
            store.selectedRequest?.id &&
            !store.selectedRequest.id.startsWith('new-')
                ? store.selectedRequest.id
                : null;
        const validEnvironmentId =
            store.activeEnvironment?.id &&
            !store.activeEnvironment.id.startsWith('no-env')
                ? store.activeEnvironment.id
                : null;

        const res = await axios.post(`/requests/execute`, {
            request_id: validRequestId,
            method: method.value,
            url: targetUrl,
            body: JSON.stringify(bodyConfig.value),
            query_params: cleanParams,
            path_variables: pathVariables.value,
            headers: cleanHeaders,
            auth: authConfig.value,
            environment_id: validEnvironmentId,
        });
        const data = res.data;

        responseMeta.value = {
            status: data.status,
            statusText: data.status_text,
            time: data.time_ms,
            size: data.size_bytes,
        };
        responseHeaders.value = data.headers || {};

        if (data.is_json && typeof data.body === 'string') {
            try {
                responseBody.value = JSON.stringify(
                    JSON.parse(data.body),
                    null,
                    2,
                );
            } catch {
                responseBody.value = data.body;
            }
        } else {
            responseBody.value =
                typeof data.body === 'string'
                    ? data.body
                    : JSON.stringify(data.body, null, 2);
        }
    } catch (error) {
        console.error('Execution failed', error);
        responseBody.value = String(error);
        responseHeaders.value = {};
        responseMeta.value = { status: 0, statusText: 'Error' };
    } finally {
        isExecuting.value = false;
        // Reset cache so the next hover will fetch the new history
        historyFetchedForRequestId.value = null;
    }
};
</script>

<template>
    <div
        v-if="!store.selectedRequest"
        class="relative flex h-full w-full flex-col items-center justify-center overflow-hidden bg-background p-8 text-center"
    >
        <!-- Aesthetic background gradients -->
        <div
            class="pointer-events-none absolute top-1/4 left-1/4 h-96 w-96 rounded-full bg-primary/5 blur-3xl"
        ></div>
        <div
            class="pointer-events-none absolute right-1/4 bottom-1/4 h-96 w-96 rounded-full bg-violet-500/5 blur-3xl"
        ></div>

        <div class="relative z-10 max-w-md space-y-6">
            <div
                class="mx-auto flex h-12 w-12 animate-pulse items-center justify-center rounded-2xl bg-primary/10 text-primary"
            >
                <Sparkles class="h-6 w-6" />
            </div>
            <div class="space-y-2">
                <h3 class="text-xl font-semibold tracking-tight">
                    Modern API Workspace
                </h3>
                <p class="text-sm text-muted-foreground">
                    Select a request from the sidebar or create a new collection
                    to get started building and testing endpoints.
                </p>
            </div>
            <div class="grid grid-cols-2 gap-4 pt-4 text-left">
                <div class="space-y-1 rounded-xl border bg-muted/20 p-3">
                    <Terminal class="h-4 w-4 text-emerald-500" />
                    <div class="text-xs font-medium">Fast Execution</div>
                    <div class="text-[10px] text-muted-foreground">
                        Execute HTTP requests and inspect responses cleanly.
                    </div>
                </div>
                <div class="space-y-1 rounded-xl border bg-muted/20 p-3">
                    <Sparkles class="h-4 w-4 text-violet-500" />
                    <div class="text-xs font-medium">Test Scripts</div>
                    <div class="text-[10px] text-muted-foreground">
                        Write JavaScript test assertions and automated
                        workflows.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <ResizablePanelGroup
        id="request-editor-main-group"
        auto-save-id="request-editor-main-group"
        v-else
        direction="horizontal"
        class="h-full w-full"
    >
        <!-- Editor and Response Panel -->
        <ResizablePanel
            id="request-editor-left-panel"
            :default-size="70"
            :min-size="40"
        >
            <ResizablePanelGroup
                id="request-editor-vertical-group"
                auto-save-id="request-editor-vertical-group"
                direction="vertical"
                class="h-full w-full"
            >
                <!-- Request Input -->
                <ResizablePanel
                    id="request-editor-input-panel"
                    :default-size="50"
                    :min-size="20"
                >
                    <div class="flex h-full flex-col">
                        <!-- Address Bar -->
                        <div
                            class="flex items-center gap-2 border-b bg-background px-3 py-2"
                        >
                            <Select v-model="method">
                                <SelectTrigger
                                    class="h-9 w-[110px] font-mono text-xs font-bold"
                                    :class="getMethodColor(method)"
                                >
                                    <SelectValue placeholder="Method" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="m in methods"
                                        :key="m"
                                        :value="m"
                                        class="font-mono text-xs font-bold"
                                        :class="getMethodColor(m)"
                                    >
                                        {{ m }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <VariableInput
                                v-model="url"
                                :path-variables="pathVariables"
                                placeholder="Enter request URL"
                                class="h-9 flex-1"
                            />

                            <Button
                                variant="outline"
                                size="sm"
                                class="h-9 gap-1 text-xs"
                                @click="handleSave"
                                :disabled="isSaving"
                            >
                                <Save class="h-3.5 w-3.5" />
                                <span>{{ isSaving ? '...' : 'Save' }}</span>
                            </Button>

                            <Button
                                variant="default"
                                size="sm"
                                class="h-9 w-20 text-xs"
                                @click="executeRequest"
                                :disabled="isExecuting"
                            >
                                <span v-if="!isExecuting">Send</span>
                                <span v-else class="animate-spin"
                                    ><Terminal class="h-3.5 w-3.5"
                                /></span>
                            </Button>
                        </div>

                        <!-- Request Details Tabs -->
                        <div class="flex-1 overflow-hidden bg-background p-3">
                            <Tabs
                                v-model="activeRequestTab"
                                class="flex h-full flex-col"
                            >
                                <TabsList class="h-8 w-fit">
                                    <TabsTrigger
                                        value="params"
                                        class="py-1 text-xs"
                                        >Params</TabsTrigger
                                    >
                                    <TabsTrigger
                                        value="headers"
                                        class="py-1 text-xs"
                                        >Headers</TabsTrigger
                                    >
                                    <TabsTrigger
                                        v-if="methodSupportsBody"
                                        value="body"
                                        class="py-1 text-xs"
                                        >Body</TabsTrigger
                                    >
                                    <TabsTrigger
                                        value="auth"
                                        class="py-1 text-xs"
                                        >Auth</TabsTrigger
                                    >
                                    <TabsTrigger
                                        value="description"
                                        class="py-1 text-xs"
                                        >Docs</TabsTrigger
                                    >
                                </TabsList>

                                <div
                                    class="mt-2 flex-1 overflow-hidden rounded-md border"
                                >
                                    <TabsContent
                                        value="params"
                                        class="m-0 h-full overflow-y-auto bg-background/50 p-2"
                                    >
                                        <div class="space-y-2">
                                            <template
                                                v-if="pathVariables.length > 0"
                                            >
                                                <h3
                                                    class="mt-2 px-2 text-sm font-semibold"
                                                >
                                                    Path Variables
                                                </h3>
                                                <div
                                                    class="grid grid-cols-[30px_1fr_1fr_1fr_40px] items-center gap-2 border-b px-2 pb-2 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                                >
                                                    <div></div>
                                                    <div>Key</div>
                                                    <div>Value</div>
                                                    <div>Description</div>
                                                    <div></div>
                                                </div>

                                                <div class="space-y-2">
                                                    <div
                                                        v-for="(
                                                            param, idx
                                                        ) in pathVariables"
                                                        :key="'path-' + idx"
                                                        class="group/row grid grid-cols-[30px_1fr_1fr_1fr_40px] items-center gap-2"
                                                    >
                                                        <div
                                                            class="flex justify-center"
                                                        >
                                                            <Checkbox
                                                                :model-value="
                                                                    param.enabled
                                                                "
                                                                @update:model-value="
                                                                    param.enabled =
                                                                        !!$event
                                                                "
                                                            />
                                                        </div>
                                                        <VariableInput
                                                            v-model="param.key"
                                                            placeholder="Key"
                                                            class="h-8 font-mono text-xs opacity-70"
                                                            readonly
                                                        />
                                                        <VariableInput
                                                            v-model="
                                                                param.value
                                                            "
                                                            placeholder="Value"
                                                            class="h-8 font-mono text-xs"
                                                        />
                                                        <Input
                                                            v-model="
                                                                param.description
                                                            "
                                                            placeholder="Description"
                                                            class="h-8 text-xs"
                                                        />
                                                        <div
                                                            class="flex justify-center"
                                                        ></div>
                                                    </div>
                                                </div>
                                            </template>

                                            <div
                                                class="mt-2 flex items-center justify-between px-2"
                                            >
                                                <h3
                                                    class="text-sm font-semibold"
                                                >
                                                    Query Parameters
                                                </h3>
                                                <div
                                                    v-if="
                                                        pathVariables.length ===
                                                        0
                                                    "
                                                    class="flex items-center gap-1.5 text-xs text-muted-foreground/70"
                                                >
                                                    <svg
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        width="12"
                                                        height="12"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        stroke-width="2"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        class="lucide lucide-lightbulb"
                                                    >
                                                        <path
                                                            d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.9 1.2 1.5 1.5 2.5"
                                                        />
                                                        <path d="M9 18h6" />
                                                        <path d="M10 22h4" />
                                                    </svg>
                                                    <span
                                                        >Tip: Add
                                                        <code
                                                            class="rounded bg-muted/50 px-1 py-0.5 font-mono text-[10px]"
                                                            >:variable</code
                                                        >
                                                        to the URL for path
                                                        variables.</span
                                                    >
                                                </div>
                                            </div>
                                            <div
                                                class="grid grid-cols-[30px_1fr_1fr_1fr_40px] items-center gap-2 border-b px-2 pb-2 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                            >
                                                <div></div>
                                                <div>Key</div>
                                                <div>Value</div>
                                                <div>Description</div>
                                                <div></div>
                                            </div>

                                            <div class="space-y-2">
                                                <div
                                                    v-for="(
                                                        param, idx
                                                    ) in queryParams"
                                                    :key="idx"
                                                    class="group/row grid grid-cols-[30px_1fr_1fr_1fr_40px] items-center gap-2"
                                                >
                                                    <div
                                                        class="flex justify-center"
                                                    >
                                                        <Checkbox
                                                            :model-value="
                                                                param.enabled
                                                            "
                                                            @update:model-value="
                                                                param.enabled =
                                                                    !!$event
                                                            "
                                                        />
                                                    </div>
                                                    <VariableInput
                                                        v-model="param.key"
                                                        placeholder="Key"
                                                        class="h-8 font-mono text-xs"
                                                        @focus="
                                                            handleSuggestionFocus(
                                                                $event,
                                                                'param-key',
                                                                idx,
                                                                param.key,
                                                            )
                                                        "
                                                        @input="
                                                            activeSuggestion = {
                                                                type: 'param-key',
                                                                index: idx,
                                                                filter: param.key,
                                                            };
                                                            handleParamInput(
                                                                idx,
                                                            );
                                                        "
                                                        @blur="
                                                            activeSuggestion =
                                                                null
                                                        "
                                                    />
                                                    <VariableInput
                                                        v-model="param.value"
                                                        placeholder="Value"
                                                        class="h-8 font-mono text-xs"
                                                    />
                                                    <Input
                                                        v-model="
                                                            param.description
                                                        "
                                                        placeholder="Description"
                                                        class="h-8 text-xs"
                                                    />
                                                    <div
                                                        class="flex justify-center"
                                                    >
                                                        <Button
                                                            v-if="
                                                                idx <
                                                                queryParams.length -
                                                                    1
                                                            "
                                                            variant="ghost"
                                                            size="sm"
                                                            class="h-7 w-7 p-0 text-muted-foreground opacity-0 transition-opacity group-hover/row:opacity-100 hover:text-red-500"
                                                            @click="
                                                                removeParam(idx)
                                                            "
                                                        >
                                                            <Trash2
                                                                class="h-3.5 w-3.5"
                                                            />
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </TabsContent>

                                    <TabsContent
                                        value="headers"
                                        class="m-0 h-full overflow-y-auto bg-background/50 p-2"
                                    >
                                        <div class="space-y-2">
                                            <div
                                                class="grid grid-cols-[30px_1fr_1fr_1fr_40px] items-center gap-2 border-b px-2 pb-2 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                            >
                                                <div></div>
                                                <div>Key</div>
                                                <div>Value</div>
                                                <div>Description</div>
                                                <div></div>
                                            </div>

                                            <div class="space-y-2">
                                                <div
                                                    v-for="(
                                                        header, idx
                                                    ) in headersList"
                                                    :key="idx"
                                                    class="group/row grid grid-cols-[30px_1fr_1fr_1fr_40px] items-center gap-2"
                                                >
                                                    <div
                                                        class="flex justify-center"
                                                    >
                                                        <Checkbox
                                                            :model-value="
                                                                header.enabled
                                                            "
                                                            @update:model-value="
                                                                header.enabled =
                                                                    !!$event
                                                            "
                                                        />
                                                    </div>
                                                    <VariableInput
                                                        v-model="header.key"
                                                        placeholder="Key"
                                                        class="h-8 font-mono text-xs"
                                                        @focus="
                                                            handleSuggestionFocus(
                                                                $event,
                                                                'header-key',
                                                                idx,
                                                                header.key,
                                                            )
                                                        "
                                                        @input="
                                                            activeSuggestion = {
                                                                type: 'header-key',
                                                                index: idx,
                                                                filter: header.key,
                                                            };
                                                            handleHeaderInput(
                                                                idx,
                                                            );
                                                        "
                                                        @blur="
                                                            activeSuggestion =
                                                                null
                                                        "
                                                    />

                                                    <VariableInput
                                                        v-model="header.value"
                                                        placeholder="Value"
                                                        class="h-8 font-mono text-xs"
                                                        @focus="
                                                            handleSuggestionFocus(
                                                                $event,
                                                                'header-value',
                                                                idx,
                                                                header.value,
                                                            )
                                                        "
                                                        @input="
                                                            activeSuggestion = {
                                                                type: 'header-value',
                                                                index: idx,
                                                                filter: header.value,
                                                            }
                                                        "
                                                        @blur="
                                                            activeSuggestion =
                                                                null
                                                        "
                                                    />
                                                    <Input
                                                        v-model="
                                                            header.description
                                                        "
                                                        placeholder="Description"
                                                        class="h-8 text-xs"
                                                    />
                                                    <div
                                                        class="flex justify-center"
                                                    >
                                                        <Button
                                                            v-if="
                                                                idx <
                                                                headersList.length -
                                                                    1
                                                            "
                                                            variant="ghost"
                                                            size="sm"
                                                            class="h-7 w-7 p-0 text-muted-foreground opacity-0 transition-opacity group-hover/row:opacity-100 hover:text-red-500"
                                                            @click="
                                                                removeHeader(
                                                                    idx,
                                                                )
                                                            "
                                                        >
                                                            <Trash2
                                                                class="h-3.5 w-3.5"
                                                            />
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </TabsContent>

                                    <TabsContent
                                        v-if="methodSupportsBody"
                                        value="body"
                                        class="m-0 flex h-full flex-col overflow-hidden"
                                    >
                                        <!-- Body Selector Sub-bar -->
                                        <div
                                            class="flex shrink-0 items-center justify-between border-b border-border/40 bg-muted/20 px-4 py-2 select-none"
                                        >
                                            <div
                                                class="flex items-center gap-1"
                                            >
                                                <button
                                                    v-for="modeOption in [
                                                        'none',
                                                        'form-data',
                                                        'x-www-form-urlencoded',
                                                        'raw',
                                                        'graphql',
                                                    ]"
                                                    :key="modeOption"
                                                    type="button"
                                                    class="rounded px-2.5 py-1 text-xs font-medium capitalize transition-colors"
                                                    :class="
                                                        bodyConfig.mode ===
                                                        modeOption
                                                            ? 'bg-sidebar-accent font-semibold text-sidebar-accent-foreground shadow-sm'
                                                            : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                                                    "
                                                    @click="
                                                        bodyConfig.mode =
                                                            modeOption
                                                    "
                                                >
                                                    {{
                                                        modeOption ===
                                                        'form-data'
                                                            ? 'form-data'
                                                            : modeOption ===
                                                                'x-www-form-urlencoded'
                                                              ? 'x-www-form-urlencoded'
                                                              : modeOption
                                                    }}
                                                </button>
                                            </div>

                                            <!-- Content-Type selector for raw body -->
                                            <div
                                                v-if="bodyConfig.mode === 'raw'"
                                                class="flex items-center gap-2"
                                            >
                                                <span
                                                    class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                                                    >Type:</span
                                                >
                                                <Select
                                                    v-model="
                                                        bodyConfig.raw.language
                                                    "
                                                >
                                                    <SelectTrigger
                                                        class="h-6 w-[110px] border bg-background px-2 py-0 text-[11px] font-medium"
                                                    >
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem
                                                            value="json"
                                                            class="text-xs"
                                                            >JSON</SelectItem
                                                        >
                                                        <SelectItem
                                                            value="text"
                                                            class="text-xs"
                                                            >Text</SelectItem
                                                        >
                                                        <SelectItem
                                                            value="javascript"
                                                            class="text-xs"
                                                            >JavaScript</SelectItem
                                                        >
                                                        <SelectItem
                                                            value="html"
                                                            class="text-xs"
                                                            >HTML</SelectItem
                                                        >
                                                        <SelectItem
                                                            value="xml"
                                                            class="text-xs"
                                                            >XML</SelectItem
                                                        >
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>

                                        <!-- Body Content Area -->
                                        <div class="flex-1 overflow-hidden">
                                            <!-- None Mode -->
                                            <div
                                                v-if="
                                                    bodyConfig.mode === 'none'
                                                "
                                                class="flex h-full flex-col items-center justify-center text-muted-foreground select-none"
                                            >
                                                <Terminal
                                                    class="mb-2 h-8 w-8 animate-pulse opacity-20"
                                                />
                                                <span
                                                    class="text-xs font-medium"
                                                    >This request does not have
                                                    a body.</span
                                                >
                                            </div>

                                            <!-- Raw Mode -->
                                            <div
                                                v-else-if="
                                                    bodyConfig.mode === 'raw'
                                                "
                                                class="h-full"
                                            >
                                                <VueMonacoEditor
                                                    v-model:value="
                                                        bodyConfig.raw.content
                                                    "
                                                    :theme="monacoTheme"
                                                    :language="
                                                        bodyConfig.raw.language
                                                    "
                                                    :options="{
                                                        minimap: {
                                                            enabled: false,
                                                        },
                                                        automaticLayout: true,
                                                        formatOnPaste: true,
                                                        formatOnType: true,
                                                        tabSize: 2,
                                                        scrollBeyondLastLine: false,
                                                    }"
                                                    @mount="handleEditorMount"
                                                />
                                            </div>

                                            <!-- Form Data Mode -->
                                            <div
                                                v-else-if="
                                                    bodyConfig.mode ===
                                                    'form-data'
                                                "
                                                class="h-full overflow-y-auto bg-background/50 p-2"
                                            >
                                                <div
                                                    class="max-w-4xl divide-y overflow-hidden rounded-md border bg-background shadow-sm"
                                                >
                                                    <div
                                                        class="grid h-8 grid-cols-[40px_1.5fr_100px_2fr_2fr_50px] items-center bg-muted/40 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                                    >
                                                        <div></div>
                                                        <div
                                                            class="flex h-full items-center border-r px-2"
                                                        >
                                                            Key
                                                        </div>
                                                        <div
                                                            class="flex h-full items-center border-r px-2"
                                                        >
                                                            Type
                                                        </div>
                                                        <div
                                                            class="flex h-full items-center border-r px-2"
                                                        >
                                                            Value
                                                        </div>
                                                        <div
                                                            class="flex h-full items-center border-r px-2"
                                                        >
                                                            Description
                                                        </div>
                                                        <div></div>
                                                    </div>

                                                    <div
                                                        v-for="(
                                                            item, idx
                                                        ) in bodyConfig.formdata"
                                                        :key="idx"
                                                        class="group/row grid grid-cols-[40px_1.5fr_100px_2fr_2fr_50px] items-center border-b transition-colors last:border-0 hover:bg-muted/10"
                                                    >
                                                        <div
                                                            class="flex justify-center"
                                                        >
                                                            <Checkbox
                                                                :model-value="
                                                                    item.enabled
                                                                "
                                                                @update:model-value="
                                                                    item.enabled =
                                                                        !!$event
                                                                "
                                                            />
                                                        </div>
                                                        <div
                                                            class="flex h-full items-center border-r"
                                                        >
                                                            <VariableInput
                                                                v-model="
                                                                    item.key
                                                                "
                                                                placeholder="Key"
                                                                class="h-8 border-0 bg-transparent px-0 text-xs focus-visible:ring-0 focus-visible:ring-offset-0"
                                                                @input="
                                                                    handleFormDataInput(
                                                                        idx,
                                                                    )
                                                                "
                                                            />
                                                        </div>
                                                        <div
                                                            class="flex h-full items-center border-r px-1"
                                                        >
                                                            <Select
                                                                v-model="
                                                                    item.type
                                                                "
                                                            >
                                                                <SelectTrigger
                                                                    class="h-6 w-full border-0 bg-transparent px-1 text-[11px] shadow-none focus:ring-0"
                                                                >
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem
                                                                        value="text"
                                                                        class="text-xs"
                                                                        >Text</SelectItem
                                                                    >
                                                                    <SelectItem
                                                                        value="file"
                                                                        class="text-xs"
                                                                        >File</SelectItem
                                                                    >
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <div
                                                            class="flex h-full items-center border-r"
                                                        >
                                                            <Input
                                                                v-if="
                                                                    item.type ===
                                                                    'file'
                                                                "
                                                                type="file"
                                                                class="h-7 border-0 bg-transparent px-2 text-[11px] file:mr-2 file:rounded file:border-0 file:bg-muted file:px-2 file:py-0.5 file:text-[10px] focus-visible:ring-0 focus-visible:ring-offset-0"
                                                                @change="
                                                                    handleFileChange(
                                                                        $event,
                                                                        item,
                                                                    )
                                                                "
                                                            />
                                                            <VariableInput
                                                                v-else
                                                                v-model="
                                                                    item.value
                                                                "
                                                                placeholder="Value"
                                                                class="h-8 border-0 bg-transparent px-0 text-xs focus-visible:ring-0 focus-visible:ring-offset-0"
                                                            />
                                                        </div>
                                                        <div
                                                            class="flex h-full items-center border-r"
                                                        >
                                                            <Input
                                                                v-model="
                                                                    item.description
                                                                "
                                                                placeholder="Description"
                                                                class="h-8 border-0 bg-transparent px-2 text-xs focus-visible:ring-0 focus-visible:ring-offset-0"
                                                            />
                                                        </div>
                                                        <div
                                                            class="flex justify-center"
                                                        >
                                                            <Button
                                                                v-if="
                                                                    idx <
                                                                    bodyConfig
                                                                        .formdata
                                                                        .length -
                                                                        1
                                                                "
                                                                variant="ghost"
                                                                size="sm"
                                                                class="h-7 w-7 p-0 text-muted-foreground opacity-0 transition-opacity group-hover/row:opacity-100 hover:text-red-500"
                                                                @click="
                                                                    removeFormData(
                                                                        idx,
                                                                    )
                                                                "
                                                            >
                                                                <Trash2
                                                                    class="h-3.5 w-3.5"
                                                                />
                                                            </Button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- URL Encoded Mode -->
                                            <div
                                                v-else-if="
                                                    bodyConfig.mode ===
                                                    'x-www-form-urlencoded'
                                                "
                                                class="h-full overflow-y-auto bg-background/50 p-2"
                                            >
                                                <div
                                                    class="max-w-4xl divide-y overflow-hidden rounded-md border bg-background shadow-sm"
                                                >
                                                    <div
                                                        class="grid h-8 grid-cols-[40px_2fr_2fr_2fr_50px] items-center bg-muted/40 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                                    >
                                                        <div></div>
                                                        <div
                                                            class="flex h-full items-center border-r px-2"
                                                        >
                                                            Key
                                                        </div>
                                                        <div
                                                            class="flex h-full items-center border-r px-2"
                                                        >
                                                            Value
                                                        </div>
                                                        <div
                                                            class="flex h-full items-center border-r px-2"
                                                        >
                                                            Description
                                                        </div>
                                                        <div></div>
                                                    </div>

                                                    <div
                                                        v-for="(
                                                            item, idx
                                                        ) in bodyConfig.urlencoded"
                                                        :key="idx"
                                                        class="group/row grid grid-cols-[40px_2fr_2fr_2fr_50px] items-center border-b transition-colors last:border-0 hover:bg-muted/10"
                                                    >
                                                        <div
                                                            class="flex justify-center"
                                                        >
                                                            <Checkbox
                                                                :model-value="
                                                                    item.enabled
                                                                "
                                                                @update:model-value="
                                                                    item.enabled =
                                                                        !!$event
                                                                "
                                                            />
                                                        </div>
                                                        <div
                                                            class="flex h-full items-center border-r"
                                                        >
                                                            <VariableInput
                                                                v-model="
                                                                    item.key
                                                                "
                                                                placeholder="Key"
                                                                class="h-8 border-0 bg-transparent px-0 text-xs focus-visible:ring-0 focus-visible:ring-offset-0"
                                                                @input="
                                                                    handleUrlEncodedInput(
                                                                        idx,
                                                                    )
                                                                "
                                                            />
                                                        </div>
                                                        <div
                                                            class="flex h-full items-center border-r"
                                                        >
                                                            <VariableInput
                                                                v-model="
                                                                    item.value
                                                                "
                                                                placeholder="Value"
                                                                class="h-8 border-0 bg-transparent px-0 text-xs focus-visible:ring-0 focus-visible:ring-offset-0"
                                                            />
                                                        </div>
                                                        <div
                                                            class="flex h-full items-center border-r"
                                                        >
                                                            <Input
                                                                v-model="
                                                                    item.description
                                                                "
                                                                placeholder="Description"
                                                                class="h-8 border-0 bg-transparent px-2 text-xs focus-visible:ring-0 focus-visible:ring-offset-0"
                                                            />
                                                        </div>
                                                        <div
                                                            class="flex justify-center"
                                                        >
                                                            <Button
                                                                v-if="
                                                                    idx <
                                                                    bodyConfig
                                                                        .urlencoded
                                                                        .length -
                                                                        1
                                                                "
                                                                variant="ghost"
                                                                size="sm"
                                                                class="h-7 w-7 p-0 text-muted-foreground opacity-0 transition-opacity group-hover/row:opacity-100 hover:text-red-500"
                                                                @click="
                                                                    removeUrlEncoded(
                                                                        idx,
                                                                    )
                                                                "
                                                            >
                                                                <Trash2
                                                                    class="h-3.5 w-3.5"
                                                                />
                                                            </Button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- GraphQL Mode -->
                                            <div
                                                v-else-if="
                                                    bodyConfig.mode ===
                                                    'graphql'
                                                "
                                                class="flex h-full flex-col divide-y overflow-hidden border-t"
                                            >
                                                <div
                                                    class="flex min-h-0 flex-1 flex-col overflow-hidden"
                                                >
                                                    <div
                                                        class="flex shrink-0 items-center justify-between border-b bg-muted/30 px-4 py-1.5 text-[10px] font-bold tracking-wider text-muted-foreground uppercase select-none"
                                                    >
                                                        <span
                                                            >GraphQL Query</span
                                                        >
                                                        <span
                                                            class="font-mono text-[9px] font-normal text-muted-foreground/60 normal-case"
                                                            >Use variables below
                                                            if needed</span
                                                        >
                                                    </div>
                                                    <div
                                                        class="flex-1 overflow-hidden"
                                                    >
                                                        <VueMonacoEditor
                                                            v-model:value="
                                                                bodyConfig
                                                                    .graphql
                                                                    .query
                                                            "
                                                            :theme="monacoTheme"
                                                            language="graphql"
                                                            :options="{
                                                                minimap: {
                                                                    enabled: false,
                                                                },
                                                                automaticLayout: true,
                                                                scrollBeyondLastLine: false,
                                                                tabSize: 2,
                                                            }"
                                                        />
                                                    </div>
                                                </div>
                                                <div
                                                    class="flex h-[180px] shrink-0 flex-col overflow-hidden"
                                                >
                                                    <div
                                                        class="shrink-0 border-b bg-muted/30 px-4 py-1.5 text-[10px] font-bold tracking-wider text-muted-foreground uppercase select-none"
                                                    >
                                                        Variables (JSON)
                                                    </div>
                                                    <div
                                                        class="flex-1 overflow-hidden"
                                                    >
                                                        <VueMonacoEditor
                                                            v-model:value="
                                                                bodyConfig
                                                                    .graphql
                                                                    .variables
                                                            "
                                                            :theme="monacoTheme"
                                                            language="json"
                                                            :options="{
                                                                minimap: {
                                                                    enabled: false,
                                                                },
                                                                automaticLayout: true,
                                                                scrollBeyondLastLine: false,
                                                                tabSize: 2,
                                                            }"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </TabsContent>

                                    <TabsContent
                                        value="auth"
                                        class="m-0 h-full overflow-y-auto bg-background/50 p-2"
                                    >
                                        <div class="max-w-xl space-y-6">
                                            <div class="space-y-2">
                                                <label
                                                    class="text-xs font-semibold text-muted-foreground"
                                                    >Type</label
                                                >
                                                <Select
                                                    v-model="authConfig.type"
                                                >
                                                    <SelectTrigger
                                                        class="h-8 w-[200px] border bg-background text-xs font-medium"
                                                    >
                                                        <SelectValue
                                                            placeholder="Auth Type"
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem
                                                            value="noauth"
                                                            class="text-xs"
                                                            >No Auth</SelectItem
                                                        >
                                                        <SelectItem
                                                            value="bearer"
                                                            class="text-xs"
                                                            >Bearer
                                                            Token</SelectItem
                                                        >
                                                        <SelectItem
                                                            value="basic"
                                                            class="text-xs"
                                                            >Basic
                                                            Auth</SelectItem
                                                        >
                                                        <SelectItem
                                                            value="apikey"
                                                            class="text-xs"
                                                            >API Key</SelectItem
                                                        >
                                                    </SelectContent>
                                                </Select>
                                                <p
                                                    class="mt-1 text-[10px] text-muted-foreground"
                                                >
                                                    Configure authentication
                                                    credentials for this
                                                    request. Supports
                                                    environment variables like
                                                    <span class="font-mono"
                                                        >&#123;variable&#125;</span
                                                    >.
                                                </p>
                                            </div>

                                            <!-- Bearer Token Config -->
                                            <div
                                                v-if="
                                                    authConfig.type === 'bearer'
                                                "
                                                class="space-y-3 border-t pt-4"
                                            >
                                                <div class="space-y-1.5">
                                                    <label
                                                        class="text-xs font-semibold"
                                                        >Token</label
                                                    >
                                                    <VariableInput
                                                        v-model="
                                                            authConfig.bearerToken
                                                        "
                                                        type="text"
                                                        placeholder="Bearer Token"
                                                        class="h-8 font-mono text-xs"
                                                    />
                                                </div>
                                            </div>

                                            <!-- Basic Auth Config -->
                                            <div
                                                v-if="
                                                    authConfig.type === 'basic'
                                                "
                                                class="space-y-3 border-t pt-4"
                                            >
                                                <div
                                                    class="grid grid-cols-2 gap-4"
                                                >
                                                    <div class="space-y-1.5">
                                                        <label
                                                            class="text-xs font-semibold"
                                                            >Username</label
                                                        >
                                                        <VariableInput
                                                            v-model="
                                                                authConfig.basicUsername
                                                            "
                                                            type="text"
                                                            placeholder="Username"
                                                            class="h-8 font-mono text-xs"
                                                        />
                                                    </div>
                                                    <div class="space-y-1.5">
                                                        <label
                                                            class="text-xs font-semibold"
                                                            >Password</label
                                                        >
                                                        <VariableInput
                                                            v-model="
                                                                authConfig.basicPassword
                                                            "
                                                            type="password"
                                                            placeholder="Password"
                                                            class="h-8 font-mono text-xs"
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- API Key Config -->
                                            <div
                                                v-if="
                                                    authConfig.type === 'apikey'
                                                "
                                                class="space-y-3 border-t pt-4"
                                            >
                                                <div
                                                    class="grid grid-cols-2 gap-4"
                                                >
                                                    <div class="space-y-1.5">
                                                        <label
                                                            class="text-xs font-semibold"
                                                            >Key</label
                                                        >
                                                        <VariableInput
                                                            v-model="
                                                                authConfig.apiKeyKey
                                                            "
                                                            type="text"
                                                            placeholder="api_key"
                                                            class="h-8 font-mono text-xs"
                                                        />
                                                    </div>
                                                    <div class="space-y-1.5">
                                                        <label
                                                            class="text-xs font-semibold"
                                                            >Value</label
                                                        >
                                                        <VariableInput
                                                            v-model="
                                                                authConfig.apiKeyValue
                                                            "
                                                            type="text"
                                                            placeholder="value"
                                                            class="h-8 font-mono text-xs"
                                                        />
                                                    </div>
                                                </div>
                                                <div class="mt-2 space-y-1.5">
                                                    <label
                                                        class="text-xs font-semibold"
                                                        >Add To</label
                                                    >
                                                    <Select
                                                        v-model="
                                                            authConfig.apiKeyAddTo
                                                        "
                                                    >
                                                        <SelectTrigger
                                                            class="h-8 w-[200px] border bg-background text-xs font-medium"
                                                        >
                                                            <SelectValue
                                                                placeholder="Add to..."
                                                            />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                value="header"
                                                                class="text-xs"
                                                                >Header</SelectItem
                                                            >
                                                            <SelectItem
                                                                value="query"
                                                                class="text-xs"
                                                                >Query
                                                                Params</SelectItem
                                                            >
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                        </div>
                                    </TabsContent>

                                    <!-- Docs Tab -->
                                    <TabsContent
                                        value="description"
                                        class="m-0 flex h-full flex-col overflow-hidden bg-background/50 p-2"
                                    >
                                        <div
                                            class="mb-4 flex shrink-0 items-center justify-between border-b pb-2"
                                        >
                                            <div
                                                class="flex space-x-1 rounded-md bg-muted/30 p-1"
                                            >
                                                <button
                                                    @click="
                                                        activeDocTab = 'write'
                                                    "
                                                    class="flex items-center gap-1.5 rounded-sm px-3 py-1 text-xs transition-colors"
                                                    :class="
                                                        activeDocTab === 'write'
                                                            ? 'bg-background font-medium text-foreground shadow-sm'
                                                            : 'text-muted-foreground hover:text-foreground'
                                                    "
                                                >
                                                    <Edit3
                                                        class="h-3.5 w-3.5"
                                                    />
                                                    Write
                                                </button>
                                                <button
                                                    @click="
                                                        activeDocTab = 'preview'
                                                    "
                                                    class="flex items-center gap-1.5 rounded-sm px-3 py-1 text-xs transition-colors"
                                                    :class="
                                                        activeDocTab ===
                                                        'preview'
                                                            ? 'bg-background font-medium text-foreground shadow-sm'
                                                            : 'text-muted-foreground hover:text-foreground'
                                                    "
                                                >
                                                    <Eye class="h-3.5 w-3.5" />
                                                    Preview
                                                </button>
                                            </div>
                                            <span
                                                class="font-mono text-xs text-muted-foreground"
                                            >
                                                Markdown Supported
                                            </span>
                                        </div>

                                        <div
                                            class="relative flex-1 overflow-hidden rounded-md border bg-background"
                                        >
                                            <div
                                                v-show="
                                                    activeDocTab === 'write'
                                                "
                                                class="absolute inset-0 flex flex-col"
                                            >
                                                <VueMonacoEditor
                                                    v-model:value="description"
                                                    :theme="monacoTheme"
                                                    language="markdown"
                                                    :options="{
                                                        minimap: {
                                                            enabled: false,
                                                        },
                                                        wordWrap: 'on',
                                                        automaticLayout: true,
                                                        tabSize: 2,
                                                        scrollBeyondLastLine: false,
                                                    }"
                                                />
                                            </div>

                                            <div
                                                v-show="
                                                    activeDocTab === 'preview'
                                                "
                                                class="absolute inset-0 overflow-y-auto p-4"
                                            >
                                                <div
                                                    v-if="
                                                        description &&
                                                        description.trim()
                                                    "
                                                    class="prose dark:prose-invert max-w-none text-sm leading-relaxed"
                                                    v-html="
                                                        parseMarkdown(
                                                            description,
                                                        )
                                                    "
                                                ></div>
                                                <div
                                                    v-else
                                                    class="flex h-full items-center justify-center text-xs text-muted-foreground italic"
                                                >
                                                    No documentation written
                                                    yet. Switch to "Write" to
                                                    add markdown documentation.
                                                </div>
                                            </div>
                                        </div>
                                    </TabsContent>
                                </div>
                            </Tabs>
                        </div>
                    </div>
                </ResizablePanel>

                <ResizableHandle
                    id="request-editor-vertical-handle"
                    with-handle
                />

                <!-- Response Section -->
                <ResizablePanel
                    id="request-editor-response-panel"
                    :default-size="50"
                    :min-size="20"
                >
                    <div class="flex h-full flex-col bg-muted/10">
                        <div
                            class="flex items-center gap-4 border-b bg-background/50 px-3 py-2"
                        >
                            <span class="text-xs font-semibold tracking-wide"
                                >RESPONSE</span
                            >
                            <!-- Response Meta -->
                            <div
                                v-if="responseMeta.status"
                                class="ml-auto flex gap-4 text-[10px] text-muted-foreground"
                            >
                                <span
                                    >Status:
                                    <span
                                        :class="{
                                            'text-emerald-500':
                                                responseMeta.status < 400,
                                            'text-rose-500':
                                                responseMeta.status >= 400,
                                        }"
                                        class="font-mono font-bold"
                                        >{{ responseMeta.status }}
                                        {{ responseMeta.statusText }}</span
                                    ></span
                                >
                                <span class="flex items-center gap-1"
                                    >Time:
                                    <HoverCard
                                        @update:open="
                                            (open) => {
                                                if (open) fetchRequestHistory();
                                            }
                                        "
                                    >
                                        <HoverCardTrigger as-child>
                                            <span
                                                class="cursor-help font-mono font-bold underline decoration-dotted underline-offset-2"
                                                >{{
                                                    responseMeta.time
                                                }}
                                                ms</span
                                            >
                                        </HoverCardTrigger>
                                        <HoverCardContent
                                            align="end"
                                            class="z-[100] w-64 p-3"
                                        >
                                            <div class="flex flex-col gap-2">
                                                <h4
                                                    class="text-xs font-semibold"
                                                >
                                                    Recent Executions
                                                </h4>
                                                <div
                                                    v-if="isFetchingHistory"
                                                    class="flex items-center justify-center py-4 text-[10px] text-muted-foreground"
                                                >
                                                    <Loader2
                                                        class="h-4 w-4 animate-spin"
                                                    />
                                                </div>
                                                <div
                                                    v-else-if="
                                                        requestHistoryItems.length ===
                                                        0
                                                    "
                                                    class="py-2 text-center text-[10px] text-muted-foreground"
                                                >
                                                    No history found.
                                                </div>
                                                <div
                                                    v-else
                                                    class="relative mt-4 mb-2 h-20 w-full"
                                                >
                                                    <!-- Beautiful SVG Curve Graph -->
                                                    <svg
                                                        class="pointer-events-none absolute inset-0 h-full w-full overflow-visible"
                                                        preserveAspectRatio="none"
                                                        viewBox="0 0 100 100"
                                                    >
                                                        <defs>
                                                            <linearGradient
                                                                id="chart-gradient"
                                                                x1="0"
                                                                y1="0"
                                                                x2="0"
                                                                y2="1"
                                                            >
                                                                <stop
                                                                    offset="0%"
                                                                    stop-color="currentColor"
                                                                    stop-opacity="0.3"
                                                                    class="text-primary"
                                                                />
                                                                <stop
                                                                    offset="100%"
                                                                    stop-color="currentColor"
                                                                    stop-opacity="0"
                                                                    class="text-primary"
                                                                />
                                                            </linearGradient>
                                                        </defs>
                                                        <!-- Area fill -->
                                                        <path
                                                            :d="graphAreaPath"
                                                            fill="url(#chart-gradient)"
                                                        />
                                                        <!-- Line stroke -->
                                                        <path
                                                            :d="graphLinePath"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            stroke-width="2"
                                                            class="text-primary"
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            vector-effect="non-scaling-stroke"
                                                        />
                                                    </svg>

                                                    <!-- Interactive Overlay -->
                                                    <div
                                                        class="absolute inset-0 flex items-stretch"
                                                    >
                                                        <div
                                                            v-for="(
                                                                point, i
                                                            ) in graphPoints"
                                                            :key="i"
                                                            class="group/bar relative flex h-full flex-1 cursor-crosshair flex-col justify-end"
                                                        >
                                                            <!-- Vertical Crosshair Line -->
                                                            <div
                                                                class="pointer-events-none absolute bottom-0 left-1/2 h-full w-px -translate-x-1/2 bg-primary/20 opacity-0 transition-opacity duration-150 group-hover/bar:opacity-100"
                                                            ></div>

                                                            <!-- Tooltip -->
                                                            <div
                                                                class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-3 -translate-x-1/2 transform rounded-md border border-border bg-popover/90 px-2 py-1 text-[10px] whitespace-nowrap text-popover-foreground opacity-0 shadow-md backdrop-blur-sm transition-all duration-200 group-hover/bar:-translate-y-1 group-hover/bar:opacity-100"
                                                            >
                                                                <div
                                                                    class="font-mono font-bold text-primary"
                                                                >
                                                                    {{
                                                                        point.ms
                                                                    }}
                                                                    ms
                                                                </div>
                                                            </div>

                                                            <!-- Point Indicator Dot -->
                                                            <div
                                                                class="pointer-events-none absolute left-1/2 h-2.5 w-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-[2px] border-primary bg-background opacity-0 shadow-sm transition-all duration-200 group-hover/bar:scale-125 group-hover/bar:opacity-100"
                                                                :style="{
                                                                    top: `${point.y}%`,
                                                                }"
                                                            ></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </HoverCardContent>
                                    </HoverCard>
                                </span>
                                <span
                                    >Size:
                                    <span class="font-mono font-bold"
                                        >{{
                                            (responseMeta.size! / 1024).toFixed(
                                                2,
                                            )
                                        }}
                                        KB</span
                                    ></span
                                >
                            </div>
                        </div>

                        <div class="flex-1 overflow-hidden p-3">
                            <Tabs
                                default-value="body"
                                class="flex h-full flex-col"
                            >
                                <TabsList class="h-8 w-fit">
                                    <TabsTrigger
                                        value="body"
                                        class="py-1 text-xs"
                                        >Body</TabsTrigger
                                    >
                                    <TabsTrigger
                                        value="headers"
                                        class="py-1 text-xs"
                                        >Headers</TabsTrigger
                                    >
                                </TabsList>

                                <div
                                    class="relative mt-2 flex-1 overflow-hidden rounded-md border bg-background"
                                >
                                    <div
                                        v-if="isExecuting"
                                        class="absolute inset-0 z-10 flex items-center justify-center bg-background/60"
                                    >
                                        <div
                                            class="flex items-center gap-2 text-xs"
                                        >
                                            <span
                                                class="h-2 w-2 animate-ping rounded-full bg-primary"
                                            ></span>
                                            <span
                                                class="animate-pulse font-medium text-muted-foreground"
                                                >Executing request...</span
                                            >
                                        </div>
                                    </div>
                                    <TabsContent
                                        value="body"
                                        class="m-0 flex h-full flex-col"
                                    >
                                        <div
                                            v-if="isHtmlResponse"
                                            class="relative min-h-0 flex-1 bg-white"
                                        >
                                            <iframe
                                                class="h-full w-full border-none"
                                                :srcdoc="responseBody"
                                                sandbox="allow-same-origin"
                                            ></iframe>
                                        </div>
                                        <div
                                            v-else
                                            class="relative min-h-0 flex-1"
                                        >
                                            <VueMonacoEditor
                                                :value="responseBody"
                                                :theme="monacoTheme"
                                                language="json"
                                                :options="{
                                                    readOnly: true,
                                                    minimap: { enabled: false },
                                                    automaticLayout: true,
                                                    scrollBeyondLastLine: false,
                                                    wordWrap: 'on',
                                                }"
                                            />
                                        </div>
                                    </TabsContent>

                                    <TabsContent
                                        value="headers"
                                        class="m-0 h-full overflow-y-auto bg-background/50 p-2"
                                    >
                                        <div
                                            v-if="
                                                Object.keys(responseHeaders)
                                                    .length === 0
                                            "
                                            class="flex h-full flex-col items-center justify-center space-y-2 py-8 text-xs text-muted-foreground"
                                        >
                                            <span class="font-medium"
                                                >No response headers
                                                available</span
                                            >
                                            <span class="text-[10px] opacity-70"
                                                >Execute a request to see the
                                                returned headers list.</span
                                            >
                                        </div>
                                        <div v-else>
                                            <div
                                                class="space-y-1.5 font-mono text-[11px]"
                                            >
                                                <div
                                                    v-for="(
                                                        values, key
                                                    ) in responseHeaders"
                                                    :key="key"
                                                    class="border-b border-muted/20 pb-1.5 last:border-0"
                                                >
                                                    <div
                                                        class="truncate font-semibold text-primary select-all"
                                                    >
                                                        {{ key }}
                                                    </div>
                                                    <div
                                                        class="mt-0.5 break-all text-muted-foreground select-all"
                                                    >
                                                        {{
                                                            Array.isArray(
                                                                values,
                                                            )
                                                                ? values.join(
                                                                      ', ',
                                                                  )
                                                                : values
                                                        }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </TabsContent>
                                </div>
                            </Tabs>
                        </div>
                    </div>
                </ResizablePanel>
            </ResizablePanelGroup>
        </ResizablePanel>
    </ResizablePanelGroup>

    <!-- Teleport suggestions to body to avoid container overflow clipping -->
    <Teleport to="body">
        <div
            v-if="activeSuggestion && filteredSuggestions.length > 0"
            :style="{
                position: 'absolute',
                top: `${suggestionCoords.top}px`,
                left: `${suggestionCoords.left}px`,
                width: `${suggestionCoords.width}px`,
                zIndex: 9999,
            }"
            class="max-h-[160px] space-y-0.5 overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-lg"
        >
            <button
                v-for="sug in filteredSuggestions"
                :key="sug"
                type="button"
                class="w-full rounded px-2 py-1 text-left font-mono text-[11px] transition-colors hover:bg-accent hover:text-accent-foreground"
                @mousedown.prevent="selectSuggestion(sug)"
            >
                {{ sug }}
            </button>
        </div>
    </Teleport>

    <!-- Shadcn Confirm Dialog -->
</template>
