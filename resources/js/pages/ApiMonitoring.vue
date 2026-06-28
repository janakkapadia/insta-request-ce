<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity, Shield, Bell, Check, Play, RefreshCw,
    MapPin, AlertCircle, Clock, CheckCircle2, Sliders, Cpu
} from 'lucide-vue-next';
import { ref, onMounted, onUnmounted } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';

// Uptime Assertions Mock State
const assertions = ref([
    { id: 1, name: 'Status Code is 200', active: true, passed: true },
    { id: 2, name: 'Response Latency < 250ms', active: true, passed: true },
    { id: 3, name: 'JSON Body contains "success"', active: true, passed: true },
    { id: 4, name: 'Header Content-Type is application/json', active: false, passed: true }
]);

// Interactive Region Simulation
const selectedRegion = ref('us-east');
const regions = ref([
    { id: 'us-east', name: 'N. Virginia (US-East)', ping: 42, status: 'Healthy', flag: '🇺🇸' },
    { id: 'eu-central', name: 'Frankfurt (EU-Central)', ping: 118, status: 'Healthy', flag: '🇩🇪' },
    { id: 'ap-southeast', name: 'Singapore (AP-Southeast)', ping: 224, status: 'Healthy', flag: '🇸🇬' }
]);

// Latency Chart simulation
const pingLogs = ref<number[]>([42, 45, 41, 48, 43, 40, 42, 45, 41, 44, 42, 46, 43, 40, 42]);
const isSimulating = ref(false);
const mockUrl = ref('https://api.jackman.dev/v1/health');

const toggleAssertion = (index: number) => {
    assertions.value[index].active = !assertions.value[index].active;
    simulatePing();
};

const runSimulation = () => {
    if (isSimulating.value) {
return;
}

    isSimulating.value = true;
    
    // Simulate latency spikes and checks
    let duration = 0;
    const interval = setInterval(() => {
        const basePing = regions.value.find(r => r.id === selectedRegion.value)?.ping || 50;
        const randomness = Math.floor(Math.random() * 15) - 7;
        const newPing = Math.max(10, basePing + randomness);
        
        pingLogs.value.shift();
        pingLogs.value.push(newPing);
        duration += 100;
        
        if (duration >= 1000) {
            clearInterval(interval);
            isSimulating.value = false;
            
            // Check assertion metrics
            assertions.value.forEach(ast => {
                if (ast.id === 2) {
                    // Latency threshold check
                    ast.passed = newPing < 250;
                } else {
                    ast.passed = true;
                }
            });
        }
    }, 100);
};

const simulatePing = () => {
    runSimulation();
};

// Periodic background sparkline tick to show real-time dynamic behavior
let liveTicker: any = null;
onMounted(() => {
    liveTicker = setInterval(() => {
        if (!isSimulating.value) {
            const basePing = regions.value.find(r => r.id === selectedRegion.value)?.ping || 50;
            const randomness = Math.floor(Math.random() * 8) - 4;
            const newPing = Math.max(10, basePing + randomness);
            pingLogs.value.shift();
            pingLogs.value.push(newPing);
        }
    }, 3000);
});

onUnmounted(() => {
    if (liveTicker) {
clearInterval(liveTicker);
}
});

// JSON-LD SEO Schema
const schemaMarkup = {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "API Uptime Monitoring Platform | InstaRequest",
    "description": "InstaRequest delivers state-of-the-art API monitoring with global multi-region latency logging, instant threshold alerting, and validation assertions.",
    "breadcrumb": {
        "@type": "BreadcrumbList",
        "itemListElement": [
            { "@type": "ListItem", "position": 1, "name": "Home", "item": "https://jackman.dev" },
            { "@type": "ListItem", "position": 2, "name": "API Uptime Monitoring", "item": "https://jackman.dev/api-monitoring" }
        ]
    }
};
</script>

