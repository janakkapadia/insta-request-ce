<script setup lang="ts">
import { Head, Link, usePage, useForm } from '@inertiajs/vue3';
import {
    Terminal, Play, Activity, Users, Layers, Sparkles,
    Check, ChevronDown, ChevronUp, Github, Code, Cpu,
    Globe, Server, ArrowRight, UploadCloud, FileJson,
    CheckCircle2, AlertCircle, Loader2, Key,
    TestTube, Zap, Rocket, FlaskConical, X
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';

// Interactive API Sandbox State
const selectedMethod = ref('GET');
const requestUrl = ref('https://api.jackman.dev/v1/teams');
const isSending = ref(false);
const showResponse = ref(false);
const responseTime = ref(0);
const methods = ['GET', 'POST', 'PUT', 'DELETE'];

const selectMethod = (method: string) => {
    selectedMethod.value = method;

    if (method === 'GET') {
        requestUrl.value = 'https://api.jackman.dev/v1/teams';
    } else if (method === 'POST') {
        requestUrl.value = 'https://api.jackman.dev/v1/collections/col_902a/requests';
    } else if (method === 'PUT') {
        requestUrl.value = 'https://api.jackman.dev/v1/environments/env_prod';
    } else if (method === 'DELETE') {
        requestUrl.value = 'https://api.jackman.dev/v1/requests/req_2039';
    }
};

const sendRequest = () => {
    isSending.value = true;
    showResponse.value = false;
    const start = performance.now();
    setTimeout(() => {
        isSending.value = false;
        showResponse.value = true;
        responseTime.value = Math.round(performance.now() - start + 8);
    }, 600);
};

const mockJsonResponse = computed(() => {
    if (selectedMethod.value === 'GET') {
        return JSON.stringify({
            status: "success",
            team: {
                id: "team_InstaRequest_prod",
                name: "InstaRequest Core Team",
                plan: "Pro Enterprise",
                realtime_sync: "active",
                active_developers: [
                    { name: "Janak", role: "Owner", cursor: "editing header" },
                    { name: "Sarah", role: "Architect", cursor: "viewing schema" }
                ]
            }
        }, null, 2);
    } else if (selectedMethod.value === 'POST') {
        return JSON.stringify({
            status: "created",
            request: {
                id: "req_9921",
                collection_id: "col_902a",
                name: "Get Server Health",
                method: "POST",
                path: "/v1/health-check",
                created_at: "2026-05-20T19:25:32Z"
            }
        }, null, 2);
    } else if (selectedMethod.value === 'PUT') {
        return JSON.stringify({
            status: "success",
            updated: true,
            environment: {
                id: "env_prod",
                name: "Production API Presets",
                variables_merged: 14,
                sync_timestamp: 17792823812
            }
        }, null, 2);
    } else {
        return JSON.stringify({
            status: "deleted",
            target_id: "req_2039",
            cascade_delete: true,
            orphaned_records: 0
        }, null, 2);
    }
});

// Interactive Postman Parser State
const isDragActive = ref(false);
const parsedCollection = ref<any>(null);
const parserError = ref<string | null>(null);

const handlePostmanFile = (file: File) => {
    parserError.value = null;
    parsedCollection.value = null;

    if (!file.name.endsWith('.json')) {
        parserError.value = "Invalid file type. Please upload a Postman JSON collection.";

        return;
    }

    const reader = new FileReader();
    reader.onload = (e: any) => {
        try {
            const data = JSON.parse(e.target.result);

            if (!data.info || (!data.item && !data.requests)) {
                parserError.value = "Does not look like a standard Postman Collection V2/V2.1 JSON.";

                return;
            }

            parsedCollection.value = processCollection(data);
        } catch (err) {
            parserError.value = "Failed to parse JSON file.";
        }
    };
    reader.readAsText(file);
};

const processCollection = (data: any) => {
    const name = data.info?.name || "Imported Collection";
    const description = data.info?.description || "No description provided.";
    const requestsCount = countRequests(data.item || []);
    const items = extractFoldersAndRequests(data.item || []);

    return { name, description, requestsCount, items };
};

const countRequests = (items: any[]): number => {
    let count = 0;

    for (const item of items) {
        if (item.request) {
count++;
}

        if (item.item) {
count += countRequests(item.item);
}
    }

    return count;
};

const extractFoldersAndRequests = (items: any[]): any[] => {
    return items.slice(0, 10).map(item => {
        if (item.item) {
            return {
                type: 'folder',
                name: item.name,
                children: extractFoldersAndRequests(item.item)
            };
        } else {
            return {
                type: 'request',
                name: item.name,
                method: item.request?.method || 'GET',
                url: typeof item.request?.url === 'string' ? item.request.url : (item.request?.url?.raw || '/path')
            };
        }
    });
};

const loadSampleCollection = () => {
    parsedCollection.value = {
        name: "Acme Payments API",
        description: "Official payment integrations collection including customers, subscriptions, and logs.",
        requestsCount: 6,
        items: [
            {
                type: 'folder',
                name: "Customers API",
                children: [
                    { type: 'request', name: "Create Customer", method: "POST", url: "/v1/customers" },
                    { type: 'request', name: "Retrieve Customer By ID", method: "GET", url: "/v1/customers/:id" }
                ]
            },
            {
                type: 'folder',
                name: "Billing & Plans",
                children: [
                    { type: 'request', name: "Trigger Subscription", method: "POST", url: "/v1/subscriptions" },
                    { type: 'request', name: "Retrieve Invoices", method: "GET", url: "/v1/invoices" }
                ]
            },
            { type: 'request', name: "Check Health Status", method: "GET", url: "/v1/health" },
            { type: 'request', name: "Delete Webhook Configuration", method: "DELETE", url: "/v1/webhooks/:id" }
        ]
    };
};

const onFileDrop = (e: DragEvent) => {
    isDragActive.value = false;

    if (e.dataTransfer?.files && e.dataTransfer.files.length > 0) {
        handlePostmanFile(e.dataTransfer.files[0]);
    }
};

const onFileSelect = (e: Event) => {
    const target = e.target as HTMLInputElement;

    if (target.files && target.files.length > 0) {
        handlePostmanFile(target.files[0]);
    }
};



// Interactive API Sandbox State (Testing)
const activeTab = ref('pre-request'); // 'pre-request' | 'tests'
const preRequestScript = ref(`// Generate a dynamic timestamp\njm.request.headers['X-Timestamp'] = Date.now().toString();\n\n// Add a custom authorization header programmatically\njm.request.headers['X-Auth'] = "dummy_token";`);
const testsScript = ref(`// Assert HTTP status is 200 OK\njm.test("Status code is 200", function () {\n    jm.expect(jm.response.status).toBe(200);\n});\n\n// Validate response time is under 150ms\njm.test("Response time is acceptable", function () {\n    jm.expect(jm.response.time).toBeLessThan(150);\n});\n\n// Check if body contains a specific user object\njm.test("Response contains user data", function() {\n    const body = jm.response.body;\n    jm.expect(body.user).toBeDefined();\n    jm.expect(body.user.role).toBe("admin");\n});`);

const isTestingExecuting = ref(false);
const testResults = ref<{ name: string, passed: boolean, error?: string }[]>([]);
const simulatedResponseTime = ref(124);

const runTestScripts = () => {
    if (isTestingExecuting.value) {
return;
}

    isTestingExecuting.value = true;
    testResults.value = [];
    simulatedResponseTime.value = Math.floor(Math.random() * 50) + 100; // 100-150ms

    setTimeout(() => {
        isTestingExecuting.value = false;
        
        // Mock API object for the sandbox to match RequestEditor.vue exactly
        const jm = {
            test: (name: string, fn: Function) => {
                try {
                    const result = fn();
                    const passed = result !== false;
                    testResults.value.push({ name, passed });
                } catch (e: any) {
                    testResults.value.push({ name, passed: false, error: e.message });
                }
            },
            response: {
                status: 200,
                statusText: "OK",
                time: simulatedResponseTime.value,
                size: 1024,
                headers: {
                    "content-type": "application/json"
                },
                body: { user: { role: 'admin' }, token: 'abc' }
            },
            expect: (val: any) => ({
                toBe: (expected: any) => {
 if (val !== expected) {
throw new Error(`Expected ${expected} but got ${val}`)
} 
},
                toBeDefined: () => {
 if (val === undefined) {
throw new Error(`Expected value to be defined`)
} 
},
                toBeLessThan: (expected: number) => {
 if (val >= expected) {
throw new Error(`Expected ${val} to be less than ${expected}`)
} 
}
            }),
            request: {
                headers: {} as Record<string, string>,
                url: "https://api.jackman.dev/v1/users/auth"
            }
        };

        // Execute Pre-request
        try {
            const fn = new Function('jm', preRequestScript.value);
            fn(jm);
        } catch (e: any) {
            testResults.value.push({ name: "Pre-request Script Execution", passed: false, error: e.message });
        }

        // Execute Tests
        try {
            const fn = new Function('jm', testsScript.value);
            fn(jm);
        } catch (e: any) {
            testResults.value.push({ name: "Post-response Tests Execution", passed: false, error: e.message });
        }

    }, 800);
};

// Interactive FAQs Accordion State
const activeFaqIndex = ref<number | null>(null);
const toggleFaq = (index: number) => {
    activeFaqIndex.value = activeFaqIndex.value === index ? null : index;
};


// Structured Search Schema Markup (JSON-LD)
const seoSchemaMarkup = {
    "@context": "https://schema.org",
    "@graph": [
        {
            "@type": "WebPage",
            "@id": "https://jackman.dev/#webpage",
            "url": "https://jackman.dev/",
            "name": "InstaRequest - Modern API Collaboration Platform",
            "datePublished": "2026-01-01T08:00:00+08:00",
            "dateModified": "2026-05-24T12:00:00+08:00",
            "author": {
                "@type": "Person",
                "name": "Janak Kapadia",
                "jobTitle": "Lead Engineer",
                "url": "https://jackman.dev"
            }
        },
        {
            "@type": "SoftwareApplication",
            "@id": "https://jackman.dev/#software",
            "name": "InstaRequest",
            "applicationCategory": "DeveloperApplication",
            "operatingSystem": "All",
            "description": "A premium developer-first collaborative API platform built for realtime teamwork, API monitoring, and high-performance workflows.",
            "offers": {
                "@type": "Offer",
                "price": "0",
                "priceCurrency": "USD"
            }
        },
        {
            "@type": "Organization",
            "@id": "https://jackman.dev/#organization",
            "name": "InstaRequest API",
            "url": "https://jackman.dev",
            "logo": "https://jackman.dev/favicon.svg"
        },
        {
            "@type": "FAQPage",
            "mainEntity": [
                {
                    "@type": "Question",
                    "name": "What is InstaRequest?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "InstaRequest provides a modern API workspace for testing, collections, environments, collaboration, monitoring, and realtime team workflows, built for high-performance."
                    }
                },
                {
                    "@type": "Question",
                    "name": "Can teams collaborate in realtime?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "Yes. Developers can edit request panels, folders, environment variables, and active API monitors simultaneously inside team workspaces, complete with multiplayer cursors and real-time activity logs."
                    }
                },
                {
                    "@type": "Question",
                    "name": "Does it support API monitoring?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "Yes. InstaRequest includes fully integrated monitoring agents that trigger automated checks on response latency, status codes, and test assertions, notifying your team instantly via Slack or webhooks."
                    }
                },
                {
                    "@type": "Question",
                    "name": "Can I import collections?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "Yes. You can import standard JSON collection files directly in-browser. Our engine parses the parameters, nested layouts, and scripts, extracting it safely in milliseconds."
                    }
                },
                {
                    "@type": "Question",
                    "name": "Is AI required?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "No. AI request generation features are purely opt-in and designed to speed up typing and automated script drafting, keeping you fully in control of your core data."
                    }
                },
                {
                    "@type": "Question",
                    "name": "Is there a desktop version planned?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "No. Providing an enterprise-grade web API orchestration platform is a core pillar of our roadmap. We believe the future of API tools is web-based, eliminating the need for heavy local installs and allowing for seamless team collaboration."
                    }
                }
            ]
        }
    ]
};
</script>

