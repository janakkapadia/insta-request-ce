<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    BookOpen,
    Search,
    ChevronRight,
    ChevronDown,
    Copy,
    Check,
    Globe,
    Code,
    Sparkles,
    Terminal,
    Sun,
    Moon,
} from 'lucide-vue-next';
import { ref, computed, onMounted, watch } from 'vue';
import DocFolderNode from '@/components/Documentation/DocFolderNode.vue';
import OfflineOverlay from '@/components/OfflineOverlay.vue';
import {
    ResizableHandle,
    ResizablePanel,
    ResizablePanelGroup,
} from '@/components/ui/resizable';
import { parseMarkdown } from '@/lib/markdown';
import { getMethodBadgeColors as getMethodClass } from '@/lib/method-colors';

defineOptions({ layout: null as any });

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
    } | null;
    collection: {
        id: string;
        name: string;
        description: string | null;
        folders: Array<{
            id: string;
            name: string;
            parent_id: string | null;
        }>;
    } | null;
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
    environment?: {
        id: string;
        name: string;
        color: string | null;
        variables: Array<{ key: string; value: string; enabled: boolean }>;
    } | null;
    publicDocsList?: Array<{
        id: string;
        collection_id: string;
        public_slug: string;
        version: string;
        name: string;
    }>;
}>();

// Environment state & variable substitution helper

const activeEnvVariables = computed(() => {
    if (!props.environment || !props.environment.variables) {
        return [];
    }

    return props.environment.variables.filter((v) => v.enabled !== false);
});

const substituteEnvVariables = (text: string | null | undefined): string => {
    if (!text || typeof text !== 'string') {
        return text || '';
    }

    if (!activeEnvVariables.value.length) {
        return text;
    }

    let result = text;
    activeEnvVariables.value.forEach((v) => {
        const regex1 = new RegExp(`\\{\\{${v.key}\\}\\}`, 'g');
        const regex2 = new RegExp(`\\{${v.key}\\}`, 'g');
        result = result.replace(regex1, v.value).replace(regex2, v.value);
    });

    return result;
};

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
const showDocsDropdown = ref(false);

const switchCollection = (docItem: {
    id: string;
    collection_id: string;
    public_slug?: string;
    version: string;
    name: string;
}) => {
    showDocsDropdown.value = false;

    if (docItem.public_slug) {
        router.get(`/docs/${docItem.collection_id}/${docItem.public_slug}`);
    } else {
        router.get(`/docs?collection_id=${docItem.collection_id}`);
    }
};

// Code Generation State
const selectedLang = ref<
    | 'curl'
    | 'fetch'
    | 'axios'
    | 'python'
    | 'go'
    | 'php-guzzle'
    | 'php-curl'
    | 'java'
>('curl');
const copiedState = ref(false);
const pathCopied = ref(false);

// Active response example per request (keyed by request ID)
const activeExampleIndex = ref<Record<string, number>>({});

// Filtered Requests
const filteredRequests = computed(() => {
    if (!props.requests) {
        return [];
    }

    if (!searchQuery.value.trim()) {
        return props.requests;
    }

    const query = searchQuery.value.toLowerCase();

    return props.requests.filter(
        (r) =>
            r.name.toLowerCase().includes(query) ||
            r.url.toLowerCase().includes(query),
    );
});

const rootFolders = computed(() => {
    if (!props.collection || !props.collection.folders) {
        return [];
    }

    return props.collection.folders.filter((f) => !f.parent_id);
});

const rootRequests = computed(() => {
    return filteredRequests.value.filter((r) => !r.folder_id);
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
        return req.headers
            .filter(
                (h) =>
                    h && typeof h === 'object' && h.key && h.enabled !== false,
            )
            .map((h) => ({
                ...h,
                value: substituteEnvVariables(String(h.value || '')),
            }));
    }

    // If it's stored as a key-value object
    if (typeof req.headers === 'object') {
        return Object.entries(req.headers)
            .filter(([k, v]) => k && v !== undefined && v !== null)
            .map(([k, v]) => ({
                key: k,
                value: substituteEnvVariables(String(v)),
            }));
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
        return req.query_params
            .filter(
                (q) =>
                    q && typeof q === 'object' && q.key && q.enabled !== false,
            )
            .map((q) => ({
                ...q,
                value: substituteEnvVariables(String(q.value || '')),
            }));
    }

    // If it's stored as a key-value object
    if (typeof req.query_params === 'object') {
        return Object.entries(req.query_params)
            .filter(([k, v]) => k && v !== undefined && v !== null)
            .map(([k, v]) => ({
                key: k,
                value: substituteEnvVariables(String(v)),
            }));
    }

    return [];
});

const rawBodyContent = computed(() => {
    const req = activeRequest.value;

    if (!req || !req.body) {
        return null;
    }

    let parsedBodyObj: any = null;

    if (typeof req.body === 'string') {
        try {
            parsedBodyObj = JSON.parse(req.body);
        } catch {
            return null;
        }
    } else {
        parsedBodyObj = req.body;
    }

    if (parsedBodyObj?.mode === 'raw' && parsedBodyObj?.raw?.content) {
        return {
            content: substituteEnvVariables(parsedBodyObj.raw.content),
            language: parsedBodyObj.raw.language || 'text',
        };
    }

    return null;
});

const parsedBodyItems = computed(() => {
    const req = activeRequest.value;

    if (!req || !req.body) {
        return [];
    }

    let parsedBodyObj: any = null;

    if (typeof req.body === 'string') {
        try {
            parsedBodyObj = JSON.parse(req.body);
        } catch {
            return [];
        }
    } else {
        parsedBodyObj = req.body;
    }

    const mode = parsedBodyObj?.mode;

    let items: any[] = [];

    if (mode === 'formdata' || mode === 'form-data') {
        items = Array.isArray(parsedBodyObj?.formdata)
            ? parsedBodyObj.formdata
            : [];
    } else if (mode === 'urlencoded' || mode === 'x-www-form-urlencoded') {
        items = Array.isArray(parsedBodyObj?.urlencoded)
            ? parsedBodyObj.urlencoded
            : [];
    } else if (mode === 'raw' && !parsedBodyObj?.raw?.content) {
        // Fallback checks
        if (
            Array.isArray(parsedBodyObj?.formdata) &&
            parsedBodyObj.formdata.length > 0 &&
            parsedBodyObj.formdata.some(
                (i: any) => i && i.key && i.enabled !== false,
            )
        ) {
            items = parsedBodyObj.formdata;
        } else if (
            Array.isArray(parsedBodyObj?.urlencoded) &&
            parsedBodyObj.urlencoded.length > 0 &&
            parsedBodyObj.urlencoded.some(
                (i: any) => i && i.key && i.enabled !== false,
            )
        ) {
            items = parsedBodyObj.urlencoded;
        }
    }

    return items
        .filter(
            (i) => i && typeof i === 'object' && i.key && i.enabled !== false,
        )
        .map((i) => ({
            ...i,
            value: substituteEnvVariables(String(i.value || '')),
        }));
});

