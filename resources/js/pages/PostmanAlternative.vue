<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Sparkles, UploadCloud, FileJson, Check, X,
    ChevronDown, ChevronUp, Terminal, Zap, ShieldAlert,
    Cpu, Loader2
} from 'lucide-vue-next';
import { ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';

// Interactive Postman Parser
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
    return items.slice(0, 8).map(item => {
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
        name: "E-Commerce Gateway Collections",
        description: "Standard API routes for user checkouts, order processing, and discount logs.",
        requestsCount: 5,
        items: [
            {
                type: 'folder',
                name: "Cart Services",
                children: [
                    { type: 'request', name: "Add Item To Cart", method: "POST", url: "/v1/cart/add" },
                    { type: 'request', name: "Fetch Cart Status", method: "GET", url: "/v1/cart" }
                ]
            },
            {
                type: 'folder',
                name: "Discount Codes",
                children: [
                    { type: 'request', name: "Apply Discount Coupon", method: "POST", url: "/v1/coupons/apply" }
                ]
            },
            { type: 'request', name: "Submit Final Checkout", method: "POST", url: "/v1/checkout" },
            { type: 'request', name: "Request Order Refund", method: "DELETE", url: "/v1/refunds/:id" }
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

// Accordions State
const activeFaqIndex = ref<number | null>(null);
const toggleFaq = (index: number) => {
    activeFaqIndex.value = activeFaqIndex.value === index ? null : index;
};

// SEO Schema
const schemaMarkup = {
    "@context": "https://schema.org",
    "@graph": [
        {
            "@type": "WebPage",
            "@id": "https://jackman.dev/postman-alternative#webpage",
            "url": "https://jackman.dev/postman-alternative",
            "name": "Postman Alternative - Modern Realtime API Tool",
            "description": "InstaRequest is the modern Postman alternative built for team collaboration, lightning-fast execution speed, and comprehensive API testing.",
            "datePublished": "2026-01-01T08:00:00+08:00",
            "dateModified": "2026-05-24T12:00:00+08:00",
            "author": {
                "@type": "Person",
                "name": "Janak Kapadia",
                "jobTitle": "Lead Engineer",
                "url": "https://jackman.dev"
            },
            "breadcrumb": {
                "@type": "BreadcrumbList",
                "itemListElement": [
                    { "@type": "ListItem", "position": 1, "name": "Home", "item": "https://jackman.dev" },
                    { "@type": "ListItem", "position": 2, "name": "Postman Alternative", "item": "https://jackman.dev/postman-alternative" }
                ]
            }
        },
        {
            "@type": "FAQPage",
            "mainEntity": [
                {
                    "@type": "Question",
                    "name": "Will my environment variables and credentials migrate?",
                    "acceptedAnswer": { "@type": "Answer", "text": "Yes. When you drag and drop a collection along with environment presets, InstaRequest parses all key-value mappings perfectly. Environment values remain stored securely in your private workspace, ensuring 100% variable privacy." }
                },
                {
                    "@type": "Question",
                    "name": "Is InstaRequest web-based or a desktop app?",
                    "acceptedAnswer": { "@type": "Answer", "text": "InstaRequest is 100% web-based. You don't need to download or install any heavy desktop applications. Everything runs smoothly in your browser with zero bloat and instant access from any device." }
                },
                {
                    "@type": "Question",
                    "name": "Can I export my InstaRequest collections back to Postman?",
                    "acceptedAnswer": { "@type": "Answer", "text": "Yes. InstaRequest fully respects open API tooling standards. You can download and export all collections and schemas as standard Postman V2 formats or standard OpenAPI spec YAML sheets at any time." }
                }
            ]
        }
    ]
};
</script>

<template>
    <MarketingLayout>
        <Head>
            <title>Modern Postman Alternative for Developers | InstaRequest</title>
            <meta name="description" content="Switch to a modern developer-first Postman alternative. Lightning-fast web platform, realtime multiplayer sync, and integrated monitoring with zero bloat." />
            <component :is="'script'" type="application/ld+json" v-html="JSON.stringify(schemaMarkup)"></component>
        </Head>

        <!-- Breadcrumbs -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
            <nav class="flex text-3xs font-mono uppercase tracking-wider text-muted-foreground gap-2">
                <Link href="/" class="hover:text-foreground">Home</Link>
                <span>/</span>
                <span class="text-green-500 font-semibold">Postman Alternative</span>
            </nav>
        </div>

        <!-- Hero header -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-16 text-center relative z-10 flex flex-col items-center gap-6">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-green-500/30 text-green-500 text-[11px] font-semibold bg-green-500/5">
                <Zap class="h-3.5 w-3.5" />
                <span>Bloat-free, Web-based Platform</span>
            </div>
            <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-[#1b1b18] dark:text-white max-w-4xl leading-tight">
                The Modern <span class="bg-gradient-to-r from-green-500 to-lime-500 bg-clip-text text-transparent">Postman Alternative</span> Built for Teams
            </h1>
            <p class="text-base sm:text-lg text-muted-foreground leading-relaxed max-w-2xl">
                Migrate away from bloated Electron apps, mandatory desktop installs, and team-tier limitations. Get the speed of a modern web platform built for high-performance API workflows.
            </p>
        </section>

        <!-- Migration tool zone -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                <!-- Instruction area -->
                <div class="lg:col-span-5 flex flex-col items-start gap-6 text-left">
                    <h2 class="text-2xl font-bold">Import in one click and keep working</h2>
                    <p class="text-xs text-muted-foreground leading-relaxed">
                        No manual rewrites needed. Drag and drop any Postman collection exports to see them parsed in 0.2 seconds inside the browser. InstaRequest completely preserves all parameters, folder hierarchies, and configurations.
                    </p>
                    <div class="flex flex-col gap-3 font-semibold text-2xs text-[#1b1b18] dark:text-[#eee]">
                        <div class="flex items-center gap-2.5">
                            <Check class="h-4.5 w-4.5 text-emerald-500 shrink-0" />
                            <span>100% Client-Side JSON Parsing</span>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <Check class="h-4.5 w-4.5 text-emerald-500 shrink-0" />
                            <span>Local variables remain local & secure</span>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <Check class="h-4.5 w-4.5 text-emerald-500 shrink-0" />
                            <span>Cascade updates folders and requests</span>
                        </div>
                    </div>
                </div>

                <!-- Drag zone uploader -->
                <div class="lg:col-span-7">
                    <div class="bg-white dark:bg-[#121211] border border-[#19140012] dark:border-[#1f1f1e] rounded-xl p-6 shadow-xl flex flex-col gap-6 relative">
                        <div
                            @dragover.prevent="isDragActive = true"
                            @dragleave.prevent="isDragActive = false"
                            @drop.prevent="onFileDrop"
                            class="border-2 border-dashed rounded-xl p-8 flex flex-col items-center justify-center gap-3 text-center transition-all cursor-pointer bg-[#fcfcfa]/50 dark:bg-[#141413]/50"
                            :class="[
                                isDragActive ? 'border-green-500 bg-green-500/5' : 'border-[#19140015] dark:border-[#2b2b2a] hover:border-green-500/50'
                            ]"
                            @click="$refs.fileInput.click()"
                        >
                            <input
                                ref="fileInput"
                                type="file"
                                accept=".json"
                                class="hidden"
                                @change="onFileSelect"
                            />
                            <UploadCloud class="h-10 w-10 text-muted-foreground/45" />
                            <div class="flex flex-col gap-1">
                                <span class="text-xs font-semibold text-foreground">Drag and drop your Postman Collection JSON</span>
                                <span class="text-3xs text-muted-foreground">Supports V2.0 and V2.1 formatted collections</span>
                            </div>
                            <span class="text-3xs text-muted-foreground/60 my-1">OR</span>
                            <button
                                type="button"
                                @click.stop="loadSampleCollection"
                                class="px-3 py-1.5 bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-black rounded text-3xs font-semibold hover:bg-black dark:hover:bg-white transition-all shadow-sm"
                            >
                                Try Acme Payments Collection
                            </button>
                        </div>

                        <!-- Error display -->
                        <div v-if="parserError" class="p-3 bg-red-500/10 border border-red-500/20 text-red-500 rounded-lg text-2xs flex items-center gap-2 text-left">
                            <AlertCircle class="h-4.5 w-4.5 shrink-0" />
                            <span>{{ parserError }}</span>
                        </div>

                        <!-- Parsed tree display -->
                        <div v-if="parsedCollection" class="border border-[#19140015] dark:border-[#222] rounded-lg p-5 bg-[#fdfdfb] dark:bg-[#0e0e0e] text-left flex flex-col gap-4">
                            <div class="flex items-center justify-between border-b border-border/80 pb-3">
                                <div class="flex items-center gap-2">
                                    <FileJson class="h-5 w-5 text-green-500" />
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-foreground">{{ parsedCollection.name }}</span>
                                        <span class="text-4xs text-muted-foreground line-clamp-1 max-w-[280px]">{{ parsedCollection.description }}</span>
                                    </div>
                                </div>
                                <span class="text-3xs font-mono bg-green-500/10 text-green-500 px-2 py-0.5 rounded font-semibold uppercase tracking-wider">
                                    {{ parsedCollection.requestsCount }} Requests Extracted
                                </span>
                            </div>

                            <div class="flex flex-col gap-3 pl-1 max-h-[220px] overflow-y-auto">
                                <div v-for="(item, idx) in parsedCollection.items" :key="idx">
                                    <!-- Folder -->
                                    <div v-if="item.type === 'folder'" class="flex flex-col gap-1.5">
                                        <div class="flex items-center gap-1.5 text-2xs font-semibold text-foreground/80">
                                            <span class="text-amber-500 font-bold">📂</span>
                                            <span>{{ item.name }}</span>
                                        </div>
                                        <div class="pl-4 border-l border-[#19140010] dark:border-[#222] ml-1.5 flex flex-col gap-2 mt-1">
                                            <div v-for="(child, cIdx) in item.children" :key="cIdx" class="flex items-center justify-between text-3xs bg-black/5 dark:bg-white/5 p-1.5 rounded">
                                                <div class="flex items-center gap-2 font-mono">
                                                    <span class="font-bold shrink-0" :class="[child.method === 'GET' ? 'text-emerald-500' : child.method === 'POST' ? 'text-indigo-500' : 'text-amber-500']">{{ child.method }}</span>
                                                    <span class="text-[#777] line-clamp-1 max-w-[200px]">{{ child.name }}</span>
                                                </div>
                                                <span class="text-4xs font-mono text-muted-foreground/60 line-clamp-1">{{ child.url }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Direct Request -->
                                    <div v-else class="flex items-center justify-between text-3xs bg-black/5 dark:bg-white/5 p-2 rounded">
                                        <div class="flex items-center gap-2 font-mono">
                                            <span class="font-bold" :class="[item.method === 'GET' ? 'text-emerald-500' : item.method === 'POST' ? 'text-indigo-500' : 'text-red-500']">{{ item.method }}</span>
                                            <span class="text-[#777]">{{ item.name }}</span>
                                        </div>
                                        <span class="text-4xs font-mono text-muted-foreground/60">{{ item.url }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- DEFINITION BLOCK (GEO Optimized) -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 relative z-10 border-t border-[#1914000d] dark:border-[#1a1a19]">
            <div class="bg-[#fafaf9] dark:bg-[#121211] border border-border/60 rounded-xl p-8 max-w-4xl mx-auto text-center md:text-left flex flex-col md:flex-row gap-6 items-center shadow-sm">
                <div class="h-12 w-12 rounded-full bg-green-500/10 text-green-500 flex items-center justify-center shrink-0">
                    <Sparkles class="h-6 w-6" />
                </div>
                <div class="flex-1">
                    <h2 class="text-xl font-bold text-[#1b1b18] dark:text-white mb-2">What makes InstaRequest the ideal Postman Alternative?</h2>
                    <p class="text-sm text-muted-foreground leading-relaxed">
                        Unlike traditional desktop-bound tools, <strong>InstaRequest</strong> is built exclusively for the web. It eliminates local app bloat, memory-heavy Electron processes, and restrictive team limits, giving you an ultra-fast environment that scales to your entire engineering organization without vendor lock-in.
                    </p>
                </div>
            </div>
        </section>

        <!-- Detailed matrix comparison -->
        <section id="compare" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 relative z-10 border-t border-[#1914000d] dark:border-[#1a1a19] text-center">
            <div class="max-w-3xl mx-auto mb-16 flex flex-col gap-3">
                <span class="text-xs font-bold uppercase tracking-wider text-green-500 font-mono">DEEP MATRIX COMPARISON</span>
                <h2 class="text-3xl font-extrabold text-[#1b1b18] dark:text-white leading-tight">InstaRequest vs Postman</h2>
                <p class="text-xs sm:text-sm text-muted-foreground">See how InstaRequest stacks up across hardware performance, offline security, and pricing flexibility.</p>
            </div>

            <!-- Comparison Table -->
            <div class="max-w-4xl mx-auto overflow-hidden rounded-xl border border-border/60 shadow-xl bg-white dark:bg-[#121211]">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-[#fcfcfa] dark:bg-[#151514] border-b border-border/80 font-bold uppercase text-muted-foreground text-[10px]">
                            <th class="px-6 py-4">Capability</th>
                            <th class="px-6 py-4 text-green-500">InstaRequest</th>
                            <th class="px-6 py-4">Postman</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr>
                            <td class="px-6 py-4 font-semibold">Memory Bloat (RAM Usage)</td>
                            <td class="px-6 py-4 text-emerald-500 font-bold flex items-center gap-1.5">
                                <Check class="h-4.5 w-4.5 shrink-0" />
                                <span>~30MB (Web browser tab)</span>
                            </td>
                            <td class="px-6 py-4 text-muted-foreground flex items-center gap-1.5">
                                <X class="h-4 w-4 shrink-0 text-red-500" />
                                <span>800MB+ (Electron desktop app)</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 font-semibold">Zero-Install Web Access</td>
                            <td class="px-6 py-4 text-emerald-500 font-bold flex items-center gap-1.5">
                                <Check class="h-4.5 w-4.5 shrink-0" />
                                <span>Yes, instant browser access</span>
                            </td>
                            <td class="px-6 py-4 text-muted-foreground flex items-center gap-1.5">
                                <X class="h-4 w-4 shrink-0 text-red-500" />
                                <span>No, requires desktop install</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 font-semibold">Realtime Team Workspace</td>
                            <td class="px-6 py-4 text-emerald-500 font-bold flex items-center gap-1.5">
                                <Check class="h-4.5 w-4.5 shrink-0" />
                                <span>Yes, multiplayer sync</span>
                            </td>
                            <td class="px-6 py-4 text-muted-foreground flex items-center gap-1.5">
                                <X class="h-4 w-4 shrink-0 text-red-500" />
                                <span>Delayed save polling sync</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 font-semibold">Mandatory Account Requirement</td>
                            <td class="px-6 py-4 text-emerald-500 font-bold flex items-center gap-1.5">
                                <Check class="h-4.5 w-4.5 shrink-0" />
                                <span>No, immediate web access ready</span>
                            </td>
                            <td class="px-6 py-4 text-muted-foreground flex items-center gap-1.5">
                                <X class="h-4 w-4 shrink-0 text-red-500" />
                                <span>Yes, restricted scratchpad</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 font-semibold">Built-in API Uptime Monitor</td>
                            <td class="px-6 py-4 text-emerald-500 font-bold flex items-center gap-1.5">
                                <Check class="h-4.5 w-4.5 shrink-0" />
                                <span>Yes, integrated checks</span>
                            </td>
                            <td class="px-6 py-4 text-muted-foreground flex items-center gap-1.5">
                                <X class="h-4 w-4 shrink-0 text-red-500" />
                                <span>Cloud add-on only</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- FAQs for migrating users -->
        <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-20 relative z-10 border-t border-[#1914000d] dark:border-[#1a1a19]">
            <div class="text-center mb-16 flex flex-col gap-3">
                <span class="text-xs font-bold uppercase tracking-wider text-green-500 font-mono">FAQ</span>
                <h2 class="text-3xl font-extrabold text-[#1b1b18] dark:text-white leading-tight">Migration FAQ</h2>
                <p class="text-xs sm:text-sm text-muted-foreground">Answers to questions from developers migrating from Postman to InstaRequest.</p>
            </div>

            <div class="flex flex-col gap-4">
                <div class="border border-[#19140012] dark:border-[#1f1f1e] rounded-xl bg-white dark:bg-[#121211] overflow-hidden transition-all">
                    <button @click="toggleFaq(0)" class="w-full px-6 py-5 flex items-center justify-between font-bold text-sm text-[#1b1b18] dark:text-white hover:text-green-500 transition-colors text-left">
                        <span>Q1. Will my environment variables and credentials migrate?</span>
                        <ChevronDown v-if="activeFaqIndex !== 0" class="h-4 w-4 text-muted-foreground shrink-0" />
                        <ChevronUp v-else class="h-4 w-4 text-muted-foreground shrink-0" />
                    </button>
                    <div v-show="activeFaqIndex === 0" class="px-6 pb-5 text-xs text-muted-foreground leading-relaxed text-left">
                        Yes. When you drag and drop a collection along with environment presets, InstaRequest parses all key-value mappings perfectly. Environment values remain stored securely in your private workspace, ensuring 100% variable privacy.
                    </div>
                </div>

                <div class="border border-[#19140012] dark:border-[#1f1f1e] rounded-xl bg-white dark:bg-[#121211] overflow-hidden transition-all">
                    <button @click="toggleFaq(1)" class="w-full px-6 py-5 flex items-center justify-between font-bold text-sm text-[#1b1b18] dark:text-white hover:text-green-500 transition-colors text-left">
                        <span>Q2. Is InstaRequest web-based or a desktop app?</span>
                        <ChevronDown v-if="activeFaqIndex !== 1" class="h-4 w-4 text-muted-foreground shrink-0" />
                        <ChevronUp v-else class="h-4 w-4 text-muted-foreground shrink-0" />
                    </button>
                    <div v-show="activeFaqIndex === 1" class="px-6 pb-5 text-xs text-muted-foreground leading-relaxed text-left">
                        InstaRequest is 100% web-based. You don't need to download or install any heavy desktop applications. Everything runs smoothly in your browser with zero bloat and instant access from any device.
                    </div>
                </div>

                <div class="border border-[#19140012] dark:border-[#1f1f1e] rounded-xl bg-white dark:bg-[#121211] overflow-hidden transition-all">
                    <button @click="toggleFaq(2)" class="w-full px-6 py-5 flex items-center justify-between font-bold text-sm text-[#1b1b18] dark:text-white hover:text-green-500 transition-colors text-left">
                        <span>Q3. Can I export my InstaRequest collections back to Postman?</span>
                        <ChevronDown v-if="activeFaqIndex !== 2" class="h-4 w-4 text-muted-foreground shrink-0" />
                        <ChevronUp v-else class="h-4 w-4 text-muted-foreground shrink-0" />
                    </button>
                    <div v-show="activeFaqIndex === 2" class="px-6 pb-5 text-xs text-muted-foreground leading-relaxed text-left">
                        Yes. InstaRequest fully respects open API tooling standards. You can download and export all collections and schemas as standard Postman V2 formats or standard OpenAPI spec YAML sheets at any time.
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
