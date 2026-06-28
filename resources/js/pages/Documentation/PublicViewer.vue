<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    BookOpen,
    Search,
    ChevronRight,
    Copy,
    Check,
    Globe,
    Code,
    Sparkles,
    Terminal,
    Sun,
    Moon,
    ArrowRight,
    CheckCircle,
    Info,
    ExternalLink,
    AlertCircle
} from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';
import { parseMarkdown } from '@/lib/markdown';

// Props
const props = defineProps<{
    documentation: {
        id: string;
        collection_id: string;
        is_public: boolean;
        public_slug: string;
        version: string;
        markdown_intro: string | null;
        auth_info: string | null;
        settings: any;
    };
    collection: {
        id: string;
        name: string;
        description: string | null;
        folders: Array<{
            id: string;
            name: string;
            parent_id: string | null;
        }>;
    };
    requests: Array<{
        id: string;
        folder_id: string | null;
        name: string;
        description: string | null;
        method: string;
        url: string;
        headers: any;
        query_params: any;
        body: any;
        auth: any;
        examples: Array<{
            id: string;
            name: string;
            status_code: number;
            headers: any;
            body: string | null;
        }>;
    }>;
}>();

// Theme state
const isDark = ref(false);

const toggleTheme = () => {
    isDark.value = !isDark.value;

    if (isDark.value) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    }
};

// Search & Navigation
const searchQuery = ref('');
const selectedRequestId = ref<string | null>(null);

// Code Generation State
const selectedLang = ref<'curl' | 'fetch' | 'axios' | 'python' | 'go' | 'php-guzzle' | 'php-curl' | 'java'>('curl');
const copiedState = ref(false);
const pathCopied = ref(false);

// Active response example per request (keyed by request ID)
const activeExampleIndex = ref<Record<string, number>>({});

// Filtered Requests
const filteredRequests = computed(() => {
    if (!searchQuery.value.trim()) {
return props.requests;
}

    const query = searchQuery.value.toLowerCase();

    return props.requests.filter(
        (r) => r.name.toLowerCase().includes(query) || r.url.toLowerCase().includes(query)
    );
});

// Currently viewed request
const activeRequest = computed(() => {
    if (!selectedRequestId.value) {
return null;
}

    return props.requests.find((r) => r.id === selectedRequestId.value) || null;
});

// Helper to normalize headers to a list of { key: string, value: string }
const parsedHeaders = computed(() => {
    const req = activeRequest.value;

    if (!req || !req.headers) {
return [];
}
    
    // If it's an array (which holds structured objects key/value/enabled/description)
    if (Array.isArray(req.headers)) {
        return req.headers.filter(h => h && typeof h === 'object' && h.key && h.enabled !== false);
    }
    
    // If it's stored as a key-value object
    if (typeof req.headers === 'object') {
        return Object.entries(req.headers)
            .filter(([k, v]) => k && v !== undefined && v !== null)
            .map(([k, v]) => ({ key: k, value: String(v) }));
    }
    
    return [];
});

// Helper to normalize query parameters to a list of { key: string, value: string }
const parsedQueryParams = computed(() => {
    const req = activeRequest.value;

    if (!req || !req.query_params) {
return [];
}
    
    // If it's an array
    if (Array.isArray(req.query_params)) {
        return req.query_params.filter(q => q && typeof q === 'object' && q.key && q.enabled !== false);
    }
    
    // If it's stored as a key-value object
    if (typeof req.query_params === 'object') {
        return Object.entries(req.query_params)
            .filter(([k, v]) => k && v !== undefined && v !== null)
            .map(([k, v]) => ({ key: k, value: String(v) }));
    }
    
    return [];
});

// Helper for HTTP Method styling
const getMethodClass = (method: string) => {
    const m = method.toUpperCase();

    if (m === 'GET') {
return 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20';
}

    if (m === 'POST') {
return 'bg-blue-500/10 text-blue-500 border-blue-500/20';
}

    if (m === 'PUT') {
return 'bg-amber-500/10 text-amber-500 border-amber-500/20';
}

    if (m === 'DELETE') {
return 'bg-rose-500/10 text-rose-500 border-rose-500/20';
}

    return 'bg-muted text-muted-foreground border-border';
};

