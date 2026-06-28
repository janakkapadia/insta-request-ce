<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Layers, Users, Shield, Check, History, Plus,
    Lock, Eye, Edit3, Award, Clock
} from 'lucide-vue-next';
import { ref } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';

// Workspace selection mock
const activeWorkspaceId = ref('payments');
const workspaces = ref([
    { id: 'payments', name: 'Acme Payments Core API', requests: 42, membersCount: 4 },
    { id: 'auth', name: 'Gateway Identity & Auth', requests: 15, membersCount: 2 },
    { id: 'analytics', name: 'Metrics & Data Warehouse', requests: 28, membersCount: 3 }
]);

// Interactive Permission Roles Mock
const selectedUserIdx = ref(0);
const teamMembers = ref([
    { name: 'Alex Kapadia', email: 'alex@acme.com', role: 'Admin', icon: '🦊', desc: 'Full write access to global production environment configurations.' },
    { name: 'Sarah Miller', email: 'sarah@acme.com', role: 'Editor', icon: '🐹', desc: 'Can edit collections and request parameters, but environment variables remain masked.' },
    { name: 'Dave Chen', email: 'dave@acme.com', role: 'Viewer', icon: '🐻', desc: 'Read-only access to run assertions and checkout payloads without modification.' }
]);

const syncLogs = ref([
    { actor: 'Alex Kapadia', action: 'Created Environment [Staging Presets]', target: 'Acme Payments Core API', time: '5m ago' },
    { actor: 'Sarah Miller', action: 'Updated Endpoint POST /checkout', target: 'Acme Payments Core API', time: '12m ago' },
    { actor: 'Dave Chen', action: 'Executed Collections Suite', target: 'Metrics & Data Warehouse', time: '1h ago' }
]);

const handleRoleChange = (role: 'Admin' | 'Editor' | 'Viewer') => {
    teamMembers.value[selectedUserIdx.value].role = role;

    if (role === 'Admin') {
        teamMembers.value[selectedUserIdx.value].desc = 'Full write access to global production environment configurations.';
    } else if (role === 'Editor') {
        teamMembers.value[selectedUserIdx.value].desc = 'Can edit collections and request parameters, but environment variables remain masked.';
    } else {
        teamMembers.value[selectedUserIdx.value].desc = 'Read-only access to run assertions and checkout payloads without modification.';
    }
    
    // Add dynamic audit trace log
    syncLogs.value.unshift({
        actor: 'You (Manager)',
        action: `Updated role of ${teamMembers.value[selectedUserIdx.value].name} to [${role}]`,
        target: workspaces.value.find(w => w.id === activeWorkspaceId.value)?.name || 'Acme Payments Core API',
        time: 'Just now'
    });

    if (syncLogs.value.length > 4) {
syncLogs.value.pop();
}
};

// JSON-LD Schema
const schemaMarkup = {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "Team API Workspace & Management | InstaRequest",
    "description": "InstaRequest lets engineering managers coordinate API collections with robust workspace permissions control, environment scoping, and immutable activity auditing.",
    "breadcrumb": {
        "@type": "BreadcrumbList",
        "itemListElement": [
            { "@type": "ListItem", "position": 1, "name": "Home", "item": "https://jackman.dev" },
            { "@type": "ListItem", "position": 2, "name": "Realtime API Workspace", "item": "https://jackman.dev/realtime-api-workspace" }
        ]
    }
};
</script>