<template>
    <MarketingLayout>
        <Head>
            <title>Modern API Collaboration Platform for Teams | InstaRequest</title>
            <meta name="description" content="A modern developer-first API platform for testing, monitoring, and realtime collaboration. Built for teams with powerful request workflows and premium UX." />
            <link rel="preconnect" href="https://rsms.me/" />
            <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
            <component :is="'script'" type="application/ld+json" v-html="JSON.stringify(seoSchemaMarkup)"></component>
        </Head>

        <!-- HERO SECTION -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-20 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                <!-- Text Area -->
                <div class="lg:col-span-6 flex flex-col items-start gap-6 text-left">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-green-500/30 text-green-500 text-[11px] font-semibold bg-green-500/5 animate-fade-in">
                        <Sparkles class="h-3.5 w-3.5" />
                        <span>The Ultimate Modern API Client</span>
                    </div>

                    <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight text-[#1b1b18] dark:text-white leading-[1.1]">
                        The Modern <span class="bg-gradient-to-r from-green-500 to-lime-500 bg-clip-text text-transparent">API Collaboration</span> Platform for Developer Teams
                    </h1>

                    <p class="text-base sm:text-lg text-muted-foreground leading-relaxed max-w-xl">
                        Built for realtime teamwork, API monitoring, and high-performance request workflows. Fast, elegant, and infrastructure-grade.
                    </p>


                    <div class="flex items-center gap-6 mt-2">
                        <a href="/download" class="text-xs font-semibold text-muted-foreground hover:text-foreground flex items-center gap-1.5 transition-colors">
                            <UploadCloud class="h-4 w-4 text-green-500" />
                            Download for Mac
                        </a>
                        <span class="text-xs text-muted-foreground/30">|</span>
                        <a href="https://github.com" target="_blank" class="text-xs font-semibold text-muted-foreground hover:text-foreground flex items-center gap-1.5 transition-colors">
                            <Github class="h-4 w-4" />
                            1.2k Stars
                        </a>
                    </div>
                </div>

                <!-- Interactive App Sandbox Mockup -->
                <div class="lg:col-span-6 flex flex-col items-stretch relative">
                    <!-- Glow Background behind mockup -->
                    <div class="absolute -inset-2 bg-gradient-to-tr from-green-500 to-lime-500 rounded-xl blur-lg opacity-10 dark:opacity-20 pointer-events-none"></div>

                    <!-- App Mockup Shell -->
                    <div class="relative bg-white dark:bg-[#121211] border border-[#19140015] dark:border-[#222] rounded-xl shadow-2xl overflow-hidden transition-all duration-300">
                        <!-- Top Bar (Mac Controls) -->
                        <div class="bg-[#fcfcfa] dark:bg-[#161615] px-4 py-3 border-b border-[#19140010] dark:border-[#222] flex items-center justify-between">
                            <div class="flex gap-1.5">
                                <span class="h-3 w-3 rounded-full bg-red-500/30 dark:bg-red-500/20"></span>
                                <span class="h-3 w-3 rounded-full bg-yellow-500/30 dark:bg-yellow-500/20"></span>
                                <span class="h-3 w-3 rounded-full bg-green-500/30 dark:bg-green-500/20"></span>
                            </div>
                            <span class="text-2xs font-mono text-muted-foreground tracking-wider uppercase">JACKMAN API CLIENT</span>
                            <div class="h-3 w-3"></div>
                        </div>

                        <!-- Address Bar Area -->
                        <div class="p-4 bg-[#fcfcfa] dark:bg-[#141413] border-b border-[#1914000a] dark:border-[#1f1f1e] flex flex-col gap-3">
                            <!-- Method Selector Row -->
                            <div class="flex items-center gap-2">
                                <span class="text-3xs font-mono text-muted-foreground uppercase tracking-wider shrink-0">Method</span>
                                <div class="flex gap-1 bg-[#eeeeeb] dark:bg-[#1b1b1a] p-1 rounded-lg border border-[#19140010] dark:border-[#2a2a29]">
                                    <button
                                        v-for="m in methods"
                                        :key="m"
                                        @click="selectMethod(m)"
                                        :class="[
                                            'px-2.5 py-1 text-2xs font-bold rounded transition-all',
                                            selectedMethod === m
                                                ? m === 'GET' ? 'bg-emerald-500/10 text-emerald-500' :
                                                  m === 'POST' ? 'bg-indigo-500/10 text-indigo-500' :
                                                  m === 'PUT' ? 'bg-amber-500/10 text-amber-500' : 'bg-red-500/10 text-red-500'
                                                : 'text-muted-foreground hover:bg-[#19140008] dark:hover:bg-[#222]'
                                        ]"
                                    >
                                        {{ m }}
                                    </button>
                                </div>
                            </div>

                            <!-- URL Input + Send Button Row -->
                            <div class="flex gap-2">
                                <div class="flex-grow px-3 py-2 text-xs font-mono rounded-lg border border-[#19140015] dark:border-[#222] bg-white dark:bg-[#121211] focus-within:border-green-500 flex items-center overflow-x-auto text-[#1b1b18] dark:text-[#EDEDEC] whitespace-nowrap">
                                    {{ requestUrl }}
                                </div>
                                <button
                                    @click="sendRequest"
                                    :disabled="isSending"
                                    class="px-4 py-2 bg-green-600 hover:bg-green-500 text-white rounded-lg font-semibold text-xs transition-all shadow-md shadow-green-500/10 flex items-center gap-1.5 disabled:opacity-50 cursor-pointer shrink-0"
                                >
                                    <Loader2 v-if="isSending" class="h-3 w-3 animate-spin" />
                                    <Play v-else class="h-3 w-3 fill-current" />
                                    <span>Send</span>
                                </button>
                            </div>
                        </div>

                        <!-- Response / Request Panels -->
                        <div class="grid grid-cols-1 sm:grid-cols-12 min-h-[220px]">
                            <!-- Left Sidebar (Mock Headers/Params) -->
                            <div class="sm:col-span-4 border-r border-[#1914000d] dark:border-[#1c1c1b] bg-[#fdfdfc] dark:bg-[#131312] p-4 flex flex-col gap-3 text-left">
                                <span class="text-2xs font-semibold uppercase tracking-wider text-muted-foreground">Headers (3)</span>
                                <div class="flex flex-col gap-2.5 font-mono text-3xs">
                                    <div class="flex flex-col gap-0.5 py-1.5 border-b border-[#19140008] dark:border-[#222]">
                                        <span class="text-muted-foreground text-4xs uppercase tracking-wider">Content-Type</span>
                                        <span class="text-green-500 font-semibold">application/json</span>
                                    </div>
                                    <div class="flex flex-col gap-0.5 py-1.5 border-b border-[#19140008] dark:border-[#222]">
                                        <span class="text-muted-foreground text-4xs uppercase tracking-wider">Authorization</span>
                                        <span class="text-green-500 font-semibold">Bearer jk_live_...</span>
                                    </div>
                                    <div class="flex flex-col gap-0.5 py-1.5">
                                        <span class="text-muted-foreground text-4xs uppercase tracking-wider">X-Sync-Node</span>
                                        <span class="text-green-500 font-semibold">us-east-1</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Display Area (Live Response) -->
                            <div class="sm:col-span-8 bg-[#fdfdfb] dark:bg-[#0e0e0e] p-4 flex flex-col gap-3 relative text-left">
                                <!-- Initial State -->
                                <div v-if="!isSending && !showResponse" class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center bg-[#fdfdfb]/80 dark:bg-[#0e0e0e]/80 backdrop-blur-xs">
                                    <Terminal class="h-8 w-8 text-muted-foreground/30 mb-2 animate-bounce" />
                                    <span class="text-xs font-semibold text-muted-foreground">Ready to Execute</span>
                                    <span class="text-3xs text-muted-foreground/75 mt-1">Press "Send" to trigger mock server request</span>
                                </div>

                                <!-- Loading State -->
                                <div v-if="isSending" class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center bg-[#fdfdfb]/90 dark:bg-[#0e0e0e]/90">
                                    <Loader2 class="h-8 w-8 text-green-500 animate-spin mb-2" />
                                    <span class="text-xs font-mono text-green-500">EXEUCTING REQUEST...</span>
                                </div>

                                <!-- Response Metadata Header -->
                                <div class="flex items-center justify-between border-b border-[#1914000d] dark:border-[#1e1e1d] pb-2 text-2xs">
                                    <span class="font-bold uppercase text-muted-foreground">RESPONSE BODY</span>
                                    <div class="flex items-center gap-3 font-mono">
                                        <span class="px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-500 font-bold">200 OK</span>
                                        <span class="text-muted-foreground">{{ responseTime }}ms</span>
                                        <span class="text-muted-foreground">1.3KB</span>
                                    </div>
                                </div>

                                <!-- Response Code (Vue compute) -->
                                <pre class="font-mono text-3xs text-[#1b1b18] dark:text-[#a0c5e8] overflow-y-auto leading-relaxed max-h-[160px] select-all bg-[#eeeeea] dark:bg-[#141414] p-3 rounded-lg border border-[#1914000a] dark:border-[#222]"><code>{{ mockJsonResponse }}</code></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- DEFINITION BLOCK (GEO Optimized) -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 relative z-10">
            <div class="bg-[#fafaf9] dark:bg-[#121211] border border-border/60 rounded-xl p-8 max-w-4xl mx-auto text-center md:text-left flex flex-col md:flex-row gap-6 items-center shadow-sm">
                <div class="h-12 w-12 rounded-full bg-green-500/10 text-green-500 flex items-center justify-center shrink-0">
                    <Sparkles class="h-6 w-6" />
                </div>
                <div class="flex-1">
                    <h2 class="text-xl font-bold text-[#1b1b18] dark:text-white mb-2">What is InstaRequest?</h2>
                    <p class="text-sm text-muted-foreground leading-relaxed">
                        <strong>InstaRequest</strong> is a web-based, developer-first API collaboration platform designed to be lightweight and high-performance. It enables engineering teams to build, test, and monitor APIs with real-time multiplayer synchronization, integrated uptime monitoring, and zero mandatory desktop installations.
                    </p>
                </div>
            </div>
        </section>

        <!-- BENTO FEATURES GRID -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 relative z-10">
            <div class="text-center max-w-3xl mx-auto mb-16 flex flex-col gap-3">
                <span class="text-xs font-bold uppercase tracking-wider text-green-500">Engineered for Performance</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-[#1b1b18] dark:text-white leading-tight">
                    API Orchestration Without The Clutter
                </h2>
                <p class="text-sm sm:text-base text-muted-foreground leading-relaxed">
                    Designed as a hardcore, lightweight, and infrastructure-grade developer environment. Get all your collaborative workloads synced in milliseconds.
                </p>
            </div>

            <!-- Bento Layout Grid -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <!-- Card 1: Request Builder -->
                <div class="md:col-span-8 bg-white dark:bg-[#121211] border border-[#19140012] dark:border-[#1f1f1e] rounded-xl p-8 flex flex-col justify-between gap-6 shadow-[0_4px_20px_rgba(0,0,0,0.01)] hover:border-green-500/35 transition-all">
                    <div class="flex flex-col gap-3 text-left">
                        <div class="h-10 w-10 rounded-lg bg-green-500/10 text-green-500 flex items-center justify-center">
                            <Layers class="h-5 w-5" />
                        </div>
                        <h3 class="text-xl font-bold">Fast API Request Builder Built for Developers</h3>
                        <p class="text-xs text-muted-foreground leading-relaxed max-w-xl">
                            Create and organize API requests with environments, collections, realtime sync, and powerful developer workflows. Built keyboard-first for hyper-speed request building.
                        </p>
                    </div>
                    <!-- Visual Mock -->
                    <div class="h-[120px] bg-[#fcfcfa] dark:bg-[#151514] border border-[#19140008] dark:border-[#222] rounded-lg p-3 font-mono text-3xs flex flex-col gap-2 relative overflow-hidden select-none">
                        <div class="flex items-center gap-2 border-b border-border/60 pb-1.5 text-2xs text-muted-foreground font-semibold">
                            <span>ENVIRONMENTS</span>
                            <span class="text-[9px] bg-green-500/10 text-green-500 px-1 py-0.2 rounded font-bold uppercase">PROD-ENV</span>
                        </div>
                        <div class="flex flex-col gap-1 items-start text-left">
                            <span class="text-[#777]">// Environment Variables (Inherited Automatically)</span>
                            <span class="text-green-500">baseUrl: <span class="text-foreground">"https://api.acme.com/v1"</span></span>
                            <span class="text-green-500">auth_token: <span class="text-foreground">"jk_auth_981aa12bc"</span></span>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Uptime Monitoring -->
                <div class="md:col-span-4 bg-white dark:bg-[#121211] border border-[#19140012] dark:border-[#1f1f1e] rounded-xl p-8 flex flex-col justify-between gap-6 shadow-[0_4px_20px_rgba(0,0,0,0.01)] hover:border-green-500/35 transition-all">
                    <div class="flex flex-col gap-3 text-left">
                        <div class="h-10 w-10 rounded-lg bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
                            <Activity class="h-5 w-5" />
                        </div>
                        <h3 class="text-xl font-bold">API Monitoring Built Into Your Workspace</h3>
                        <p class="text-xs text-muted-foreground leading-relaxed">
                            Track uptime, latency, failures, and performance metrics directly inside your active API collection window.
                        </p>
                    </div>
                    <!-- Latency Sparkline Mock -->
                    <div class="bg-[#fcfcfa] dark:bg-[#151514] border border-[#19140008] dark:border-[#222] rounded-lg p-4 flex flex-col gap-1.5 relative overflow-hidden">
                        <div class="flex items-center justify-between text-2xs">
                            <span class="font-semibold text-muted-foreground">LATENCY SPARKLINE (24H)</span>
                            <span class="text-emerald-500 font-bold font-mono">99.98% Uptime</span>
                        </div>
                        <!-- Mock Wave -->
                        <div class="h-8 flex items-end gap-1 pt-2">
                            <div v-for="i in 18" :key="i" class="flex-grow bg-emerald-500/20 dark:bg-emerald-500/30 rounded-t" :style="{ height: `${Math.sin(i * 0.5) * 12 + 18}px` }"></div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Multiplayer Sync -->
                <div class="md:col-span-4 bg-white dark:bg-[#121211] border border-[#19140012] dark:border-[#1f1f1e] rounded-xl p-8 flex flex-col justify-between gap-6 shadow-[0_4px_20px_rgba(0,0,0,0.01)] hover:border-green-500/35 transition-all">
                    <div class="flex flex-col gap-3 text-left">
                        <div class="h-10 w-10 rounded-lg bg-indigo-500/10 text-indigo-500 flex items-center justify-center">
                            <Users class="h-5 w-5" />
                        </div>
                        <h3 class="text-xl font-bold">Realtime Collaboration for API Teams</h3>
                        <p class="text-xs text-muted-foreground leading-relaxed">
                            Work together on collections, requests, environments, and monitoring with multiplayer sync and live workspaces.
                        </p>
                    </div>
                    <!-- Multiplayer Cursor visual -->
                    <div class="h-[100px] bg-[#fcfcfa] dark:bg-[#151514] border border-[#19140008] dark:border-[#222] rounded-lg p-3 relative overflow-hidden select-none">
                        <div class="absolute top-2 left-6 px-2 py-1 bg-green-500 text-white rounded text-3xs font-mono shadow-sm flex items-center gap-1">
                            <span class="h-1 w-1 bg-white rounded-full animate-ping"></span>
                            <span>Janak Kapadia (editing path)</span>
                        </div>
                        <div class="absolute bottom-4 right-10 px-2 py-1 bg-indigo-500 text-white rounded text-3xs font-mono shadow-sm flex items-center gap-1">
                            <span class="h-1 w-1 bg-white rounded-full animate-ping"></span>
                            <span>Sarah L. (editing parameters)</span>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Pre-request & Test Scripts -->
                <div class="md:col-span-8 bg-white dark:bg-[#121211] border border-[#19140012] dark:border-[#1f1f1e] rounded-xl p-8 flex flex-col justify-between gap-6 shadow-[0_4px_20px_rgba(0,0,0,0.01)] hover:border-green-500/35 transition-all">
                    <div class="flex flex-col gap-3 text-left">
                        <div class="h-10 w-10 rounded-lg bg-blue-500/10 text-blue-500 flex items-center justify-center">
                            <Code class="h-5 w-5" />
                        </div>
                        <h3 class="text-xl font-bold">Pre-request & Post-response Test Scripts</h3>
                        <p class="text-xs text-muted-foreground leading-relaxed max-w-xl">
                            Write JavaScript to execute before a request runs or after a response is received. Assert data formats, validate JSON schemas, and securely chain complex request workflows.
                        </p>
                    </div>
                    <!-- Interactive Scripting playground -->
                    <div class="flex flex-col gap-2 bg-[#fcfcfa] dark:bg-[#151514] border border-[#19140008] dark:border-[#222] rounded-lg p-4 text-left">
                        <div class="flex items-center gap-4 border-b border-border/60 pb-2 mb-1">
                            <span class="text-2xs font-bold text-muted-foreground">Pre-request Script</span>
                            <span class="text-2xs font-bold text-green-500 border-b-2 border-green-500 pb-2 -mb-2.5">Tests</span>
                        </div>
                        <pre class="font-mono text-3xs text-[#1b1b18] dark:text-[#a0c5e8] leading-relaxed max-h-[120px] overflow-y-auto"><code><span class="text-purple-500 dark:text-purple-400">jm</span>.test(<span class="text-green-600 dark:text-green-400">"Status code is 200"</span>, <span class="text-blue-500 dark:text-blue-400">function</span> () {
    <span class="text-purple-500 dark:text-purple-400">jm</span>.expect(<span class="text-purple-500 dark:text-purple-400">jm</span>.response.status).toBe(<span class="text-green-500 dark:text-green-400">200</span>);
});

<span class="text-purple-500 dark:text-purple-400">jm</span>.test(<span class="text-green-600 dark:text-green-400">"Response time is less than 200ms"</span>, <span class="text-blue-500 dark:text-blue-400">function</span> () {
    <span class="text-purple-500 dark:text-purple-400">jm</span>.expect(<span class="text-purple-500 dark:text-purple-400">jm</span>.response.time).toBeLessThan(<span class="text-green-500 dark:text-green-400">200</span>);
});</code></pre>
                    </div>
                </div>
            </div>
        </section>

        <!-- Interactive Testing Playground Section -->
        <section id="interactive-demo" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 relative z-10 border-t border-[#1914000d] dark:border-[#1a1a19]">
            <div class="text-center max-w-3xl mx-auto mb-16 flex flex-col gap-3">
                <span class="text-xs font-bold uppercase tracking-wider text-green-500">Test Automation & Scripting</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-[#1b1b18] dark:text-white leading-tight">
                    Script Everything. Automate Anything.
                </h2>
                <p class="text-sm sm:text-base text-muted-foreground leading-relaxed">
                    Take full control of your API lifecycle with powerful JavaScript execution. Write pre-request setup scripts, post-response data validation, and complex request chains using standard syntax.
                </p>
            </div>
            
            <div class="bg-[#fafaf9] dark:bg-[#121211] border border-border/80 rounded-2xl p-2 shadow-2xl overflow-hidden ring-1 ring-black/5 dark:ring-white/5">
                
                <!-- Mock IDE Header -->
                <div class="flex items-center justify-between px-4 py-3 border-b border-border/60 bg-[#f4f4f2] dark:bg-[#161615] rounded-t-xl">
                    <div class="flex items-center gap-3">
                        <div class="flex gap-1.5">
                            <div class="w-3 h-3 rounded-full bg-red-500/80"></div>
                            <div class="w-3 h-3 rounded-full bg-amber-500/80"></div>
                            <div class="w-3 h-3 rounded-full bg-emerald-500/80"></div>
                        </div>
                        <span class="ml-2 text-2xs font-mono font-semibold text-muted-foreground">GET https://api.jackman.dev/v1/users/auth</span>
                    </div>
                    
                    <button 
                        @click="runTestScripts"
                        :disabled="isTestingExecuting"
                        class="px-4 py-1.5 bg-blue-600 text-white rounded text-xs font-semibold hover:bg-blue-700 transition-all flex items-center gap-1.5 disabled:opacity-50"
                    >
                        <Play v-if="!isTestingExecuting" class="h-3.5 w-3.5" />
                        <Rocket v-else class="h-3.5 w-3.5 animate-pulse" />
                        <span>{{ isTestingExecuting ? 'Running Scripts...' : 'Send & Execute' }}</span>
                    </button>
                </div>

                <!-- Split Pane Layout -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-px bg-border/40 min-h-[400px]">
                    
                    <!-- Left Pane: Editor -->
                    <div class="bg-white dark:bg-[#121211] flex flex-col">
                        <!-- Tabs -->
                        <div class="flex items-center border-b border-border/60 bg-[#fdfdfb] dark:bg-[#161615]">
                            <button 
                                @click="activeTab = 'pre-request'"
                                :class="[
                                    'px-4 py-2.5 text-xs font-semibold border-b-2 transition-colors flex items-center gap-1.5',
                                    activeTab === 'pre-request' ? 'border-blue-500 text-blue-600 dark:text-blue-400 bg-white dark:bg-[#121211]' : 'border-transparent text-muted-foreground hover:text-foreground'
                                ]"
                            >
                                <Terminal class="h-3.5 w-3.5" />
                                Pre-request Script
                            </button>
                            <button 
                                @click="activeTab = 'tests'"
                                :class="[
                                    'px-4 py-2.5 text-xs font-semibold border-b-2 transition-colors flex items-center gap-1.5',
                                    activeTab === 'tests' ? 'border-blue-500 text-blue-600 dark:text-blue-400 bg-white dark:bg-[#121211]' : 'border-transparent text-muted-foreground hover:text-foreground'
                                ]"
                            >
                                <TestTube class="h-3.5 w-3.5" />
                                Post-response Tests
                            </button>
                        </div>
                        
                        <!-- Editor Area -->
                        <div class="flex-1 p-4 overflow-y-auto">
                            <textarea 
                                v-if="activeTab === 'pre-request'"
                                v-model="preRequestScript"
                                class="w-full h-full min-h-[300px] bg-transparent resize-none focus:outline-none font-mono text-xs text-foreground leading-relaxed"
                                spellcheck="false"
                            ></textarea>
                            <textarea 
                                v-if="activeTab === 'tests'"
                                v-model="testsScript"
                                class="w-full h-full min-h-[300px] bg-transparent resize-none focus:outline-none font-mono text-xs text-foreground leading-relaxed"
                                spellcheck="false"
                            ></textarea>
                        </div>
                    </div>

                    <!-- Right Pane: Results -->
                    <div class="bg-[#fafaf9] dark:bg-[#151514] flex flex-col">
                        <div class="px-4 py-2.5 border-b border-border/60 text-xs font-semibold text-muted-foreground flex items-center gap-1.5 bg-[#fdfdfb] dark:bg-[#161615]">
                            <CheckCircle2 class="h-3.5 w-3.5 text-emerald-500" />
                            Test Results
                        </div>
                        
                        <div class="flex-1 p-4 overflow-y-auto flex flex-col gap-2">
                            <div v-if="testResults.length === 0 && !isTestingExecuting" class="h-full flex flex-col items-center justify-center text-muted-foreground text-xs p-8 text-center gap-3">
                                <FlaskConical class="h-8 w-8 opacity-20" />
                                <p>Click "Send & Execute" to run your pre-request script, make the API call, and evaluate your tests.</p>
                            </div>
                            
                            <div v-else-if="isTestingExecuting" class="h-full flex flex-col items-center justify-center text-blue-500 text-xs gap-3">
                                <Zap class="h-6 w-6 animate-pulse" />
                                <span>Executing sandbox runtime...</span>
                            </div>

                            <div v-else class="flex flex-col gap-2">
                                <div class="px-3 py-2 rounded text-xs font-semibold mb-2 flex items-center justify-between border"
                                    :class="testResults.length > 0 && testResults.every(r => r.passed) ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' : 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/20'">
                                    <span>{{ testResults.filter(r => r.passed).length }} / {{ testResults.length }} Tests Passed</span>
                                    <span class="font-mono px-1.5 py-0.5 rounded text-[10px]"
                                        :class="testResults.length > 0 && testResults.every(r => r.passed) ? 'bg-emerald-500/20' : 'bg-red-500/20'">
                                        {{ simulatedResponseTime }}ms
                                    </span>
                                </div>
                                <div 
                                    v-for="(result, index) in testResults" 
                                    :key="index"
                                    class="flex flex-col gap-1 text-xs p-2 rounded bg-white dark:bg-[#1a1a19] border border-border/50 shadow-sm"
                                >
                                    <div class="flex items-center gap-2">
                                        <Check v-if="result.passed" class="h-4 w-4 text-emerald-500 shrink-0" />
                                        <X v-else class="h-4 w-4 text-red-500 shrink-0" />
                                        <span class="font-mono text-foreground">{{ result.name }}</span>
                                    </div>
                                    <div v-if="!result.passed && result.error" class="text-red-500 text-[10px] pl-6 font-mono break-all mt-1">
                                        {{ result.error }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>


        <!-- SPEED / CORE METRICS SECTION -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 relative z-10 border-t border-[#1914000d] dark:border-[#1a1a19] text-center">
            <div class="max-w-3xl mx-auto flex flex-col gap-4">
                <span class="text-xs font-bold uppercase tracking-wider text-green-500">Infrastructure Grade Performance</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-[#1b1b18] dark:text-white leading-tight">
                    Built for Speed and Large API Workflows
                </h2>
                <p class="text-sm text-muted-foreground leading-relaxed">
                    Designed for speed to eliminate cloud lag, network latency, and memory bloat. Get responsive navigation and instant requests inside a web app that loads in under a second.
                </p>
            </div>

            <!-- Specs Grid -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mt-16 max-w-4xl mx-auto text-left">
                <div class="p-6 bg-white dark:bg-[#121211] border border-[#1914000d] dark:border-[#1f1f1e] rounded-xl flex flex-col gap-2">
                    <span class="text-3xl font-extrabold tracking-tight text-green-500">12ms</span>
                    <span class="text-2xs font-semibold uppercase tracking-wider text-muted-foreground">Request Latency</span>
                </div>
                <div class="p-6 bg-white dark:bg-[#121211] border border-[#1914000d] dark:border-[#1f1f1e] rounded-xl flex flex-col gap-2">
                    <span class="text-3xl font-extrabold tracking-tight text-green-500">0.1s</span>
                    <span class="text-2xs font-semibold uppercase tracking-wider text-muted-foreground">Cold Start</span>
                </div>
                <div class="p-6 bg-white dark:bg-[#121211] border border-[#1914000d] dark:border-[#1f1f1e] rounded-xl flex flex-col gap-2">
                    <span class="text-3xl font-extrabold tracking-tight text-green-500">95+</span>
                    <span class="text-2xs font-semibold uppercase tracking-wider text-muted-foreground">Lighthouse Speed</span>
                </div>
                <div class="p-6 bg-white dark:bg-[#121211] border border-[#1914000d] dark:border-[#1f1f1e] rounded-xl flex flex-col gap-2">
                    <span class="text-3xl font-extrabold tracking-tight text-green-500">100%</span>
                    <span class="text-2xs font-semibold uppercase tracking-wider text-muted-foreground">Keyboard Driven</span>
                </div>
            </div>
        </section>

        <!-- PRICING SECTION -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 relative z-10 border-t border-[#1914000d] dark:border-[#1a1a19]">
            <div class="text-center max-w-2xl mx-auto mb-16 flex flex-col gap-3">
                <span class="text-xs font-bold uppercase tracking-wider text-green-500">InstaRequest Pricing</span>
                <h2 class="text-3xl font-extrabold text-[#1b1b18] dark:text-white leading-tight">Simple Pricing for Developer Teams</h2>
                <p class="text-xs sm:text-sm text-muted-foreground">Individual accounts are always free. Team workspaces scale with your collaborators.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                <!-- Free Plan -->
                <div class="bg-white dark:bg-[#121211] border border-[#19140012] dark:border-[#1f1f1e] rounded-xl p-8 flex flex-col justify-between gap-8 relative shadow-lg hover:border-border transition-all text-left">
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center justify-between">
                            <span class="text-lg font-bold text-foreground">Developer Free</span>
                            <span class="text-2xs font-mono uppercase bg-muted text-muted-foreground px-2 py-0.5 rounded">Free Forever</span>
                        </div>
                        <p class="text-2xs text-muted-foreground">Ideal for small dev teams, independent projects, and cloud workspaces.</p>
                        <div class="flex items-baseline gap-1 mt-2">
                            <span class="text-4xl font-extrabold">$0</span>
                            <span class="text-2xs text-muted-foreground">/ user / mo</span>
                        </div>
                        <ul class="flex flex-col gap-3 text-2xs mt-4">
                            <li class="flex items-center gap-2.5">
                                <Check class="h-4 w-4 text-emerald-500 shrink-0" />
                                <span>Small teams up to 5 developers</span>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <Check class="h-4 w-4 text-emerald-500 shrink-0" />
                                <span>Basic API monitoring checks (15m interval)</span>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <Check class="h-4 w-4 text-emerald-500 shrink-0" />
                                <span>Standard request builder & variables</span>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <Check class="h-4 w-4 text-emerald-500 shrink-0" />
                                <span>Unlimited collection imports and exports</span>
                            </li>
                        </ul>
                    </div>
                    <Link href="/register" class="w-full text-center px-4 py-2 border border-green-500 text-green-500 rounded-lg hover:bg-green-500/5 transition-all text-xs font-semibold">
                        Get Started
                    </Link>
                </div>

                <!-- Pro Plan -->
                <div class="bg-white dark:bg-[#121211] border-2 border-green-500 rounded-xl p-8 flex flex-col justify-between gap-8 relative shadow-2xl text-left">
                    <div class="absolute -top-3 right-6 bg-gradient-to-r from-green-600 to-lime-500 text-white text-[9px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider">
                        RECOMMENDED
                    </div>
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center justify-between">
                            <span class="text-lg font-bold text-foreground">Pro Team</span>
                            <span class="text-2xs font-mono uppercase bg-green-500/10 text-green-500 px-2 py-0.5 rounded font-bold">Standard</span>
                        </div>
                        <p class="text-2xs text-muted-foreground">For fast-moving organizations requiring enterprise sync, monitoring, and AI integrations.</p>
                        <div class="flex items-baseline gap-1 mt-2">
                            <span class="text-4xl font-extrabold">$12</span>
                            <span class="text-2xs text-muted-foreground">/ user / mo</span>
                        </div>
                        <ul class="flex flex-col gap-3 text-2xs mt-4">
                            <li class="flex items-center gap-2.5">
                                <Check class="h-4 w-4 text-emerald-500 shrink-0" />
                                <span class="font-semibold text-foreground">Unlimited team size and collaborative collections</span>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <Check class="h-4 w-4 text-emerald-500 shrink-0" />
                                <span>High-frequency monitoring checks (1m interval)</span>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <Check class="h-4 w-4 text-emerald-500 shrink-0" />
                                <span>Advanced team authorization presets & RBAC log</span>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <Check class="h-4 w-4 text-emerald-500 shrink-0" />
                                <span>AI request builder generator & test automation</span>
                            </li>
                        </ul>
                    </div>
                    <Link href="/register" class="w-full text-center px-4 py-2.5 bg-gradient-to-r from-green-600 to-lime-500 text-white rounded-lg hover:from-green-500 hover:to-lime-400 transition-all text-xs font-bold shadow-lg shadow-green-500/10">
                        Get Started with Pro
                    </Link>
                </div>
            </div>
        </section>

        <!-- FAQ SECTION -->
        <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-20 relative z-10 border-t border-[#1914000d] dark:border-[#1a1a19]">
            <div class="text-center mb-16 flex flex-col gap-3">
                <span class="text-xs font-bold uppercase tracking-wider text-green-500">FAQ</span>
                <h2 class="text-3xl font-extrabold text-[#1b1b18] dark:text-white leading-tight">Frequently Asked Questions</h2>
                <p class="text-xs sm:text-sm text-muted-foreground">Everything you need to know about InstaRequest, migration, and billing.</p>
            </div>

            <!-- Accordion Wrapper -->
            <div class="flex flex-col gap-4">
                <!-- Question 1 -->
                <div class="border border-[#19140012] dark:border-[#1f1f1e] rounded-xl bg-white dark:bg-[#121211] overflow-hidden transition-all duration-300">
                    <button @click="toggleFaq(0)" class="w-full px-6 py-5 flex items-center justify-between font-bold text-sm text-[#1b1b18] dark:text-white hover:text-green-500 transition-colors text-left">
                        <span>Q1. What is InstaRequest?</span>
                        <ChevronDown v-if="activeFaqIndex !== 0" class="h-4 w-4 shrink-0 text-muted-foreground" />
                        <ChevronUp v-else class="h-4 w-4 shrink-0 text-muted-foreground" />
                    </button>
                    <div v-show="activeFaqIndex === 0" class="px-6 pb-5 text-xs text-muted-foreground leading-relaxed transition-all text-left">
                        InstaRequest is a premium developer-first collaborative API workspace built for high-performance, offering API testing, collections, environment variables, monitoring, and team environments.
                    </div>
                </div>

                <!-- Question 2 -->
                <div class="border border-[#19140012] dark:border-[#1f1f1e] rounded-xl bg-white dark:bg-[#121211] overflow-hidden transition-all duration-300">
                    <button @click="toggleFaq(1)" class="w-full px-6 py-5 flex items-center justify-between font-bold text-sm text-[#1b1b18] dark:text-white hover:text-green-500 transition-colors text-left">
                        <span>Q2. Can teams collaborate in realtime?</span>
                        <ChevronDown v-if="activeFaqIndex !== 1" class="h-4 w-4 shrink-0 text-muted-foreground" />
                        <ChevronUp v-else class="h-4 w-4 shrink-0 text-muted-foreground" />
                    </button>
                    <div v-show="activeFaqIndex === 1" class="px-6 pb-5 text-xs text-muted-foreground leading-relaxed transition-all text-left">
                        Yes. Developers can edit request panels, folders, environment variables, and active API monitors simultaneously inside team workspaces, complete with multiplayer cursors and real-time activity logs.
                    </div>
                </div>

                <!-- Question 3 -->
                <div class="border border-[#19140012] dark:border-[#1f1f1e] rounded-xl bg-white dark:bg-[#121211] overflow-hidden transition-all duration-300">
                    <button @click="toggleFaq(2)" class="w-full px-6 py-5 flex items-center justify-between font-bold text-sm text-[#1b1b18] dark:text-white hover:text-green-500 transition-colors text-left">
                        <span>Q3. Does it support API monitoring?</span>
                        <ChevronDown v-if="activeFaqIndex !== 2" class="h-4 w-4 shrink-0 text-muted-foreground" />
                        <ChevronUp v-else class="h-4 w-4 shrink-0 text-muted-foreground" />
                    </button>
                    <div v-show="activeFaqIndex === 2" class="px-6 pb-5 text-xs text-muted-foreground leading-relaxed transition-all text-left">
                        Yes. InstaRequest includes fully integrated monitoring agents that trigger automated checks on response latency, status codes, and test assertions, notifying your team instantly via Slack or webhooks.
                    </div>
                </div>

                <!-- Question 4 -->
                <div class="border border-[#19140012] dark:border-[#1f1f1e] rounded-xl bg-white dark:bg-[#121211] overflow-hidden transition-all duration-300">
                    <button @click="toggleFaq(3)" class="w-full px-6 py-5 flex items-center justify-between font-bold text-sm text-[#1b1b18] dark:text-white hover:text-green-500 transition-colors text-left">
                        <span>Q4. Can I import collections?</span>
                        <ChevronDown v-if="activeFaqIndex !== 3" class="h-4 w-4 shrink-0 text-muted-foreground" />
                        <ChevronUp v-else class="h-4 w-4 shrink-0 text-muted-foreground" />
                    </button>
                    <div v-show="activeFaqIndex === 3" class="px-6 pb-5 text-xs text-muted-foreground leading-relaxed transition-all text-left">
                        Yes. You can import standard JSON collection files directly in-browser. Our engine parses the parameters, nested layouts, and scripts, extracting it safely in milliseconds.
                    </div>
                </div>

                <!-- Question 5 -->
                <div class="border border-[#19140012] dark:border-[#1f1f1e] rounded-xl bg-white dark:bg-[#121211] overflow-hidden transition-all duration-300">
                    <button @click="toggleFaq(4)" class="w-full px-6 py-5 flex items-center justify-between font-bold text-sm text-[#1b1b18] dark:text-white hover:text-green-500 transition-colors text-left">
                        <span>Q5. Is AI required?</span>
                        <ChevronDown v-if="activeFaqIndex !== 4" class="h-4 w-4 shrink-0 text-muted-foreground" />
                        <ChevronUp v-else class="h-4 w-4 shrink-0 text-muted-foreground" />
                    </button>
                    <div v-show="activeFaqIndex === 4" class="px-6 pb-5 text-xs text-muted-foreground leading-relaxed transition-all text-left">
                        No. AI request generation features are purely opt-in and designed to speed up typing and automated script drafting, keeping you fully in control of your core data.
                    </div>
                </div>

                <!-- Question 6 -->
                <div class="border border-[#19140012] dark:border-[#1f1f1e] rounded-xl bg-white dark:bg-[#121211] overflow-hidden transition-all duration-300">
                    <button @click="toggleFaq(5)" class="w-full px-6 py-5 flex items-center justify-between font-bold text-sm text-[#1b1b18] dark:text-white hover:text-green-500 transition-colors text-left">
                        <span>Q6. Is there a desktop version planned?</span>
                        <ChevronDown v-if="activeFaqIndex !== 5" class="h-4 w-4 shrink-0 text-muted-foreground" />
                        <ChevronUp v-else class="h-4 w-4 shrink-0 text-muted-foreground" />
                    </button>
                    <div v-show="activeFaqIndex === 5" class="px-6 pb-5 text-xs text-muted-foreground leading-relaxed transition-all text-left">
                        No. Providing an enterprise-grade web API orchestration platform is a core pillar of our roadmap. We believe the future of API tools is web-based, eliminating the need for heavy local installs and allowing for seamless team collaboration.
                    </div>
                </div>
            </div>
        </section>
        <!-- AUTHOR & DATE META (GEO Optimized) -->
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pb-12 pt-4 text-center">
            <p class="text-[10px] text-muted-foreground/60 uppercase tracking-widest font-semibold">
                Written & Reviewed by <span class="text-foreground/80">Janak Kapadia</span> (Lead Engineer) <span class="mx-2">|</span> Last Updated: <time datetime="2026-05-24">May 24, 2026</time>
            </p>
        </div>
    </MarketingLayout>
</template>