// Pre-render content
const introHtml = computed(() => {
    return parseMarkdown(props.documentation.markdown_intro || '# API Documentation\nWelcome to our API reference portal.');
});

const authHtml = computed(() => {
    return parseMarkdown(props.documentation.auth_info || '');
});

const requestDescHtml = computed(() => {
    if (!activeRequest.value) {
return '';
}

    return parseMarkdown(activeRequest.value.description || '*No detailed documentation provided for this endpoint yet.*');
});

// Helper to extract clean raw body content string based on body mode configuration
const extractBodyContent = (body: any): string => {
    if (!body) {
return '';
}
    
    let parsed: any = null;

    if (typeof body === 'string') {
        try {
            parsed = JSON.parse(body);
        } catch (_) {
            return body;
        }
    } else {
        parsed = body;
    }
    
    if (parsed && typeof parsed === 'object') {
        if ('mode' in parsed) {
            const mode = parsed.mode;

            if (mode === 'none') {
                return '';
            }

            if (mode === 'raw') {
                return parsed.raw?.content || '';
            }

            if (mode === 'urlencoded') {
                const list = parsed.urlencoded || [];
                const enabled = list.filter((item: any) => item && item.key && item.enabled !== false);

                return enabled.map((item: any) => `${encodeURIComponent(item.key)}=${encodeURIComponent(item.value || '')}`).join('&');
            }

            if (mode === 'formdata') {
                const list = parsed.formdata || [];
                const enabled = list.filter((item: any) => item && item.key && item.enabled !== false);
                const obj: Record<string, string> = {};
                enabled.forEach((item: any) => {
                    obj[item.key] = item.value || '';
                });

                return JSON.stringify(obj, null, 2);
            }

            if (mode === 'graphql') {
                const gql = parsed.graphql || {};
                let vars = {};

                if (gql.variables) {
                    try {
                        vars = typeof gql.variables === 'string' ? JSON.parse(gql.variables) : gql.variables;
                    } catch (_) {}
                }

                return JSON.stringify({
                    query: gql.query || '',
                    variables: vars
                }, null, 2);
            }
        }
        
        try {
            return JSON.stringify(parsed, null, 2);
        } catch (_) {
            return String(parsed);
        }
    }
    
    return String(body);
};