// Helper for HTTP Method styling — uses shared utility for consistency

// Pre-render content
const introHtml = computed(() => {
    return parseMarkdown(
        (props.documentation && props.documentation.markdown_intro) ||
            '# API Documentation\nWelcome to our API reference portal.',
    );
});

const authHtml = computed(() => {
    return parseMarkdown(
        (props.documentation && props.documentation.auth_info) || '',
    );
});

const requestDescHtml = computed(() => {
    if (!activeRequest.value) {
        return '';
    }

    return parseMarkdown(
        activeRequest.value.description ||
            '*No detailed documentation provided for this endpoint yet.*',
    );
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
        } catch {
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
                if (!parsed.raw?.content) {
                    if (
                        Array.isArray(parsed.formdata) &&
                        parsed.formdata.length > 0 &&
                        parsed.formdata.some(
                            (i: any) => i && i.key && i.enabled !== false,
                        )
                    ) {
                        const list = parsed.formdata;
                        const enabled = list.filter(
                            (item: any) =>
                                item && item.key && item.enabled !== false,
                        );
                        const obj: Record<string, string> = {};
                        enabled.forEach((item: any) => {
                            obj[item.key] = item.value || '';
                        });

                        return JSON.stringify(obj, null, 2);
                    } else if (
                        Array.isArray(parsed.urlencoded) &&
                        parsed.urlencoded.length > 0 &&
                        parsed.urlencoded.some(
                            (i: any) => i && i.key && i.enabled !== false,
                        )
                    ) {
                        const list = parsed.urlencoded;
                        const enabled = list.filter(
                            (item: any) =>
                                item && item.key && item.enabled !== false,
                        );

                        return enabled
                            .map(
                                (item: any) =>
                                    `${encodeURIComponent(item.key)}=${encodeURIComponent(item.value || '')}`,
                            )
                            .join('&');
                    }
                }

                return parsed.raw?.content || '';
            }

            if (mode === 'urlencoded' || mode === 'x-www-form-urlencoded') {
                const list = parsed.urlencoded || [];
                const enabled = list.filter(
                    (item: any) => item && item.key && item.enabled !== false,
                );

                return enabled
                    .map(
                        (item: any) =>
                            `${encodeURIComponent(item.key)}=${encodeURIComponent(item.value || '')}`,
                    )
                    .join('&');
            }

            if (mode === 'formdata' || mode === 'form-data') {
                const list = parsed.formdata || [];
                const enabled = list.filter(
                    (item: any) => item && item.key && item.enabled !== false,
                );
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
                        vars =
                            typeof gql.variables === 'string'
                                ? JSON.parse(gql.variables)
                                : gql.variables;
                    } catch {}
                }

                return JSON.stringify(
                    {
                        query: gql.query || '',
                        variables: vars,
                    },
                    null,
                    2,
                );
            }
        }

        try {
            return JSON.stringify(parsed, null, 2);
        } catch {
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
    const url = substituteEnvVariables(
        req.url || 'https://api.example.com/endpoint',
    ).replace(/\\/g, '\\\\');

    // Parse body using extractBodyContent helper (only for methods that support request bodies, e.g. POST, PUT, PATCH)
    const hasRequestBody = ['POST', 'PUT', 'PATCH'].includes(method);
    const rawBody = hasRequestBody ? extractBodyContent(req.body) : '';
    const bodyStr = substituteEnvVariables(rawBody);

    let parsedBodyObj: any = null;

    if (req.body) {
        if (typeof req.body === 'string') {
            try {
                parsedBodyObj = JSON.parse(req.body);
            } catch {}
        } else {
            parsedBodyObj = req.body;
        }
    }

    const mode = parsedBodyObj?.mode || 'raw';
    let isUrlEncoded =
        mode === 'urlencoded' || mode === 'x-www-form-urlencoded';
    let isFormData = mode === 'formdata' || mode === 'form-data';

    if (mode === 'raw' && !parsedBodyObj?.raw?.content) {
        if (
            Array.isArray(parsedBodyObj?.formdata) &&
            parsedBodyObj.formdata.length > 0 &&
            parsedBodyObj.formdata.some(
                (i: any) => i && i.key && i.enabled !== false,
            )
        ) {
            isFormData = true;
        } else if (
            Array.isArray(parsedBodyObj?.urlencoded) &&
            parsedBodyObj.urlencoded.length > 0 &&
            parsedBodyObj.urlencoded.some(
                (i: any) => i && i.key && i.enabled !== false,
            )
        ) {
            isUrlEncoded = true;
        }
    }

    const formDataItems =
        isFormData && Array.isArray(parsedBodyObj?.formdata)
            ? parsedBodyObj.formdata
                  .filter((i: any) => i && i.key && i.enabled !== false)
                  .map((i: any) => ({
                      ...i,
                      key: String(i.key),
                      value: String(i.value || ''),
                  }))
            : [];

    // De-duplicate headers, adding Content-Type if missing and if a body is present
    const rawHeaders = parsedHeaders.value.map((h) => ({
        key: h.key.replace(/\\/g, '\\\\'),
        value: h.value.replace(/\\/g, '\\\\'),
    }));
    const hasContentType = rawHeaders.some(
        (h) => h.key.toLowerCase() === 'content-type',
    );
    const headersList = [...rawHeaders];

    if (!hasContentType && bodyStr) {
        headersList.unshift({
            key: 'Content-Type',
            value: isUrlEncoded
                ? 'application/x-www-form-urlencoded'
                : 'application/json',
        });
    }

    switch (selectedLang.value) {
        case 'fetch': {
            let fetchBodyStr = '';
            let setupCode = '';
            let fetchHeaders = headersList;

            if (isFormData && formDataItems.length > 0) {
                fetchHeaders = fetchHeaders.filter(
                    (h) => h.key.toLowerCase() !== 'content-type',
                );
                setupCode = `const formdata = new FormData();\n${formDataItems.map((i: any) => `formdata.append("${i.key.replace(/\\/g, '\\\\').replace(/"/g, '\\"')}", "${i.value.replace(/\\/g, '\\\\').replace(/"/g, '\\"')}");`).join('\n')}\n\n`;
                fetchBodyStr = `,\n  body: formdata`;
            } else if (bodyStr) {
                if (isUrlEncoded) {
                    fetchBodyStr = `,\n  body: "${bodyStr.replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"`;
                } else {
                    fetchBodyStr = `,\n  body: JSON.stringify(${bodyStr.split('\n').join('\n  ')})`;
                }
            }

            return `${setupCode}fetch("${url}", {
  method: "${method}",
  headers: {
    ${fetchHeaders.map((h) => `"${h.key}": "${h.value}"`).join(',\n    ')}
  }${fetchBodyStr}
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error("Error:", error));`;
        }

        case 'axios': {
            let axiosDataStr = '';
            let setupCode = '';
            let axiosHeaders = headersList;

            if (isFormData && formDataItems.length > 0) {
                axiosHeaders = axiosHeaders.filter(
                    (h) => h.key.toLowerCase() !== 'content-type',
                );
                setupCode = `const formdata = new FormData();\n${formDataItems.map((i: any) => `formdata.append("${i.key.replace(/\\/g, '\\\\').replace(/"/g, '\\"')}", "${i.value.replace(/\\/g, '\\\\').replace(/"/g, '\\"')}");`).join('\n')}\n\n`;
                axiosDataStr = `,\n  data: formdata`;
            } else if (bodyStr) {
                if (isUrlEncoded) {
                    axiosDataStr = `,\n  data: "${bodyStr.replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"`;
                } else {
                    axiosDataStr = `,\n  data: ${bodyStr.split('\n').join('\n  ')}`;
                }
            }

            return `import axios from 'axios';

${setupCode}axios({
  method: '${method.toLowerCase()}',
  url: '${url}'${axiosDataStr}${axiosHeaders.length ? `,\n  headers: {\n    ` + axiosHeaders.map((h) => `'${h.key}': '${h.value}'`).join(',\n    ') + `\n  }` : ''}
})
.then(response => {
  console.log(response.data);
})
.catch(error => {
  console.error(error);
});`;
        }

        case 'python': {
            let pythonDataStr = '';
            let setupCode = '';
            let pythonHeaders = headersList;

            if (isFormData && formDataItems.length > 0) {
                pythonHeaders = pythonHeaders.filter(
                    (h) => h.key.toLowerCase() !== 'content-type',
                );
                setupCode = `payload = {\n${formDataItems.map((i: any) => `    "${i.key.replace(/\\/g, '\\\\').replace(/"/g, '\\"')}": "${i.value.replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"`).join(',\n')}\n}\n`;
                pythonDataStr = `, data=payload`;
            } else if (bodyStr) {
                setupCode = `payload = ${JSON.stringify(bodyStr)}\n`;
                pythonDataStr = `, data=payload`;
            }

            return `import requests

url = "${url}"
${pythonHeaders.length ? `headers = {\n    ` + pythonHeaders.map((h) => `"${h.key}": "${h.value}"`).join(',\n    ') + `\n}\n` : ''}${setupCode}
response = requests.${method.toLowerCase()}(
    url${pythonHeaders.length ? ', headers=headers' : ''}${pythonDataStr}
)

print(response.status_code)
print(response.json())`;
        }

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

	${headersList.map((h) => `req.Header.Set("${h.key}", "${h.value}")`).join('\n\t')}

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

        case 'php-guzzle': {
            const headersObjStr = headersList
                .map((h) => `'${h.key}' => '${h.value}'`)
                .join(',\n        ');

            return `<?php
require 'vendor/autoload.php';

use GuzzleHttp\\Client;

$client = new Client();
$response = $client->request('${method}', '${url}', [
    ${headersList.length ? `'headers' => [\n        ` + headersObjStr + `\n    ]` : ''}${bodyStr ? `${headersList.length ? ',\n    ' : ''}'body' => '${bodyStr.replace(/\\/g, "\\\\").replace(/'/g, "\\'")}'` : ''}
]);