<template>
    <MarketingLayout>
        <Head>
            <title>Global API Uptime Monitoring Platform | InstaRequest</title>
            <meta name="description" content="Automate API uptime monitoring with multi-region latency checks, dynamic test assertions, instant integration-grade alerts, and unified team dashboard dashboards." />
            <component :is="'script'" type="application/ld+json" v-html="JSON.stringify(schemaMarkup)"></component>
        </Head>

        <!-- Breadcrumbs -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
            <nav class="flex text-3xs font-mono uppercase tracking-wider text-muted-foreground gap-2">
                <Link href="/" class="hover:text-foreground">Home</Link>
                <span>/</span>
                <span class="text-green-500 font-semibold">API Uptime Monitoring</span>
            </nav>
        </div>

        <!-- Hero Header -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-16 text-center relative z-10 flex flex-col items-center gap-6">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-green-500/30 text-green-500 text-[11px] font-semibold bg-green-500/5">
                <Activity class="h-3.5 w-3.5" />
                <span>Multi-Region Latency Tracking</span>
            </div>
            <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-[#1b1b18] dark:text-white max-w-4xl leading-tight">
                Continuous <span class="bg-gradient-to-r from-green-500 to-lime-500 bg-clip-text text-transparent">API Monitoring</span> with Global Assurance
            </h1>
            <p class="text-base sm:text-lg text-muted-foreground leading-relaxed max-w-2xl">
                Know when your endpoints degrade before your users do. Trigger microsecond assertions globally across top cloud centers with instant alerts down to Slack, Discord, and PagerDuty.
            </p>
        </section>

        <!-- Interactive Playground -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-stretch">
                <!-- Sandbox Control Center -->
                <div class="lg:col-span-5 flex flex-col justify-between gap-8 text-left">
                    <div class="flex flex-col gap-6">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-green-500/10 rounded-lg text-green-500">
                                <Sliders class="h-5 w-5" />
                            </div>
                            <h2 class="text-xl font-bold">Simulate Alert Triggers</h2>
                        </div>
                        <p class="text-xs text-muted-foreground leading-relaxed">
                            Select check locations, toggle assertion guidelines, and execute live tests. Watch how assertions automatically fail if thresholds are violated or responses lag.
                        </p>

                        <!-- Region Selector -->
                        <div class="flex flex-col gap-3">
                            <span class="text-3xs uppercase font-bold text-muted-foreground tracking-wider font-mono">Location Checkpoints</span>
                            <div class="flex flex-col gap-2">
                                <button
                                    v-for="region in regions"
                                    :key="region.id"
                                    @click="selectedRegion = region.id; simulatePing()"
                                    class="w-full flex items-center justify-between p-3 rounded-lg border text-xs font-semibold transition-all text-left"
                                    :class="[
                                        selectedRegion === region.id
                                            ? 'border-green-500 bg-green-500/5 text-[#1b1b18] dark:text-white'
                                            : 'border-[#19140012] dark:border-[#222] bg-[#fdfdfb] dark:bg-[#121211] hover:border-green-500/30'
                                    ]"
                                >
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm shrink-0">{{ region.flag }}</span>
                                        <span>{{ region.name }}</span>
                                    </div>
                                    <div class="flex items-center gap-3 font-mono text-3xs">
                                        <span class="text-muted-foreground">{{ region.ping }}ms avg</span>
                                        <span class="h-2 w-2 rounded-full bg-emerald-500 shadow-sm shadow-emerald-500"></span>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Assertions Toggle Grid -->
                        <div class="flex flex-col gap-3 mt-2">
                            <span class="text-3xs uppercase font-bold text-muted-foreground tracking-wider font-mono">Validation Guidelines</span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <button
                                    v-for="(ast, index) in assertions"
                                    :key="ast.id"
                                    @click="toggleAssertion(index)"
                                    class="flex items-center gap-2 p-2.5 rounded-md border text-3xs font-semibold text-left transition-all"
                                    :class="[
                                        ast.active
                                            ? 'border-emerald-500/40 bg-emerald-500/5 text-[#1b1b18] dark:text-white'
                                            : 'border-[#19140010] dark:border-[#1a1a19] text-muted-foreground opacity-60'
                                    ]"
                                >
                                    <span class="h-3 w-3 rounded-full flex items-center justify-center border border-border shrink-0" :class="[ast.active ? 'bg-emerald-500 border-emerald-500 text-white' : '']">
                                        <Check v-if="ast.active" class="h-2 w-2" />
                                    </span>
                                    <span class="truncate">{{ ast.name }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- CTA Anchor inside interactive zone -->
                    <div class="p-4 bg-[#fcfcfa] dark:bg-[#141413] border border-border/60 rounded-xl flex items-center justify-between gap-4">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-2xs font-bold">Real-time alerts ready</span>
                            <span class="text-4xs text-muted-foreground">Get notified via Webhooks instantly.</span>
                        </div>
                        <Link href="/register" class="px-3 py-1.5 bg-green-500 text-white rounded text-3xs font-semibold hover:bg-green-600 transition-all font-mono">
                            Deploy Now
                        </Link>
                    </div>
                </div>

                <!-- Interactive Dashboard Panel -->
                <div class="lg:col-span-7 flex flex-col">
                    <div class="bg-white dark:bg-[#121211] border border-border/80 rounded-xl p-5 shadow-2xl flex flex-col gap-6 flex-1 text-left relative overflow-hidden">
                        
                        <!-- Top status bar -->
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-border/60 pb-4">
                            <div class="flex items-center gap-3">
                                <div class="h-9 w-9 bg-green-500/10 rounded-lg flex items-center justify-center text-green-500">
                                    <Activity class="h-4.5 w-4.5" />
                                </div>
                                <div class="flex flex-col">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-foreground">API Latency Live Feed</span>
                                        <span class="h-2 w-2 rounded-full bg-emerald-500 animate-ping"></span>
                                    </div>
                                    <span class="text-4xs font-mono text-muted-foreground">Endpoint: <span class="text-green-500">{{ mockUrl }}</span></span>
                                </div>
                            </div>
                            
                            <button
                                @click="runSimulation"
                                :disabled="isSimulating"
                                class="px-3.5 py-1.5 bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-black rounded text-3xs font-semibold flex items-center gap-1.5 hover:bg-black dark:hover:bg-white transition-all shadow-sm shrink-0 disabled:opacity-50"
                            >
                                <Play v-if="!isSimulating" class="h-3 w-3 shrink-0" />
                                <RefreshCw v-else class="h-3 w-3 animate-spin shrink-0" />
                                <span>{{ isSimulating ? 'Testing...' : 'Trigger Check' }}</span>
                            </button>
                        </div>

                        <!-- Simulated Latency Graph -->
                        <div class="flex flex-col gap-3">
                            <div class="flex justify-between items-center text-3xs text-muted-foreground font-mono">
                                <span>Uptime: <strong class="text-emerald-500 font-semibold">99.998%</strong></span>
                                <span>Latency limit: 250ms</span>
                            </div>
                            
                            <!-- Sparkline canvas simulated using flex columns -->
                            <div class="h-[160px] bg-[#fafaf9] dark:bg-[#0c0c0b] rounded-lg border border-[#1914000b] dark:border-[#222] p-4 flex items-end justify-between gap-2.5">
                                <div
                                    v-for="(val, idx) in pingLogs"
                                    :key="idx"
                                    class="flex-1 flex flex-col justify-end items-center h-full group relative"
                                >
                                    <!-- Tooltip -->
                                    <span class="absolute bottom-full mb-2 bg-[#1b1b18] text-white text-[9px] px-2 py-0.5 rounded opacity-0 group-hover:opacity-100 transition-opacity font-mono pointer-events-none z-20">
                                        {{ val }}ms
                                    </span>
                                    
                                    <!-- Visual bar column -->
                                    <div
                                        class="w-full rounded-t-sm transition-all duration-300 relative overflow-hidden"
                                        :style="{ height: `${Math.min(100, (val / 300) * 100)}%` }"
                                        :class="[
                                            val >= 250
                                                ? 'bg-rose-500/70 border-t border-rose-500'
                                                : val >= 150
                                                ? 'bg-amber-500/70 border-t border-amber-500'
                                                : 'bg-emerald-500/60 border-t border-emerald-500'
                                        ]"
                                    ></div>
                                    <span class="text-[8px] font-mono text-muted-foreground/50 mt-1 uppercase scale-90">{{ idx + 1 }}m</span>
                                </div>
                            </div>
                        </div>

                        <!-- Live assertions result list -->
                        <div class="flex flex-col gap-3">
                            <span class="text-3xs uppercase font-bold text-muted-foreground tracking-wider font-mono">Assertion Pass Checkmarks</span>
                            <div class="flex flex-col gap-2">
                                <div
                                    v-for="ast in assertions.filter(a => a.active)"
                                    :key="ast.id"
                                    class="flex items-center justify-between p-2.5 rounded border text-3xs font-mono"
                                    :class="[
                                        ast.passed 
                                            ? 'bg-emerald-500/[0.04] border-emerald-500/20 text-emerald-600 dark:text-emerald-400' 
                                            : 'bg-rose-500/[0.04] border-rose-500/20 text-rose-600 dark:text-rose-400'
                                    ]"
                                >
                                    <div class="flex items-center gap-2">
                                        <CheckCircle2 v-if="ast.passed" class="h-4 w-4 shrink-0 text-emerald-500" />
                                        <AlertCircle v-else class="h-4 w-4 shrink-0 text-rose-500" />
                                        <span>ASSERT: {{ ast.name }}</span>
                                    </div>
                                    <span class="font-bold uppercase tracking-wider">{{ ast.passed ? 'PASS' : 'FAIL' }}</span>
                                </div>
                                <div v-if="assertions.filter(a => a.active).length === 0" class="py-4 text-center text-3xs text-muted-foreground italic border border-dashed border-border rounded">
                                    No assertions toggled. Click validation checkboxes to activate real-time checks.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Feature Details Section -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 relative z-10 border-t border-border/40">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Card 1 -->
                <div class="bg-white dark:bg-[#121211] border border-border/60 rounded-xl p-6 flex flex-col gap-4 text-left shadow-sm">
                    <div class="h-10 w-10 bg-green-500/10 rounded-lg flex items-center justify-center text-green-500">
                        <MapPin class="h-5 w-5" />
                    </div>
                    <h3 class="text-sm font-bold">Multi-region Assertions</h3>
                    <p class="text-3xs sm:text-2xs text-muted-foreground leading-relaxed">
                        Verify uptime and SLA latencies across key hosting hubs in the Americas, Europe, and Asia. Get pinpoint granularity on network bottlenecks.
                    </p>
                </div>

                <!-- Card 2 -->
                <div class="bg-white dark:bg-[#121211] border border-border/60 rounded-xl p-6 flex flex-col gap-4 text-left shadow-sm">
                    <div class="h-10 w-10 bg-green-500/10 rounded-lg flex items-center justify-center text-green-500">
                        <Clock class="h-5 w-5" />
                    </div>
                    <h3 class="text-sm font-bold">Microsecond Frequencies</h3>
                    <p class="text-3xs sm:text-2xs text-muted-foreground leading-relaxed">
                        Schedule automated API calls from every 30 seconds to daily. Configure customized threshold alerts so your team gets notified immediately.
                    </p>
                </div>

                <!-- Card 3 -->
                <div class="bg-white dark:bg-[#121211] border border-border/60 rounded-xl p-6 flex flex-col gap-4 text-left shadow-sm">
                    <div class="h-10 w-10 bg-green-500/10 rounded-lg flex items-center justify-center text-green-500">
                        <Shield class="h-5 w-5" />
                    </div>
                    <h3 class="text-sm font-bold">Integrated Security Logs</h3>
                    <p class="text-3xs sm:text-2xs text-muted-foreground leading-relaxed">
                        Keep an immutable historical record of response headers and SSL details. Resolve downtime incidents with detailed diagnostics traces in real time.
                    </p>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