// Generate Code Snippet
const generatedCode = computed(() => {
    const req = activeRequest.value;

    if (!req) {
return '';
}

    const method = req.method.toUpperCase();
    const url = req.url || 'https://api.example.com/endpoint';
    
    // Parse body using extractBodyContent helper (only for methods that support request bodies, e.g. POST, PUT, PATCH)
    const hasRequestBody = ['POST', 'PUT', 'PATCH'].includes(method);
    const bodyStr = hasRequestBody ? extractBodyContent(req.body) : '';

    // De-duplicate headers, adding Content-Type if missing and if a body is present
    const rawHeaders = parsedHeaders.value;
    const hasContentType = rawHeaders.some(h => h.key.toLowerCase() === 'content-type');
    const headersList = [...rawHeaders];

    if (!hasContentType && bodyStr) {
        headersList.unshift({ key: 'Content-Type', value: 'application/json' });
    }

    switch (selectedLang.value) {
        case 'fetch':
            return `fetch("${url}", {
  method: "${method}",
  headers: {
    ${headersList.map(h => `"${h.key}": "${h.value}"`).join(',\n    ')}
  }${bodyStr ? `,\n  body: JSON.stringify(${bodyStr.split('\n').join('\n  ')})` : ''}
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error("Error:", error));`;

        case 'axios':
            return `import axios from 'axios';

axios({
  method: '${method.toLowerCase()}',
  url: '${url}'${bodyStr ? `,\n  data: ${bodyStr.split('\n').join('\n  ')}` : ''}${headersList.length ? `,\n  headers: {\n    ` + headersList.map(h => `'${h.key}': '${h.value}'`).join(',\n    ') + `\n  }` : ''}
})
.then(response => {
  console.log(response.data);
})
.catch(error => {
  console.error(error);
});`;

        case 'python':
            return `import requests
import json

url = "${url}"
${headersList.length ? `headers = {\n    ` + headersList.map(h => `"${h.key}": "${h.value}"`).join(',\n    ') + `\n}\n` : ''}${bodyStr ? `data = ${bodyStr}\n` : ''}
response = requests.${method.toLowerCase()}(
    url${headersList.length ? ', headers=headers' : ''}${bodyStr ? ', data=json.dumps(data)' : ''}
)

print(response.status_code)
print(response.json())`;

        case 'go':
            return `package main

import (
	"fmt"
	"net/http"
	"io"
	"bytes"
)

func main() {
	url := "${url}"
	${bodyStr ? `var jsonStr = []byte(\`${bodyStr}\`)\n\treq, _ := http.NewRequest("${method}", url, bytes.NewBuffer(jsonStr))` : `req, _ := http.NewRequest("${method}", url, nil)`}

	${headersList.map(h => `req.Header.Set("${h.key}", "${h.value}")`).join('\n\t')}

	client := &http.Client{}
	resp, err := client.Do(req)
	if err != nil {
		panic(err)
	}
	defer resp.Body.Close()

	body, _ := io.ReadAll(resp.Body)
	fmt.Println("response Status:", resp.Status)
	fmt.Println("response Body:", string(body))
}`;

        case 'php-guzzle':
            {
                const headersObjStr = headersList.map(h => `'${h.key}' => '${h.value}'`).join(',\n        ');

                return `<?php
require 'vendor/autoload.php';

use GuzzleHttp\\Client;

$client = new Client();
$response = $client->request('${method}', '${url}', [
    ${headersList.length ? `'headers' => [\n        ` + headersObjStr + `\n    ]` : ''}${bodyStr ? `${headersList.length ? ',\n    ' : ''}'body' => '${bodyStr.replace(/'/g, "\\'")}'` : ''}
]);

echo $response->getBody();`;
            }

        case 'php-curl':
            {
                const headersArrStr = headersList.length 
                    ? `[\n        ` + headersList.map(h => `'${h.key}: ${h.value}'`).join(',\n        ') + `\n    ]`
                    : '[]';

                return `<?php

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => '${url}',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => '${method}',${bodyStr ? `\n    CURLOPT_POSTFIELDS => '${bodyStr.replace(/'/g, "\\'")}',` : ''}
    CURLOPT_HTTPHEADER => ${headersArrStr},
]);

$response = curl_exec($curl);

