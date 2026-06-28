<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Users, CheckCircle2, Activity, Compass, Terminal, ShieldAlert
} from 'lucide-vue-next';
import { ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';

// Dynamic activity stream
const activityLogs = ref<string[]>([
    "Alex updated headers on 'Submit Final Checkout'",
    "Sarah executed collection checks on 'Cart Services' (100% Passed)",
    "Dave merged environmental variables into 'Production Preset'"
]);

// JSON-LD Schema
const schemaMarkup = {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "Collaborative API Testing Platform | InstaRequest",
    "description": "InstaRequest is a powerful API testing client. Collaborate with shared workspaces and synchronized collections.",
    "breadcrumb": {
        "@type": "BreadcrumbList",
        "itemListElement": [
            { "@type": "ListItem", "position": 1, "name": "Home", "item": "https://jackman.dev" },
            { "@type": "ListItem", "position": 2, "name": "API Collaboration", "item": "https://jackman.dev/api-collaboration" }
        ]
    }
};
</script>

<template>
    <MarketingLayout>
        <Head>
            <title>Team API Collaboration & Shared Testing Client | InstaRequest</title>
            <meta name="description" content="Collaborate on API request building with shared team workspaces and automated collections sync." />
            <component :is="'script'" type="application/ld+json" v-html="JSON.stringify(schemaMarkup)"></component>
        </Head>

        <!-- Breadcrumbs -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
            <nav class="flex text-3xs font-mono uppercase tracking-wider text-muted-foreground gap-2">
                <Link href="/" class="hover:text-foreground">Home</Link>
                <span>/</span>
                <span class="text-green-500 font-semibold">API Collaboration</span>
            </nav>
        </div>

        <!-- Hero Header -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-16 text-center relative z-10 flex flex-col items-center gap-6">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-green-500/30 text-green-500 text-[11px] font-semibold bg-green-500/5">
                <Users class="h-3.5 w-3.5" />
                <span>Team Collaboration Sync</span>
            </div>
            <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-[#1b1b18] dark:text-white max-w-4xl leading-tight">
                The Powerful <span class="bg-gradient-to-r from-green-500 to-lime-500 bg-clip-text text-transparent">Collaborative</span> API Tool for Teams
            </h1>
            <p class="text-base sm:text-lg text-muted-foreground leading-relaxed max-w-2xl">
                Stop syncing manual exports or waiting on stale branches. Share collections across your team and sync variables instantly.
            </p>
        </section>

        <!-- Interactive Collaboration Sandbox -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-stretch">
                <!-- Instruction Panel -->
                <div class="lg:col-span-5 flex flex-col justify-between gap-8 text-left">
                    <div class="flex flex-col gap-6">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-green-500/10 rounded-lg text-green-500">
                                <CheckCircle2 class="h-5 w-5" />
                            </div>
                            <h2 class="text-xl font-bold">Shared Workspace Management</h2>
                        </div>
                        <p class="text-xs text-muted-foreground leading-relaxed">
                            Organize requests into team collections. Keep environments and endpoints up to date across your entire development organization.
                        </p>

                        <!-- Activity Stream -->
                        <div class="flex flex-col gap-3">
                            <span class="text-3xs uppercase font-bold text-muted-foreground tracking-wider font-mono">Sync Activity Log</span>
                            <div class="flex flex-col gap-2.5">
                                <div
                                    v-for="(log, idx) in activityLogs"
                                    :key="idx"
                                    class="flex items-center gap-2 text-3xs font-mono bg-[#fafaf9] dark:bg-[#121211] border border-border/40 p-2.5 rounded-lg text-foreground/80"
                                    :class="[idx === 0 ? 'border-green-500/40 text-green-500 bg-green-500/[0.02]' : '']"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full shrink-0" :class="[idx === 0 ? 'bg-green-500 animate-pulse' : 'bg-muted-foreground']"></span>
                                    <span class="truncate">{{ log }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CTA Block -->
                    <div class="p-4 bg-[#fcfcfa] dark:bg-[#141413] border border-border/60 rounded-xl flex items-center justify-between gap-4">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-2xs font-bold">Unify your backend teams</span>
                            <span class="text-4xs text-muted-foreground">Unlimited team members.</span>
                        </div>
                        <Link href="/register" class="px-3 py-1.5 bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-black rounded text-3xs font-semibold hover:bg-black dark:hover:bg-white transition-all font-mono shadow-sm">
                            Try Collaborative Workspaces
                        </Link>
                    </div>
                </div>

                <!-- Collaborative Workspace GUI Mockup -->
                <div class="lg:col-span-7 flex flex-col">
                    <div class="bg-white dark:bg-[#121211] border border-border/80 rounded-xl shadow-2xl flex-1 flex flex-col items-stretch text-left relative min-h-[460px]">
                        
                        <!-- Top header of mockup -->
                        <div class="flex items-center justify-between px-5 py-3.5 border-b border-border/60 bg-[#fafaf9] dark:bg-[#151514] rounded-t-xl">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-3xs font-bold bg-green-500/10 text-green-500 px-2 py-0.5 rounded tracking-wider uppercase">Team API Workspace</span>
                                <span class="text-4xs text-muted-foreground">Shared Collections</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                <span class="text-4xs font-mono font-semibold uppercase text-emerald-500">SYNCED</span>
                            </div>
                        </div>

                        <!-- Main canvas workspace panel -->
                        <div class="flex-1 p-5 flex flex-col gap-5 relative bg-[#fcfcfa]/50 dark:bg-[#131312]/30">
                            <!-- Mock request box inside canvas -->
                            <div class="border border-border/80 rounded-lg p-4 bg-white dark:bg-[#0c0c0b] flex flex-col gap-3 relative z-0">
                                <div class="flex items-center gap-2 font-mono text-2xs">
                                    <span class="font-bold text-indigo-500">POST</span>
                                    <span class="text-foreground font-semibold">/v1/checkout</span>
                                </div>
                                
                                <div class="grid grid-cols-12 gap-3 border-t border-border/40 pt-3">
                                    <div class="col-span-5 flex flex-col gap-1">
                                        <span class="text-[9px] font-mono text-muted-foreground uppercase">Headers Key</span>
                                        <div class="px-2 py-1 bg-black/[0.03] dark:bg-white/[0.03] border border-border/40 rounded text-3xs font-mono">Authorization</div>
                                        <div class="px-2 py-1 bg-black/[0.03] dark:bg-white/[0.03] border border-border/40 rounded text-3xs font-mono">Content-Type</div>
                                    </div>
                                    <div class="col-span-7 flex flex-col gap-1">
                                        <span class="text-[9px] font-mono text-muted-foreground uppercase">Headers Value</span>
                                        <div class="px-2 py-1 bg-black/[0.03] dark:bg-white/[0.03] border border-border/40 rounded text-3xs font-mono text-amber-500 font-bold">Bearer {{apiKey}}</div>
                                        <div class="px-2 py-1 bg-black/[0.03] dark:bg-white/[0.03] border border-border/40 rounded text-3xs font-mono">application/json</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Product Value Prop section -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 relative z-10 border-t border-border/40">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Col 1 -->
                <div class="bg-white dark:bg-[#121211] border border-border/60 rounded-xl p-6 flex flex-col gap-4 text-left shadow-sm">
                    <div class="h-10 w-10 bg-green-500/10 rounded-lg flex items-center justify-center text-green-500">
                        <Compass class="h-5 w-5" />
                    </div>
                    <h3 class="text-sm font-bold">Shared Environmental Presets</h3>
                    <p class="text-3xs sm:text-2xs text-muted-foreground leading-relaxed">
                        Keep environment configurations synchronized across teams without exposing sensitive API keys or local variables.
                    </p>
                </div>

                <!-- Col 2 -->
                <div class="bg-white dark:bg-[#121211] border border-border/60 rounded-xl p-6 flex flex-col gap-4 text-left shadow-sm">
                    <div class="h-10 w-10 bg-green-500/10 rounded-lg flex items-center justify-center text-green-500">
                        <CheckCircle2 class="h-5 w-5" />
                    </div>
                    <h3 class="text-sm font-bold">Standardized Request Specs</h3>
                    <p class="text-3xs sm:text-2xs text-muted-foreground leading-relaxed">
                        Ensure everyone tests against the exact same headers, query parameters, and payload schemas across development environments.
                    </p>
                </div>

                <!-- Col 3 -->
                <div class="bg-white dark:bg-[#121211] border border-border/60 rounded-xl p-6 flex flex-col gap-4 text-left shadow-sm">
                    <div class="h-10 w-10 bg-green-500/10 rounded-lg flex items-center justify-center text-green-500">
                        <Users class="h-5 w-5" />
                    </div>
                    <h3 class="text-sm font-bold">Conflict Resolution Engine</h3>
                    <p class="text-3xs sm:text-2xs text-muted-foreground leading-relaxed">
                        If two developers modify parameters concurrently, InstaRequest transparently flags diff details rather than quietly overriding modifications.
                    </p>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