echo $response->getBody();`;
        }

        case 'php-curl': {
            const headersArrStr = headersList.length
                ? `[\n        ` +
                  headersList
                      .map((h) => `'${h.key}: ${h.value}'`)
                      .join(',\n        ') +
                  `\n    ]`
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
    CURLOPT_CUSTOMREQUEST => '${method}',${bodyStr ? `\n    CURLOPT_POSTFIELDS => '${bodyStr.replace(/\\/g, "\\\\").replace(/'/g, "\\'")}',` : ''}
    CURLOPT_HTTPHEADER => ${headersArrStr},
]);

$response = curl_exec($curl);

curl_close($curl);
echo $response;`;
        }

        case 'java': {
            const javaHeaders = headersList
                .map((h) => `.header("${h.key}", "${h.value}")`)
                .join('\n            ');
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
        default: {
            let curlHeadersList = headersList;
            let dataFlag = '';

            if (isFormData && formDataItems.length > 0) {
                curlHeadersList = curlHeadersList.filter(
                    (h) => h.key.toLowerCase() !== 'content-type',
                );
                dataFlag = formDataItems
                    .map(
                        (i: any) =>
                            ` \\\n  -F '${i.key.replace(/\\/g, '\\\\').replace(/'/g, "'\\''")}=${i.value.replace(/\\/g, '\\\\').replace(/'/g, "'\\''")}'`,
                    )
                    .join('');
            } else if (bodyStr) {
                dataFlag = ` \\\n  -d '${bodyStr.replace(/'/g, "'\\''")}'`;
            }

            const headersCurl = curlHeadersList.length
                ? curlHeadersList
                      .map((h) => `-H "${h.key}: ${h.value}"`)
                      .join(' \\\n  ')
                : '';

            return `curl -X ${method} "${url}"${headersCurl ? ` \\\n  ${headersCurl}` : ''}${dataFlag}`;
        }
    }
});