curl_close($curl);
echo $response;`;
            }

        case 'java':
            {
                const javaHeaders = headersList.map(h => `.header("${h.key}", "${h.value}")`).join('\n            ');
                const javaBodyPublisher = bodyStr 
                    ? `HttpRequest.BodyPublishers.ofString("""\n${bodyStr.replace(/"""/g, '\\"\\"\\"')}\n""")`
                    : `HttpRequest.BodyPublishers.noBody()`;

                return `import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;

public class Main {
    public static void main(String[] args) throws Exception {
        HttpClient client = HttpClient.newHttpClient();
        
        HttpRequest request = HttpRequest.newBuilder()
            .uri(URI.create("${url}"))
            ${javaHeaders ? javaHeaders + '\n            ' : ''}.method("${method}", ${javaBodyPublisher})
            .build();

        HttpResponse<String> response = client.send(request, HttpResponse.BodyHandlers.ofString());

        System.out.println("Status Code: " + response.statusCode());
        System.out.println("Response: " + response.body());
    }
}`;
            }

        case 'curl':
        default:
            const headersCurl = headersList.length
                ? headersList.map(h => `-H "${h.key}: ${h.value}"`).join(' ')
                : '';

            return `curl -X ${method} "${url}" ${headersCurl ? `\\\n  ${headersCurl} ` : ''}${bodyStr ? `\\\n  -d '${bodyStr.replace(/'/g, "'\\''")}'` : ''}`;
    }
});

// Shared copy to clipboard helper supporting standard browser API and textarea fallback
const copyTextToClipboard = (text: string) => {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text);
    } else {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        textArea.style.top = "-999999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            document.execCommand('copy');
        } catch (err) {
            console.error('Fallback copy failed', err);
        }

        document.body.removeChild(textArea);

        return Promise.resolve();
    }
};

// Copy Snippet to Clipboard
const copySnippet = () => {
    copyTextToClipboard(generatedCode.value);
    copiedState.value = true;
    setTimeout(() => {
        copiedState.value = false;
    }, 2000);
};

// Custom Regex-based syntax highlighter for API request snippets
const highlightCode = (code: string, lang: string): string => {
    if (!code) {
return '';
}

    // 1. Escape HTML
    let escaped = code
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    // 2. Tokenizer placeholders
    const placeholders: string[] = [];
    const addPlaceholder = (content: string, className: string): string => {
        const id = `___TOKEN_HL_${placeholders.length}___`;
        placeholders.push(`<span class="${className}">${content}</span>`);

        return id;
    };

    // a. Extract comments (ensure we don't match double slashes inside URLs like http://)
    if (lang === 'python') {
        escaped = escaped.replace(/(#[^\n]*)/g, (_, comment) => addPlaceholder(comment, 'text-zinc-500 italic'));
    } else {
        escaped = escaped.replace(/((?<!:)\/\/[^\n]*)/g, (_, comment) => addPlaceholder(comment, 'text-zinc-500 italic'));
    }

    // b. Extract strings
    escaped = escaped.replace(/("(\\.|[^"\\])*")/g, (_, str) => addPlaceholder(str, 'text-emerald-400 font-medium'));
    escaped = escaped.replace(/('(\\.|[^'\\])*')/g, (_, str) => addPlaceholder(str, 'text-emerald-400 font-medium'));
    escaped = escaped.replace(/(`(\\.|[^`\\])*`)/g, (_, str) => addPlaceholder(str, 'text-emerald-400 font-medium'));

    // 3. Highlight core language terms
    // Keywords
    const keywords = [
        'const', 'let', 'var', 'import', 'from', 'return', 'function', 'func',
        'use', 'require', 'new', 'def', 'package', 'case', 'switch', 'default',
        'nil', 'err', 'if', 'panic', 'defer', 'class', 'PHP', 'GuzzleHttp',
        'public', 'static', 'void', 'throws'
    ];
    const keywordRegex = new RegExp(`\\b(${keywords.join('|')})\\b`, 'g');
    escaped = escaped.replace(keywordRegex, '<span class="text-pink-400 font-semibold">$1</span>');

    // Built-ins and common APIs
    const builtins = [
        'fetch', 'axios', 'requests', 'Client', 'NewRequest', 'curl_init',
        'curl_setopt_array', 'curl_exec', 'curl_close', 'echo', 'print',
        'response', 'Header', 'Set', 'Do', 'ReadAll', 'Println', 'then', 'catch',
        'HttpClient', 'HttpRequest', 'HttpResponse', 'URI', 'System', 'out', 'println',
        'BodyPublishers', 'BodyHandlers', 'newHttpClient', 'newBuilder', 'ofString',
        'noBody', 'send'
    ];
    const builtinRegex = new RegExp(`\\b(${builtins.join('|')})\\b`, 'g');
    escaped = escaped.replace(builtinRegex, '<span class="text-sky-400">$1</span>');

    // Numbers & Booleans
    escaped = escaped.replace(/\b(true|false|null|0|10|CURLOPT_[A-Z_]+)\b/g, '<span class="text-orange-400">$1</span>');

    // HTTP Methods
    escaped = escaped.replace(/\b(GET|POST|PUT|DELETE|PATCH)\b/g, '<span class="text-amber-400 font-bold">$1</span>');

    // Variables / properties
    escaped = escaped.replace(/(\$[a-zA-Z_][a-zA-Z0-9_]*)/g, '<span class="text-indigo-300 font-semibold">$1</span>');

    // 4. Restore strings and comments in order (reverse order handles nested string placeholders perfectly!)
    for (let i = placeholders.length - 1; i >= 0; i--) {
        escaped = escaped.replace(`___TOKEN_HL_${i}___`, () => placeholders[i]);
    }

    return escaped;
};

// Highlighted Code Snippet computed property
const highlightedCode = computed(() => {
    return highlightCode(generatedCode.value, selectedLang.value);
});

// Copy Endpoint URL to Clipboard
const copyEndpointUrl = (url: string) => {
    copyTextToClipboard(url);
    pathCopied.value = true;
    setTimeout(() => {
        pathCopied.value = false;
    }, 2000);
};

// Setup initial theme on load
onMounted(() => {
    const savedTheme = localStorage.getItem('theme');

    if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        isDark.value = true;
        document.documentElement.classList.add('dark');
    } else {
        isDark.value = false;
        document.documentElement.classList.remove('dark');
    }
});
</script>

<template>
    <Head :title="props.collection.name + ' - API Reference'" />

    <div class="min-h-screen bg-background text-foreground flex flex-col font-sans transition-colors duration-300">
        <!-- Gorgeous Top Bar Banner -->
        <header class="sticky top-0 z-40 w-full border-b border-border bg-background/90 backdrop-blur-md px-6 py-4 flex items-center justify-between select-none">
            <div class="flex items-center gap-3">
                <div class="h-9 w-9 rounded-lg bg-primary/10 flex items-center justify-center border border-primary/20 shadow-xs">
                    <BookOpen class="h-5 w-5 text-primary" />
                </div>
                <div>
                    <h1 class="font-extrabold text-base tracking-tight text-foreground flex items-center gap-2">
                        {{ props.collection.name }}
                        <span class="text-[10px] bg-primary/10 text-primary border border-primary/20 px-2 py-0.5 rounded-full font-bold">
                            v{{ props.documentation.version }}
                        </span>
                    </h1>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button
                    @click="toggleTheme"
                    class="rounded-lg p-2 border border-border bg-muted/30 text-muted-foreground hover:text-foreground transition-all"
                    aria-label="Toggle Night Mode"
                >
                    <Sun v-if="isDark" class="h-4 w-4" />
                    <Moon v-else class="h-4 w-4" />
                </button>
            </div>
        </header>

        <!-- Main Workspace: 3 Column layout -->
        <div class="flex-1 grid grid-cols-1 lg:grid-cols-12 w-full max-w-[1920px] mx-auto">
            
            <!-- Column 1: Left Navigation Sidebar -->
            <aside class="lg:col-span-3 border-r border-border p-5 flex flex-col gap-5 max-h-[calc(100vh-69px)] overflow-y-auto sticky top-[69px]">
                <!-- Interactive Search Bar -->
                <div class="relative w-full">
                    <Search class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground/60" />
                    <input
                        type="text"
                        v-model="searchQuery"
                        placeholder="Search API endpoints..."
                        class="h-9 w-full rounded-md border border-input bg-muted/20 pl-9 pr-3 py-1.5 text-xs placeholder:text-muted-foreground/70 focus:outline-hidden focus:ring-1 focus:ring-primary focus:border-primary transition-all"
                    />
                </div>

                <!-- Navigation List -->
                <div class="flex flex-col gap-4">
                    <!-- Home / Overview Link -->
                    <button
                        @click="selectedRequestId = null"
                        class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold rounded-lg text-left transition-colors"
                        :class="selectedRequestId === null ? 'bg-primary/10 text-primary border border-primary/10' : 'text-muted-foreground hover:bg-muted/50'"
                    >
                        <Globe class="h-4 w-4" />
                        Overview Guide
                    </button>

                    <!-- Group Requests by Folder -->
                    <div class="space-y-4">
                        <!-- Folders -->
                        <div v-for="folder in props.collection.folders" :key="folder.id" class="space-y-1.5">
                            <h4 class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest px-3">
                                {{ folder.name }}
                            </h4>
                            
                            <div class="space-y-0.5">
                                <button
                                    v-for="req in filteredRequests.filter(r => r.folder_id === folder.id)"
                                    :key="req.id"
                                    @click="selectedRequestId = req.id"
                                    class="w-full flex items-center gap-2 px-3 py-1.5 rounded-lg text-left text-xs transition-colors"
                                    :class="selectedRequestId === req.id ? 'bg-muted border border-border font-semibold text-foreground' : 'text-muted-foreground hover:bg-muted/30'"
                                >
                                    <span
                                        class="inline-flex shrink-0 items-center justify-center rounded border px-1 text-[8px] leading-none font-bold uppercase py-0.5 w-10 text-center select-none"
                                        :class="getMethodClass(req.method)"
                                    >
                                        {{ req.method }}
                                    </span>
                                    <span class="truncate">{{ req.name }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Unfolder-grouped Requests -->
                        <div v-if="filteredRequests.filter(r => !r.folder_id).length > 0" class="space-y-1">
                            <h4 class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest px-3">
                                Root Endpoints
                            </h4>
                            <button
                                v-for="req in filteredRequests.filter(r => !r.folder_id)"
                                :key="req.id"
                                @click="selectedRequestId = req.id"
                                class="w-full flex items-center gap-2 px-3 py-1.5 rounded-lg text-left text-xs transition-colors"
                                :class="selectedRequestId === req.id ? 'bg-muted border border-border font-semibold text-foreground' : 'text-muted-foreground hover:bg-muted/30'"
                            >
                                <span
                                    class="inline-flex shrink-0 items-center justify-center rounded border px-1 text-[8px] leading-none font-bold uppercase py-0.5 w-10 text-center select-none"
                                    :class="getMethodClass(req.method)"
                                >
                                    {{ req.method }}
                                </span>
                                <span class="truncate">{{ req.name }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Column 2: Center Content & Markdown Reader -->
            <main class="lg:col-span-5 p-6 lg:p-8 border-r border-border max-h-[calc(100vh-69px)] overflow-y-auto select-text">
                <!-- Overview Guide View -->
                <div v-if="selectedRequestId === null" class="space-y-8 animate-in fade-in duration-300">
                    <div class="prose dark:prose-invert max-w-none" v-html="introHtml"></div>

                    <!-- Global Auth Information (if present) -->
                    <div v-if="props.documentation.auth_info" class="border rounded-xl p-5 bg-muted/20 border-border mt-8">
                        <h3 class="text-sm font-extrabold uppercase tracking-widest text-muted-foreground mb-3 flex items-center gap-1.5">
                            <Sparkles class="h-4 w-4 text-primary" />
                            Authentication Guide
                        </h3>
                        <div class="prose dark:prose-invert max-w-none text-xs" v-html="authHtml"></div>
                    </div>
                </div>

                <!-- Endpoint Documentation View -->
                <div v-else-if="activeRequest" class="space-y-6 animate-in fade-in duration-200">
                    <!-- Method + Name -->
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <span
                                class="inline-flex items-center justify-center rounded border px-2 py-0.5 text-[10px] leading-none font-bold uppercase select-none"
                                :class="getMethodClass(activeRequest.method)"
                            >
                                {{ activeRequest.method }}
                            </span>
                            <span class="text-xs text-muted-foreground uppercase tracking-widest font-bold">API Endpoint</span>
                        </div>
                        <h2 class="text-xl font-extrabold text-foreground tracking-tight">{{ activeRequest.name }}</h2>
                    </div>

                    <!-- URL Bar with copy -->
                    <div class="flex items-center justify-between gap-3 p-3 bg-muted/30 border rounded-lg font-mono text-xs text-foreground/80 break-all select-all">
                        <span class="truncate">{{ activeRequest.url || 'No URL configured' }}</span>
                        <button
                            @click="copyEndpointUrl(activeRequest.url)"
                            class="rounded p-1 text-muted-foreground hover:bg-border hover:text-foreground transition-colors shrink-0"
                            title="Copy URL Path"
                        >
                            <Check v-if="pathCopied" class="h-3.5 w-3.5 text-emerald-500" />
                            <Copy v-else class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <!-- Markdown Description -->
                    <div class="prose dark:prose-invert max-w-none text-sm leading-relaxed" v-html="requestDescHtml"></div>

                    <!-- Request Headers & Query Params Tables (if present) -->
                    <div v-if="parsedHeaders.length > 0" class="space-y-3">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Request Headers</h4>
                        <div class="border rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-border text-xs">
                                <thead class="bg-muted/40">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-semibold text-muted-foreground">Header key</th>
                                        <th class="px-4 py-2 text-left font-semibold text-muted-foreground">Type</th>
                                        <th class="px-4 py-2 text-left font-semibold text-muted-foreground">Mock Value</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border bg-card">
                                    <tr v-for="header in parsedHeaders" :key="header.key">
                                        <td class="px-4 py-2 font-mono font-semibold text-foreground">{{ header.key }}</td>
                                        <td class="px-4 py-2 text-muted-foreground font-mono text-[10px]">string</td>
                                        <td class="px-4 py-2 font-mono text-muted-foreground/80 select-all">{{ header.value }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Query Parameters -->
                    <div v-if="parsedQueryParams.length > 0" class="space-y-3">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Query Parameters</h4>
                        <div class="border rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-border text-xs">
                                <thead class="bg-muted/40">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-semibold text-muted-foreground">Parameter</th>
                                        <th class="px-4 py-2 text-left font-semibold text-muted-foreground">Type</th>
                                        <th class="px-4 py-2 text-left font-semibold text-muted-foreground">Value</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border bg-card">
                                    <tr v-for="param in parsedQueryParams" :key="param.key">
                                        <td class="px-4 py-2 font-mono font-semibold text-foreground">{{ param.key }}</td>
                                        <td class="px-4 py-2 text-muted-foreground font-mono text-[10px]">string</td>
                                        <td class="px-4 py-2 font-mono text-muted-foreground/80 select-all">{{ param.value }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Column 3: Code Snippets & Response Mock Examples -->
            <aside class="lg:col-span-4 bg-muted/15 p-6 border-l lg:border-l-0 max-h-[calc(100vh-69px)] overflow-y-auto sticky top-[69px] flex flex-col gap-6 select-text">
                <!-- If overview guide is selected, show general greeting details -->
                <div v-if="selectedRequestId === null" class="flex flex-col gap-4 text-center justify-center items-center py-12 px-6 h-full">
                    <div class="rounded-full bg-primary/10 p-4 border border-primary/20 shadow-xs mb-2">
                        <Sparkles class="h-7 w-7 text-primary" />
                    </div>
                    <h3 class="text-sm font-bold text-foreground">API Reference Interactive Console</h3>
                    <p class="text-xs text-muted-foreground max-w-[280px]">
                        Select any request endpoint in the left navigation sidebar to view interactive request code snippets and live response examples instantly.
                    </p>
                </div>

                <template v-else-if="activeRequest">
                    <!-- Code Snippet Box -->
                    <div class="space-y-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <Terminal class="h-4 w-4" />
                            Request Snippet
                        </h4>
                        
                        <div class="border border-border rounded-xl bg-zinc-950 overflow-hidden shadow-md flex flex-col">
                            <!-- Snippet Language Header Selector -->
                            <div class="flex items-center justify-between bg-zinc-900 border-b border-zinc-800 px-3 py-1.5 select-none">
                                <div class="flex items-center gap-1 text-[10px] text-zinc-400 font-bold uppercase tracking-wider">
                                    <Code class="h-3.5 w-3.5" />
                                    Languages
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <select
                                        v-model="selectedLang"
                                        class="h-7 border-0 bg-transparent text-[11px] font-bold text-zinc-300 focus:outline-hidden cursor-pointer"
                                    >
                                        <option value="curl" class="bg-zinc-900 text-zinc-300">cURL</option>
                                        <option value="fetch" class="bg-zinc-900 text-zinc-300">Javascript Fetch</option>
                                        <option value="axios" class="bg-zinc-900 text-zinc-300">Axios</option>
                                        <option value="python" class="bg-zinc-900 text-zinc-300">Python Requests</option>
                                        <option value="go" class="bg-zinc-900 text-zinc-300">Go http</option>
                                        <option value="php-guzzle" class="bg-zinc-900 text-zinc-300">PHP Guzzle</option>
                                        <option value="php-curl" class="bg-zinc-900 text-zinc-300">PHP cURL</option>
                                        <option value="java" class="bg-zinc-900 text-zinc-300">Java HttpClient</option>
                                    </select>
                                    <button
                                        @click="copySnippet"
                                        class="p-1 rounded text-zinc-400 hover:text-white hover:bg-zinc-800 transition-colors"
                                        title="Copy Snippet"
                                    >
                                        <Check v-if="copiedState" class="h-3.5 w-3.5 text-emerald-400" />
                                        <Copy v-else class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>

                            <!-- Snippet Body -->
                            <pre class="p-4 text-xs font-mono text-zinc-300 overflow-x-auto max-h-[300px] leading-relaxed select-all whitespace-pre"><code v-html="highlightedCode"></code></pre>
                        </div>
                    </div>

                    <!-- Mock response Examples Tab Selector -->
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Mock response Example</h4>
                        
                        <div v-if="activeRequest.examples && activeRequest.examples.length > 0" class="border rounded-xl bg-zinc-950 overflow-hidden shadow-md">
                            <!-- Example Header Tab Selection -->
                            <div class="flex items-center justify-between bg-zinc-900 border-b border-zinc-800 px-3 py-1.5 select-none">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="h-2 w-2 rounded-full"
                                        :class="activeRequest.examples[activeExampleIndex[activeRequest.id] || 0]?.status_code >= 200 && activeRequest.examples[activeExampleIndex[activeRequest.id] || 0]?.status_code < 300 ? 'bg-emerald-500' : 'bg-rose-500'"
                                    ></span>
                                    <select
                                        :value="activeExampleIndex[activeRequest.id] || 0"
                                        @change="activeExampleIndex[activeRequest.id] = parseInt(($event.target as HTMLSelectElement).value)"
                                        class="h-7 border-0 bg-transparent text-[11px] font-bold text-zinc-300 focus:outline-hidden cursor-pointer"
                                    >
                                        <option
                                            v-for="(example, idx) in activeRequest.examples"
                                            :key="example.id"
                                            :value="idx"
                                            class="bg-zinc-900 text-zinc-300"
                                        >
                                            {{ example.status_code }} - {{ example.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <!-- Example Body -->
                            <pre class="p-4 text-xs font-mono text-zinc-300 overflow-x-auto max-h-[350px] leading-relaxed select-all whitespace-pre"><code>{{ activeRequest.examples[activeExampleIndex[activeRequest.id] || 0]?.body || '{\n  "status": "empty"\n}' }}</code></pre>
                        </div>

                        <!-- Fallback empty state -->
                        <div v-else class="text-xs italic text-muted-foreground/60 p-6 border border-dashed rounded-xl text-center bg-muted/5">
                            No response examples mock-ups registered for this endpoint.
                        </div>
                    </div>
                </template>
            </aside>

        </div>
    </div>
</template>