<template>
    <MarketingLayout>
        <Head>
            <title>Collaborative Team API Workspaces & Permission Auditor | InstaRequest</title>
            <meta name="description" content="Manage developer API collections securely. Scoped environment presets control, multi-tiered team role access (Admin, Editor, Viewer), and immutable change sync tracking." />
            <component :is="'script'" type="application/ld+json" v-html="JSON.stringify(schemaMarkup)"></component>
        </Head>

        <!-- Breadcrumbs -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
            <nav class="flex text-3xs font-mono uppercase tracking-wider text-muted-foreground gap-2">
                <Link href="/" class="hover:text-foreground">Home</Link>
                <span>/</span>
                <span class="text-green-500 font-semibold">Realtime API Workspace</span>
            </nav>
        </div>

        <!-- Hero Header -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-16 text-center relative z-10 flex flex-col items-center gap-6">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-green-500/30 text-green-500 text-[11px] font-semibold bg-green-500/5">
                <Layers class="h-3.5 w-3.5" />
                <span>Enterprise Permissions Control</span>
            </div>
            <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-[#1b1b18] dark:text-white max-w-4xl leading-tight">
                Secure Workspaces Built for <span class="bg-gradient-to-r from-green-500 to-lime-500 bg-clip-text text-transparent">Engineering Teams</span>
            </h1>
            <p class="text-base sm:text-lg text-muted-foreground leading-relaxed max-w-2xl">
                Organize API endpoints with absolute boundary isolation. Grant granular role-based controls, keep secure variables hidden from external contributors, and view full change logs seamlessly.
            </p>
        </section>

        <!-- Interactive Playground -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-stretch">
                <!-- Sandbox Controller Settings -->
                <div class="lg:col-span-5 flex flex-col justify-between gap-8 text-left">
                    <div class="flex flex-col gap-6">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-green-500/10 rounded-lg text-green-500">
                                <Shield class="h-5 w-5" />
                            </div>
                            <h2 class="text-xl font-bold">Role Access Sandbox</h2>
                        </div>
                        <p class="text-xs text-muted-foreground leading-relaxed">
                            Select a teammate in the panel and modify their role credentials. Toggling roles immediately updates security descriptors and posts a change log directly to our audit trail!
                        </p>

                        <!-- Teammate Selection Panel -->
                        <div class="flex flex-col gap-3">
                            <span class="text-3xs uppercase font-bold text-muted-foreground tracking-wider font-mono">Teammates Roster</span>
                            <div class="flex flex-col gap-2">
                                <button
                                    v-for="(member, idx) in teamMembers"
                                    :key="idx"
                                    @click="selectedUserIdx = idx"
                                    class="w-full flex items-center justify-between p-3 rounded-lg border text-xs font-semibold transition-all text-left"
                                    :class="[
                                        selectedUserIdx === idx
                                            ? 'border-green-500 bg-green-500/5 text-[#1b1b18] dark:text-white'
                                            : 'border-[#19140012] dark:border-[#222] bg-[#fdfdfb] dark:bg-[#121211] hover:border-green-500/30'
                                    ]"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <span class="text-sm shrink-0 bg-black/5 dark:bg-white/5 h-6 w-6 rounded-full flex items-center justify-center">{{ member.icon }}</span>
                                        <div class="flex flex-col">
                                            <span>{{ member.name }}</span>
                                            <span class="text-[10px] text-muted-foreground font-normal">{{ member.email }}</span>
                                        </div>
                                    </div>
                                    <span class="text-[9px] font-mono font-bold uppercase px-2 py-0.5 rounded" :class="[member.role === 'Admin' ? 'bg-rose-500/10 text-rose-500' : member.role === 'Editor' ? 'bg-indigo-500/10 text-indigo-500' : 'bg-[#1b1b18]/10 dark:bg-white/10 text-muted-foreground']">
                                        {{ member.role }}
                                    </span>
                                </button>
                            </div>
                        </div>

                        <!-- Role Modifier Selector -->
                        <div class="flex flex-col gap-3 mt-2">
                            <span class="text-3xs uppercase font-bold text-muted-foreground tracking-wider font-mono">Tweak Privilege Levels</span>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    v-for="r in (['Admin', 'Editor', 'Viewer'] as const)"
                                    :key="r"
                                    @click="handleRoleChange(r)"
                                    class="py-2 rounded border text-3xs font-mono font-bold text-center transition-all flex items-center justify-center gap-1"
                                    :class="[
                                        teamMembers[selectedUserIdx].role === r
                                            ? 'border-green-500 bg-green-500/10 text-green-500'
                                            : 'border-[#19140010] dark:border-[#1a1a19] text-muted-foreground hover:border-green-500/30'
                                    ]"
                                >
                                    <Lock v-if="r === 'Admin'" class="h-2.5 w-2.5 shrink-0" />
                                    <Edit3 v-else-if="r === 'Editor'" class="h-2.5 w-2.5 shrink-0" />
                                    <Eye v-else class="h-2.5 w-2.5 shrink-0" />
                                    <span>{{ r }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Sandbox boundary verification block -->
                    <div class="p-4 bg-[#fcfcfa] dark:bg-[#141413] border border-border/60 rounded-xl flex items-center justify-between gap-4">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-2xs font-bold">Scoped environments active</span>
                            <span class="text-4xs text-muted-foreground">Keep variables securely isolated.</span>
                        </div>
                        <Link href="/register" class="px-3 py-1.5 bg-green-500 text-white rounded text-3xs font-semibold hover:bg-green-600 transition-all font-mono">
                            Get Started
                        </Link>
                    </div>
                </div>

                <!-- Interactive Workspaces Manager UI Mockup -->
                <div class="lg:col-span-7 flex flex-col">
                    <div class="bg-white dark:bg-[#121211] border border-border/80 rounded-xl shadow-2xl flex-1 flex flex-col items-stretch text-left relative min-h-[460px]">
                        
                        <!-- Top horizontal split of workspaces selection -->
                        <div class="flex items-center justify-between px-5 py-3.5 border-b border-border/60 bg-[#fafaf9] dark:bg-[#151514] rounded-t-xl shrink-0">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-3xs font-bold bg-green-500/10 text-green-500 px-2 py-0.5 rounded tracking-wider uppercase">WORKSPACES AUDITOR</span>
                                <span class="text-4xs text-muted-foreground">Active Organization: Acme Corp</span>
                            </div>
                            <span class="text-4xs font-mono font-semibold uppercase text-emerald-500 flex items-center gap-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                Synchronized
                            </span>
                        </div>

                        <!-- Workspace panel canvas split -->
                        <div class="flex-1 p-5 flex flex-col gap-5 relative bg-[#fcfcfa]/50 dark:bg-[#131312]/30">
                            
                            <!-- Workspaces selector grid row -->
                            <div class="flex flex-col gap-2.5">
                                <span class="text-3xs uppercase font-bold text-muted-foreground tracking-wider font-mono">Switch Active Workspace</span>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                    <button
                                        v-for="ws in workspaces"
                                        :key="ws.id"
                                        @click="activeWorkspaceId = ws.id"
                                        class="p-3 border rounded-lg text-left transition-all flex flex-col gap-1 text-2xs font-semibold"
                                        :class="[
                                            activeWorkspaceId === ws.id
                                                ? 'border-green-500/50 bg-white dark:bg-[#0c0c0b] text-[#1b1b18] dark:text-white shadow-sm'
                                                : 'border-border/40 text-muted-foreground hover:border-green-500/30'
                                        ]"
                                    >
                                        <div class="flex items-center gap-1.5">
                                            <Layers class="h-3.5 w-3.5 text-green-500 shrink-0" />
                                            <span class="truncate font-bold">{{ ws.name }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-[9px] text-muted-foreground font-mono font-normal">
                                            <span>{{ ws.requests }} routes</span>
                                            <span>•</span>
                                            <span>{{ ws.membersCount }} members</span>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <!-- Mock active user permissions summary -->
                            <div class="border border-border/80 rounded-lg p-4 bg-white dark:bg-[#0c0c0b] flex flex-col gap-2.5">
                                <div class="flex items-center justify-between text-3xs font-mono border-b border-border/40 pb-2">
                                    <span class="font-bold text-foreground">Active privileges for: <strong class="text-green-500 uppercase">{{ teamMembers[selectedUserIdx].name }}</strong></span>
                                    <span class="font-bold uppercase tracking-wider text-rose-500 font-mono">SCOPED ACCESS</span>
                                </div>
                                <p class="text-3xs text-muted-foreground leading-relaxed italic">
                                    "{{ teamMembers[selectedUserIdx].desc }}"
                                </p>
                            </div>

                            <!-- Audit sync tracking log timeline -->
                            <div class="border border-border/80 rounded-lg bg-white dark:bg-[#0c0c0b] p-4 flex flex-col gap-3.5">
                                <div class="flex items-center gap-1.5 text-3xs font-mono uppercase tracking-wider font-bold text-muted-foreground">
                                    <History class="h-4 w-4 text-green-500 shrink-0" />
                                    <span>Immutable Activity Audit Trail</span>
                                </div>
                                
                                <div class="flex flex-col gap-2.5 max-h-[140px] overflow-y-auto pr-1">
                                    <div
                                        v-for="(log, idx) in syncLogs"
                                        :key="idx"
                                        class="flex items-center justify-between text-3xs font-mono bg-black/[0.01] dark:bg-white/[0.01] border border-border/40 p-2 rounded"
                                        :class="[idx === 0 ? 'border-green-500/30 bg-green-500/[0.02]' : '']"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span class="h-2 w-2 rounded-full" :class="[idx === 0 ? 'bg-green-500 animate-pulse' : 'bg-muted-foreground/50']"></span>
                                            <span class="font-bold text-foreground">{{ log.actor }}</span>
                                            <span class="text-muted-foreground truncate max-w-[240px]">{{ log.action }}</span>
                                        </div>
                                        <span class="text-[9px] text-muted-foreground/60 shrink-0">{{ log.time }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Product Value Details -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 relative z-10 border-t border-border/40">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Grid Col 1 -->
                <div class="bg-white dark:bg-[#121211] border border-border/60 rounded-xl p-6 flex flex-col gap-4 text-left shadow-sm">
                    <div class="h-10 w-10 bg-green-500/10 rounded-lg flex items-center justify-center text-green-500">
                        <History class="h-5 w-5" />
                    </div>
                    <h3 class="text-sm font-bold">Immutable Audit Logs</h3>
                    <p class="text-3xs sm:text-2xs text-muted-foreground leading-relaxed">
                        Track every single request update, environment tweak, and collection fork. Easily roll back endpoint configurations to any historical state.
                    </p>
                </div>

                <!-- Grid Col 2 -->
                <div class="bg-white dark:bg-[#121211] border border-border/60 rounded-xl p-6 flex flex-col gap-4 text-left shadow-sm">
                    <div class="h-10 w-10 bg-green-500/10 rounded-lg flex items-center justify-center text-green-500">
                        <Users class="h-5 w-5" />
                    </div>
                    <h3 class="text-sm font-bold">Role-Based boundaries</h3>
                    <p class="text-3xs sm:text-2xs text-muted-foreground leading-relaxed">
                        Control exactly who can view secret values, create collections, or trigger checks. Perfect for managing external freelancers and QA agencies.
                    </p>
                </div>

                <!-- Grid Col 3 -->
                <div class="bg-white dark:bg-[#121211] border border-border/60 rounded-xl p-6 flex flex-col gap-4 text-left shadow-sm">
                    <div class="h-10 w-10 bg-green-500/10 rounded-lg flex items-center justify-center text-green-500">
                        <Award class="h-5 w-5" />
                    </div>
                    <h3 class="text-sm font-bold">SLA assurance Reports</h3>
                    <p class="text-3xs sm:text-2xs text-muted-foreground leading-relaxed">
                        Export cryptographically verified uptime logs directly as PDF or HTML dashboards to prove compliance benchmarks to downstream enterprise clients.
                    </p>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