// Shared copy to clipboard helper supporting standard browser API and textarea fallback
const copyTextToClipboard = (text: string) => {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text);
    } else {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
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
        escaped = escaped.replace(/(#[^\n]*)/g, (_, comment) =>
            addPlaceholder(comment, 'text-zinc-500 dark:text-zinc-400 italic'),
        );
    } else {
        escaped = escaped.replace(/((?<!:)\/\/[^\n]*)/g, (_, comment) =>
            addPlaceholder(comment, 'text-zinc-500 dark:text-zinc-400 italic'),
        );
    }

    // b. Extract strings
    escaped = escaped.replace(/("(\\.|[^"\\])*")/g, (_, str) =>
        addPlaceholder(
            str,
            'text-emerald-600 dark:text-emerald-400 font-medium',
        ),
    );
    escaped = escaped.replace(/('(\\.|[^'\\])*')/g, (_, str) =>
        addPlaceholder(
            str,
            'text-emerald-600 dark:text-emerald-400 font-medium',
        ),
    );
    escaped = escaped.replace(/(`(\\.|[^`\\])*`)/g, (_, str) =>
        addPlaceholder(
            str,
            'text-emerald-600 dark:text-emerald-400 font-medium',
        ),
    );

    // 3. Highlight core language terms
    // Keywords
    const keywords = [
        'const',
        'let',
        'var',
        'import',
        'from',
        'return',
        'function',
        'func',
        'use',
        'require',
        'new',
        'def',
        'package',
        'case',
        'switch',
        'default',
        'nil',
        'err',
        'if',
        'panic',
        'defer',
        'class',
        'PHP',
        'GuzzleHttp',
        'public',
        'static',
        'void',
        'throws',
    ];
    const keywordRegex = new RegExp(`\\b(${keywords.join('|')})\\b`, 'g');
    escaped = escaped.replace(
        keywordRegex,
        '<span class="text-pink-600 dark:text-pink-400 font-semibold">$1</span>',
    );

    // Built-ins and common APIs
    const builtins = [
        'fetch',
        'axios',
        'requests',
        'Client',
        'NewRequest',
        'curl_init',
        'curl_setopt_array',
        'curl_exec',
        'curl_close',
        'echo',
        'print',
        'response',
        'Header',
        'Set',
        'Do',
        'ReadAll',
        'Println',
        'then',
        'catch',
        'HttpClient',
        'HttpRequest',
        'HttpResponse',
        'URI',
        'System',
        'out',
        'println',
        'BodyPublishers',
        'BodyHandlers',
        'newHttpClient',
        'newBuilder',
        'ofString',
        'noBody',
        'send',
    ];
    const builtinRegex = new RegExp(`\\b(${builtins.join('|')})\\b`, 'g');
    escaped = escaped.replace(
        builtinRegex,
        '<span class="text-sky-600 dark:text-sky-400">$1</span>',
    );

    // Numbers & Booleans
    escaped = escaped.replace(
        /\b(true|false|null|0|10|CURLOPT_[A-Z_]+)\b/g,
        '<span class="text-orange-600 dark:text-orange-400">$1</span>',
    );

    // HTTP Methods
    escaped = escaped.replace(
        /\b(GET|POST|PUT|DELETE|PATCH)\b/g,
        '<span class="text-amber-600 dark:text-amber-400 font-bold">$1</span>',
    );

    // Variables / properties
    escaped = escaped.replace(
        /(\$[a-zA-Z_][a-zA-Z0-9_]*)/g,
        '<span class="text-indigo-600 dark:text-indigo-300 font-semibold">$1</span>',
    );

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

// Copy Permalink to Clipboard
const permalinkCopied = ref(false);
const copyPermalink = (id: string) => {
    const url = new URL(window.location.href);
    url.hash = id;
    copyTextToClipboard(url.toString());
    permalinkCopied.value = true;
    setTimeout(() => {
        permalinkCopied.value = false;
    }, 2000);
};

// Sync selectedRequestId with URL hash
watch(selectedRequestId, (newId) => {
    if (newId) {
        window.history.replaceState(null, '', `#${newId}`);
    } else {
        window.history.replaceState(
            null,
            '',
            window.location.pathname + window.location.search,
        );
    }
});

// Setup initial theme on load
onMounted(() => {
    const savedTheme = localStorage.getItem('theme');

    if (
        savedTheme === 'dark' ||
        (!savedTheme &&
            window.matchMedia('(prefers-color-scheme: dark)').matches)
    ) {
        isDark.value = true;
        document.documentElement.classList.add('dark');
    } else {
        isDark.value = false;
        document.documentElement.classList.remove('dark');
    }

    if (props.documentation?.settings?.favicon_path) {
        const customFavicon = `/storage/${props.documentation.settings.favicon_path}`;
        const links = document.querySelectorAll("link[rel*='icon']");
        links.forEach((link: any) => {
            link.href = customFavicon;
        });
    }

    // Read initial hash for permalink
    if (window.location.hash) {
        const hashId = window.location.hash.substring(1);

        if (
            hashId &&
            props.requests &&
            props.requests.some((r) => r.id === hashId)
        ) {
            selectedRequestId.value = hashId;
        }
    }
});
</script>

<template>
    <Head
        :title="
            ((props.collection && props.collection.name) ||
                'API Documentation') + ' - API Reference'
        "
    >
        <meta
            v-if="props.documentation?.settings?.logo_path"
            head-key="og:image"
            property="og:image"
            :content="`${$page.props.appUrl}/storage/${props.documentation.settings.logo_path}`"
        />
    </Head>

    <div
        class="flex h-screen w-screen flex-col overflow-hidden bg-background font-sans text-foreground"
    >
        <!-- Gorgeous Top Bar Banner -->
        <header
            class="sticky top-0 z-40 flex h-[69px] w-full shrink-0 items-center justify-between gap-4 border-b border-border bg-background/90 px-6 py-4 backdrop-blur-md select-none"
        >
            <div class="flex min-w-0 flex-1 items-center gap-3">
                <div
                    v-if="props.documentation?.settings?.logo_path"
                    class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden"
                >
                    <img
                        :src="`/storage/${props.documentation.settings.logo_path}`"
                        class="h-full w-full object-contain"
                    />
                </div>
                <div
                    v-else
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 shadow-xs"
                >
                    <BookOpen class="h-5 w-5 text-primary" />
                </div>
                <div
                    v-if="props.collection && props.documentation"
                    class="relative max-w-[calc(100%-48px)] shrink-0"
                >
                    <div
                        v-if="
                            props.publicDocsList &&
                            props.publicDocsList.length > 0
                        "
                        class="relative max-w-full"
                    >
                        <button
                            @click="showDocsDropdown = !showDocsDropdown"
                            class="-ml-1 flex cursor-pointer items-center gap-2.5 rounded-xl border border-border/60 bg-muted/20 px-3 py-1.5 text-left text-base font-extrabold tracking-tight text-foreground shadow-2xs transition-colors hover:bg-muted/50 hover:text-primary"
                        >
                            <span class="whitespace-nowrap">{{
                                props.collection.name
                            }}</span>
                            <span
                                class="shrink-0 rounded-full border border-primary/20 bg-primary/10 px-2 py-0.5 text-[10px] font-bold whitespace-nowrap text-primary"
                            >
                                v{{ props.documentation.version }}
                            </span>
                            <ChevronDown
                                v-if="props.publicDocsList.length > 1"
                                class="h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-200"
                                :class="{ 'rotate-180': showDocsDropdown }"
                            />
                        </button>

                        <div
                            v-if="
                                showDocsDropdown &&
                                props.publicDocsList.length > 1
                            "
                            @click="showDocsDropdown = false"
                            class="fixed inset-0 z-40"
                        ></div>

                        <!-- Dropdown Menu -->
                        <div
                            v-if="
                                showDocsDropdown &&
                                props.publicDocsList.length > 1
                            "
                            class="absolute top-full left-0 z-50 mt-2 max-w-[90vw] min-w-[260px] rounded-2xl border border-border bg-popover py-1.5 text-popover-foreground shadow-xl backdrop-blur-xl"
                        >
                            <div
                                class="border-b border-border/60 px-3.5 py-2 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Switch API Collection
                            </div>
                            <div
                                class="max-h-80 space-y-0.5 overflow-y-auto px-1 py-1"
                            >
                                <button
                                    v-for="docItem in props.publicDocsList"
                                    :key="docItem.id"
                                    @click="switchCollection(docItem)"
                                    class="flex w-full cursor-pointer items-center justify-between rounded-xl px-3 py-2 text-left text-xs font-semibold transition-all"
                                    :class="
                                        docItem.collection_id ===
                                        props.collection?.id
                                            ? 'bg-primary/15 font-bold text-primary shadow-2xs'
                                            : 'text-foreground hover:bg-muted/50'
                                    "
                                >
                                    <span class="truncate pr-2">{{
                                        docItem.name
                                    }}</span>
                                    <span
                                        class="shrink-0 rounded-md border border-border bg-muted px-1.5 py-0.5 font-mono text-[10px]"
                                    >
                                        v{{ docItem.version }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <h1
                        v-else
                        class="flex items-center gap-2 text-base font-extrabold tracking-tight text-foreground"
                    >
                        <span class="whitespace-nowrap">{{
                            props.collection.name
                        }}</span>
                        <span
                            class="shrink-0 rounded-full border border-primary/20 bg-primary/10 px-2 py-0.5 text-[10px] font-bold whitespace-nowrap text-primary"
                        >
                            v{{ props.documentation.version }}
                        </span>
                    </h1>
                </div>
                <div v-else>
                    <h1
                        class="text-base font-extrabold tracking-tight text-foreground"
                    >
                        API Documentation Portal
                    </h1>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-3">
                <button
                    @click="toggleTheme"
                    class="shrink-0 cursor-pointer rounded-lg border border-border bg-muted/30 p-2 text-muted-foreground transition-all hover:text-foreground"
                    aria-label="Toggle Night Mode"
                >
                    <Sun v-if="isDark" class="h-4 w-4" />
                    <Moon v-else class="h-4 w-4" />
                </button>
            </div>
        </header>

        <OfflineOverlay />

        <!-- Main Workspace: 3 Column resizable layout or Empty State -->
        <ResizablePanelGroup
            v-if="props.collection && props.documentation"
            id="public-viewer-main-group"
            auto-save-id="public-viewer-main-group"
            direction="horizontal"
            class="mx-auto min-h-0 w-full max-w-[1920px] flex-1 overflow-hidden"
        >
            <!-- Column 1: Left Navigation Sidebar -->
            <ResizablePanel
                id="public-viewer-sidebar-panel"
                :default-size="20"
                :min-size="15"
                :max-size="45"
                class="flex h-full min-h-0 min-w-0 flex-col overflow-hidden"
            >
                <aside
                    class="flex h-full min-h-0 min-w-0 flex-1 flex-col gap-5 overflow-y-auto p-5"
                >
                    <!-- Interactive Search Bar -->
                    <div class="relative w-full">
                        <Search
                            class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground/60"
                        />
                        <input
                            type="text"
                            v-model="searchQuery"
                            placeholder="Search API endpoints..."
                            class="h-9 w-full rounded-md border border-input bg-muted/20 py-1.5 pr-3 pl-9 text-xs transition-all placeholder:text-muted-foreground/70 focus:border-primary focus:ring-1 focus:ring-primary focus:outline-hidden"
                        />
                    </div>

                    <!-- Navigation List -->
                    <div class="flex flex-col gap-4">
                        <!-- Home / Overview Link -->
                        <button
                            @click="selectedRequestId = null"
                            class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-left text-xs font-bold transition-colors"
                            :class="
                                selectedRequestId === null
                                    ? 'border border-primary/10 bg-primary/10 text-primary'
                                    : 'text-muted-foreground hover:bg-muted/50'
                            "
                        >
                            <Globe class="h-4 w-4" />
                            Overview Guide
                        </button>

                        <!-- Group Requests by Folder (Collapsible Tree) -->
                        <div class="space-y-1.5">
                            <!-- Root Folders -->
                            <DocFolderNode
                                v-for="folder in rootFolders"
                                :key="folder.id"
                                :folder="folder"
                                :folders="props.collection.folders || []"
                                :requests="filteredRequests"
                                :selected-request-id="selectedRequestId || ''"
                                :get-method-color="getMethodClass"
                                @select-request="
                                    (id) => (selectedRequestId = id)
                                "
                            />

                            <!-- Root Endpoints (Not inside any folder) -->
                            <div
                                v-if="rootRequests.length > 0"
                                class="space-y-0.5"
                                :class="{
                                    'mt-2 border-t border-border/50 pt-2':
                                        rootFolders.length > 0,
                                }"
                            >
                                <h4
                                    v-if="rootFolders.length > 0"
                                    class="mb-1 px-2 text-[10px] font-bold tracking-widest text-muted-foreground uppercase"
                                >
                                    Root Endpoints
                                </h4>
                                <button
                                    v-for="req in rootRequests"
                                    :key="req.id"
                                    @click="selectedRequestId = req.id"
                                    class="group flex w-full items-center gap-2 rounded-md p-1.5 text-left text-xs transition-colors"
                                    :class="
                                        selectedRequestId === req.id
                                            ? 'border border-border/50 bg-sidebar-accent font-semibold text-sidebar-accent-foreground shadow-sm'
                                            : 'text-muted-foreground hover:bg-muted'
                                    "
                                >
                                    <span
                                        class="inline-flex w-10 shrink-0 items-center justify-center rounded border px-1 py-0.5 text-center text-[8px] leading-none font-bold uppercase select-none"
                                        :class="getMethodClass(req.method)"
                                    >
                                        {{ req.method }}
                                    </span>
                                    <span class="flex-1 truncate">{{
                                        req.name
                                    }}</span>
                                    <ChevronRight
                                        class="h-3 w-3 shrink-0 opacity-50 transition-opacity group-hover:opacity-100"
                                    />
                                </button>
                            </div>
                        </div>
                    </div>
                </aside>
            </ResizablePanel>

            <ResizableHandle id="public-viewer-handle-1" with-handle />

            <!-- Column 2: Center Content & Markdown Reader -->
            <ResizablePanel
                id="public-viewer-content-panel"
                :default-size="50"
                :min-size="25"
                class="flex h-full min-h-0 min-w-0 flex-col overflow-hidden"
            >
                <main
                    class="h-full min-h-0 min-w-0 flex-1 overflow-y-auto p-6 select-text lg:p-8"
                >
                    <!-- Overview Guide View -->
                    <div
                        v-if="selectedRequestId === null"
                        class="animate-in space-y-8 duration-300 fade-in"
                    >
                        <div
                            class="prose dark:prose-invert max-w-none"
                            v-html="introHtml"
                        ></div>

                        <!-- Global Auth Information (if present) -->
                        <div
                            v-if="props.documentation.auth_info"
                            class="mt-8 rounded-xl border border-border bg-muted/20 p-5"
                        >
                            <h3
                                class="mb-3 flex items-center gap-1.5 text-sm font-extrabold tracking-widest text-muted-foreground uppercase"
                            >
                                <Sparkles class="h-4 w-4 text-primary" />
                                Authentication Guide
                            </h3>
                            <div
                                class="prose dark:prose-invert max-w-none text-xs"
                                v-html="authHtml"
                            ></div>
                        </div>
                    </div>

                    <!-- Endpoint Documentation View -->
                    <div
                        v-else-if="activeRequest"
                        class="animate-in space-y-6 duration-200 fade-in"
                    >
                        <!-- Method + Name -->
                        <div>
                            <div class="mb-2 flex items-center gap-2">
                                <span
                                    class="inline-flex items-center justify-center rounded border px-2 py-0.5 text-[10px] leading-none font-bold uppercase select-none"
                                    :class="
                                        getMethodClass(activeRequest.method)
                                    "
                                >
                                    {{ activeRequest.method }}
                                </span>
                                <span
                                    class="text-xs font-bold tracking-widest text-muted-foreground uppercase"
                                    >API Endpoint</span
                                >
                            </div>
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <h2
                                    class="text-xl font-extrabold tracking-tight text-foreground"
                                >
                                    {{ activeRequest.name }}
                                </h2>
                                <button
                                    @click="copyPermalink(activeRequest.id)"
                                    class="flex shrink-0 items-center gap-1.5 rounded-md border border-border/50 px-2.5 py-1.5 text-[10px] font-bold tracking-wider text-muted-foreground uppercase transition-colors hover:bg-muted/50 hover:text-foreground"
                                    title="Copy documentation permalink"
                                >
                                    <Check
                                        v-if="permalinkCopied"
                                        class="h-3.5 w-3.5 text-emerald-500"
                                    />
                                    <Copy v-else class="h-3.5 w-3.5" />
                                    <span>Permalink</span>
                                </button>
                            </div>
                        </div>

                        <!-- URL Bar with copy -->
                        <div
                            class="flex items-center justify-between gap-3 rounded-lg border bg-muted/30 p-3 font-mono text-xs break-all text-foreground/80 select-all"
                        >
                            <span class="truncate">{{
                                substituteEnvVariables(
                                    activeRequest.url || 'No URL configured',
                                )
                            }}</span>
                            <button
                                @click="
                                    copyEndpointUrl(
                                        substituteEnvVariables(
                                            activeRequest.url || '',
                                        ),
                                    )
                                "
                                class="shrink-0 rounded p-1 text-muted-foreground transition-colors hover:bg-border hover:text-foreground"
                                title="Copy URL Path"
                            >
                                <Check
                                    v-if="pathCopied"
                                    class="h-3.5 w-3.5 text-emerald-500"
                                />
                                <Copy v-else class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <!-- Markdown Description -->
                        <div
                            class="prose dark:prose-invert max-w-none text-sm leading-relaxed"
                            v-html="requestDescHtml"
                        ></div>

                        <!-- Request Headers & Query Params Tables (if present) -->
                        <div v-if="parsedHeaders.length > 0" class="space-y-3">
                            <h4
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Request Headers
                            </h4>
                            <div class="overflow-hidden rounded-lg border">
                                <table
                                    class="min-w-full divide-y divide-border text-xs"
                                >
                                    <thead class="bg-muted/40">
                                        <tr>
                                            <th
                                                class="px-4 py-2 text-left font-semibold text-muted-foreground"
                                            >
                                                Header key
                                            </th>
                                            <th
                                                class="px-4 py-2 text-left font-semibold text-muted-foreground"
                                            >
                                                Type
                                            </th>
                                            <th
                                                class="px-4 py-2 text-left font-semibold text-muted-foreground"
                                            >
                                                Mock Value
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-border bg-card"
                                    >
                                        <tr
                                            v-for="header in parsedHeaders"
                                            :key="header.key"
                                        >
                                            <td
                                                class="px-4 py-2 font-mono font-semibold text-foreground"
                                            >
                                                {{ header.key }}
                                            </td>
                                            <td
                                                class="px-4 py-2 font-mono text-[10px] text-muted-foreground"
                                            >
                                                string
                                            </td>
                                            <td
                                                class="px-4 py-2 font-mono text-muted-foreground/80 select-all"
                                            >
                                                {{ header.value }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Request Body (Form / URLEncoded) -->
                        <div
                            v-if="parsedBodyItems.length > 0"
                            class="space-y-3"
                        >
                            <h4
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Request Body
                            </h4>
                            <div class="overflow-hidden rounded-lg border">
                                <table
                                    class="min-w-full divide-y divide-border text-xs"
                                >
                                    <thead class="bg-muted/40">
                                        <tr>
                                            <th
                                                class="px-4 py-2 text-left font-semibold text-muted-foreground"
                                            >
                                                Key
                                            </th>
                                            <th
                                                class="px-4 py-2 text-left font-semibold text-muted-foreground"
                                            >
                                                Type
                                            </th>
                                            <th
                                                class="px-4 py-2 text-left font-semibold text-muted-foreground"
                                            >
                                                Description
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-border bg-card"
                                    >
                                        <tr
                                            v-for="item in parsedBodyItems"
                                            :key="item.key"
                                        >
                                            <td
                                                class="px-4 py-2 font-mono font-semibold text-foreground"
                                            >
                                                {{ item.key }}
                                                <span
                                                    v-if="item.required"
                                                    class="ml-1 text-[10px] text-red-500"
                                                    >*</span
                                                >
                                            </td>
                                            <td
                                                class="px-4 py-2 font-mono text-[10px] text-muted-foreground"
                                            >
                                                {{ item.dataType || 'string' }}
                                            </td>
                                            <td
                                                class="px-4 py-2 font-mono text-muted-foreground/80 select-all"
                                            >
                                                {{ item.description || '' }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div
                            v-else-if="rawBodyContent"
                            class="flex flex-col gap-3"
                        >
                            <h4
                                class="text-xs font-semibold tracking-wider text-muted-foreground/80 uppercase"
                            >
                                Request Body (Raw -
                                {{ rawBodyContent.language }})
                            </h4>
                            <div
                                class="overflow-hidden rounded-lg border bg-card"
                            >
                                <pre
                                    class="p-4 font-mono text-xs break-all whitespace-pre-wrap text-foreground"
                                    >{{ rawBodyContent.content }}</pre
                                >
                            </div>
                        </div>

                        <!-- Query Parameters -->
                        <div
                            v-if="parsedQueryParams.length > 0"
                            class="space-y-3"
                        >
                            <h4
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Query Parameters
                            </h4>
                            <div class="overflow-hidden rounded-lg border">
                                <table
                                    class="min-w-full divide-y divide-border text-xs"
                                >
                                    <thead class="bg-muted/40">
                                        <tr>
                                            <th
                                                class="px-4 py-2 text-left font-semibold text-muted-foreground"
                                            >
                                                Parameter
                                            </th>
                                            <th
                                                class="px-4 py-2 text-left font-semibold text-muted-foreground"
                                            >
                                                Type
                                            </th>
                                            <th
                                                class="px-4 py-2 text-left font-semibold text-muted-foreground"
                                            >
                                                Value
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-border bg-card"
                                    >
                                        <tr
                                            v-for="param in parsedQueryParams"
                                            :key="param.key"
                                        >
                                            <td
                                                class="px-4 py-2 font-mono font-semibold text-foreground"
                                            >
                                                {{ param.key }}
                                            </td>
                                            <td
                                                class="px-4 py-2 font-mono text-[10px] text-muted-foreground"
                                            >
                                                string
                                            </td>
                                            <td
                                                class="px-4 py-2 font-mono text-muted-foreground/80 select-all"
                                            >
                                                {{ param.value }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </main>
            </ResizablePanel>

            <ResizableHandle id="public-viewer-handle-2" with-handle />

            <!-- Column 3: Code Snippets & Response Mock Examples -->
            <ResizablePanel
                id="public-viewer-code-panel"
                :default-size="30"
                :min-size="20"
                :max-size="50"
                class="flex h-full min-h-0 min-w-0 flex-col overflow-hidden"
            >
                <aside
                    class="flex h-full min-h-0 min-w-0 flex-1 flex-col gap-6 overflow-y-auto bg-muted/15 p-6 select-text"
                >
                    <!-- If overview guide is selected, show general greeting details -->
                    <div
                        v-if="selectedRequestId === null"
                        class="flex h-full flex-col items-center justify-center gap-4 px-6 py-12 text-center"
                    >
                        <div
                            class="mb-2 rounded-full border border-primary/20 bg-primary/10 p-4 shadow-xs"
                        >
                            <Sparkles class="h-7 w-7 text-primary" />
                        </div>
                        <h3 class="text-sm font-bold text-foreground">
                            API Reference Interactive Console
                        </h3>
                        <p class="max-w-[280px] text-xs text-muted-foreground">
                            Select any request endpoint in the left navigation
                            sidebar to view interactive request code snippets
                            and live response examples instantly.
                        </p>
                    </div>

                    <template v-else-if="activeRequest">
                        <!-- Code Snippet Box -->
                        <div class="space-y-2">
                            <h4
                                class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                <Terminal class="h-4 w-4" />
                                Request Snippet
                            </h4>

                            <div
                                class="flex flex-col overflow-hidden rounded-xl border border-border bg-zinc-50 shadow-md dark:bg-zinc-950"
                            >
                                <!-- Snippet Language Header Selector -->
                                <div
                                    class="flex items-center justify-between border-b border-border bg-zinc-100 px-3 py-1.5 select-none dark:border-zinc-800 dark:bg-zinc-900"
                                >
                                    <div
                                        class="flex items-center gap-1 text-[10px] font-bold tracking-wider text-zinc-500 uppercase dark:text-zinc-400"
                                    >
                                        <Code class="h-3.5 w-3.5" />
                                        Languages
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <select
                                            v-model="selectedLang"
                                            class="h-7 cursor-pointer border-0 bg-transparent text-[11px] font-bold text-zinc-700 focus:outline-hidden dark:text-zinc-300"
                                        >
                                            <option
                                                value="curl"
                                                class="bg-white text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300"
                                            >
                                                cURL
                                            </option>
                                            <option
                                                value="fetch"
                                                class="bg-white text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300"
                                            >
                                                Javascript Fetch
                                            </option>
                                            <option
                                                value="axios"
                                                class="bg-white text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300"
                                            >
                                                Axios
                                            </option>
                                            <option
                                                value="python"
                                                class="bg-white text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300"
                                            >
                                                Python Requests
                                            </option>
                                            <option
                                                value="go"
                                                class="bg-white text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300"
                                            >
                                                Go http
                                            </option>
                                            <option
                                                value="php-guzzle"
                                                class="bg-white text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300"
                                            >
                                                PHP Guzzle
                                            </option>
                                            <option
                                                value="php-curl"
                                                class="bg-white text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300"
                                            >
                                                PHP cURL
                                            </option>
                                            <option
                                                value="java"
                                                class="bg-white text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300"
                                            >
                                                Java HttpClient
                                            </option>
                                        </select>
                                        <button
                                            @click="copySnippet"
                                            class="rounded p-1 text-zinc-500 transition-colors hover:bg-zinc-200 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white"
                                            title="Copy Snippet"
                                        >
                                            <Check
                                                v-if="copiedState"
                                                class="h-3.5 w-3.5 text-emerald-400"
                                            />
                                            <Copy v-else class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Snippet Body -->
                                <pre
                                    class="max-h-[300px] overflow-x-auto p-4 font-mono text-xs leading-relaxed whitespace-pre text-zinc-800 select-all dark:text-zinc-300"
                                ><code v-html="highlightedCode"></code></pre>
                            </div>
                        </div>

                        <!-- Mock response Examples Tab Selector -->
                        <div
                            v-if="
                                activeRequest.examples &&
                                activeRequest.examples.length > 0
                            "
                            class="space-y-3"
                        >
                            <h4
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Mock response Example
                            </h4>

                            <div
                                class="overflow-hidden rounded-xl border border-border bg-zinc-50 shadow-md dark:bg-zinc-950"
                            >
                                <!-- Example Header Tab Selection -->
                                <div
                                    class="flex items-center justify-between border-b border-border bg-zinc-100 px-3 py-1.5 select-none dark:border-zinc-800 dark:bg-zinc-900"
                                >
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            class="h-2 w-2 rounded-full"
                                            :class="
                                                activeRequest.examples[
                                                    activeExampleIndex[
                                                        activeRequest.id
                                                    ] || 0
                                                ]?.status_code >= 200 &&
                                                activeRequest.examples[
                                                    activeExampleIndex[
                                                        activeRequest.id
                                                    ] || 0
                                                ]?.status_code < 300
                                                    ? 'bg-emerald-500'
                                                    : 'bg-rose-500'
                                            "
                                        ></span>
                                        <select
                                            :value="
                                                activeExampleIndex[
                                                    activeRequest.id
                                                ] || 0
                                            "
                                            @change="
                                                activeExampleIndex[
                                                    activeRequest.id
                                                ] = parseInt(
                                                    (
                                                        $event.target as HTMLSelectElement
                                                    ).value,
                                                )
                                            "
                                            class="h-7 cursor-pointer border-0 bg-transparent text-[11px] font-bold text-zinc-700 focus:outline-hidden dark:text-zinc-300"
                                        >
                                            <option
                                                v-for="(
                                                    example, idx
                                                ) in activeRequest.examples"
                                                :key="example.id"
                                                :value="idx"
                                                class="bg-white text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300"
                                            >
                                                {{ example.status_code }} -
                                                {{ example.name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Example Body -->
                                <pre
                                    class="max-h-[350px] overflow-x-auto p-4 font-mono text-xs leading-relaxed whitespace-pre text-zinc-800 select-all dark:text-zinc-300"
                                ><code>{{ activeRequest.examples[activeExampleIndex[activeRequest.id] || 0]?.body || '{\n  "status": "empty"\n}' }}</code></pre>
                            </div>
                        </div>
                    </template>
                </aside>
            </ResizablePanel>
        </ResizablePanelGroup>
        <div v-else class="flex flex-1 items-center justify-center p-6">
            <div
                class="mx-auto max-w-xl space-y-4 rounded-2xl border border-dashed border-border bg-muted/10 px-6 py-16 text-center"
            >
                <div
                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-muted/40 text-muted-foreground shadow-xs"
                >
                    <BookOpen class="h-6 w-6" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-base font-bold text-foreground">
                        No Public API Collections Available
                    </h3>
                    <p class="mx-auto max-w-md text-xs text-muted-foreground">
                        No documentation collections have been published as
                        public yet. Team administrators can publish
                        documentation from the dashboard.
                    </p>
                </div>
                <a
                    href="/login"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground shadow-sm transition-all hover:bg-primary/90"
                >
                    Team Login
                </a>
            </div>
        </div>
    </div>
</template>
