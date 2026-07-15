<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BookOpen,
    Search,
    Sun,
    Moon,
    ArrowRight,
    Code,
    Terminal,
    Sparkles,
    Clock,
    Layers,
    ExternalLink,
    ShieldCheck
} from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';

defineOptions({ layout: null as any });

// Props
const props = defineProps<{
    documentations: Array<{
        id: string;
        collection_id: string;
        public_slug: string;
        version: string;
        collection_name: string;
        collection_description: string | null;
        requests_count: number;
        updated_at: string;
    }>;
    appName: string;
}>();

// Theme State
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

// Search Filter
const searchQuery = ref('');

const filteredDocs = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.documentations;
    }

    const query = searchQuery.value.toLowerCase();

    return props.documentations.filter(
        (doc) =>
            doc.collection_name.toLowerCase().includes(query) ||
            (doc.collection_description && doc.collection_description.toLowerCase().includes(query)) ||
            doc.version.toLowerCase().includes(query)
    );
});
</script>

<template>
    <Head :title="props.appName + ' - API Documentation Hub'" />

    <div class="min-h-screen bg-background text-foreground flex flex-col font-sans transition-colors duration-300">
        <!-- Top Navigation Header -->
        <header class="sticky top-0 z-40 w-full border-b border-border bg-background/90 backdrop-blur-md px-6 py-4 flex items-center justify-between select-none">
            <div class="flex items-center gap-3">
                <div class="h-9 w-9 rounded-lg bg-primary/10 flex items-center justify-center border border-primary/20 shadow-xs">
                    <BookOpen class="h-5 w-5 text-primary" />
                </div>
                <div>
                    <h1 class="font-extrabold text-base tracking-tight text-foreground flex items-center gap-2">
                        {{ props.appName }}
                        <span class="text-[10px] bg-primary/10 text-primary border border-primary/20 px-2 py-0.5 rounded-full font-bold">
                            Developer Docs
                        </span>
                    </h1>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button
                    @click="toggleTheme"
                    class="rounded-lg p-2 border border-border bg-muted/30 text-muted-foreground hover:text-foreground transition-all"
                    aria-label="Toggle Theme"
                >
                    <Sun v-if="isDark" class="h-4 w-4" />
                    <Moon v-else class="h-4 w-4" />
                </button>

                <a
                    href="/login"
                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-primary text-primary-foreground hover:bg-primary/90 transition-all shadow-xs"
                >
                    Team Login
                    <ExternalLink class="h-3 w-3" />
                </a>
            </div>
        </header>

        <!-- Main Hero Section -->
        <main class="flex-1 w-full max-w-7xl mx-auto px-6 py-12 lg:py-16 flex flex-col gap-10">
            <!-- Hero Title Banner -->
            <div class="text-center max-w-3xl mx-auto space-y-4">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 border border-primary/20 text-primary text-xs font-semibold">
                    <Sparkles class="h-3.5 w-3.5" />
                    <span>API Reference & Developer Hub</span>
                </div>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-foreground">
                    Explore API Collections
                </h2>
                <p class="text-sm sm:text-base text-muted-foreground leading-relaxed">
                    Browse our published interactive endpoints, authentication methods, and code snippets to seamlessly integrate with our services.
                </p>

                <!-- Search Input -->
                <div class="pt-3 max-w-md mx-auto">
                    <div class="relative w-full">
                        <Search class="absolute left-3.5 top-3 h-4 w-4 text-muted-foreground" />
                        <input
                            type="text"
                            v-model="searchQuery"
                            placeholder="Search API documentation by name or description..."
                            class="h-10 w-full rounded-xl border border-border bg-muted/20 pl-10 pr-4 text-sm placeholder:text-muted-foreground/70 focus:outline-hidden focus:ring-2 focus:ring-primary/40 focus:border-primary transition-all shadow-xs"
                        />
                    </div>
                </div>
            </div>

            <!-- Collections Grid -->
            <div v-if="filteredDocs.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="doc in filteredDocs"
                    :key="doc.id"
                    class="group relative flex flex-col justify-between rounded-2xl border border-border bg-card/60 hover:bg-card p-6 shadow-xs hover:shadow-lg hover:border-primary/40 transition-all duration-300"
                >
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="h-10 w-10 rounded-xl bg-primary/10 flex items-center justify-center border border-primary/20 text-primary group-hover:scale-110 transition-transform duration-300">
                                <Terminal class="h-5 w-5" />
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-muted text-muted-foreground border border-border">
                                v{{ doc.version }}
                            </span>
                        </div>

                        <div class="space-y-1.5">
                            <h3 class="text-lg font-bold text-foreground group-hover:text-primary transition-colors flex items-center gap-1.5">
                                {{ doc.collection_name }}
                            </h3>
                            <p class="text-xs text-muted-foreground line-clamp-3 leading-relaxed">
                                {{ doc.collection_description || 'No detailed description provided for this collection yet.' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-border/60 flex items-center justify-between text-xs text-muted-foreground">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center gap-1 font-medium text-foreground">
                                <Code class="h-3.5 w-3.5 text-primary" />
                                {{ doc.requests_count }} {{ doc.requests_count === 1 ? 'Endpoint' : 'Endpoints' }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <Clock class="h-3.5 w-3.5" />
                                {{ doc.updated_at }}
                            </span>
                        </div>

                        <Link
                            :href="`/docs/${doc.collection_id}/${doc.public_slug}`"
                            class="inline-flex items-center gap-1 font-bold text-primary group-hover:translate-x-1 transition-transform"
                        >
                            Explore
                            <ArrowRight class="h-3.5 w-3.5" />
                        </Link>
                    </div>

                    <!-- Entire card clickable overlay -->
                    <Link
                        :href="`/docs/${doc.collection_id}/${doc.public_slug}`"
                        class="absolute inset-0 z-10 rounded-2xl focus:outline-hidden"
                        :aria-label="`Explore ${doc.collection_name} documentation`"
                    ></Link>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-16 px-6 rounded-2xl border border-dashed border-border bg-muted/10 max-w-xl mx-auto space-y-4">
                <div class="h-12 w-12 rounded-xl bg-muted/40 flex items-center justify-center mx-auto text-muted-foreground">
                    <Layers class="h-6 w-6" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-base font-bold text-foreground">
                        {{ searchQuery ? 'No matching collections found' : 'No public API collections available' }}
                    </h3>
                    <p class="text-xs text-muted-foreground max-w-md mx-auto">
                        {{ searchQuery ? 'Try adjusting your search terms to find what you are looking for.' : 'No documentation collections have been published as public yet. Team administrators can publish documentation from the dashboard.' }}
                    </p>
                </div>
                <div v-if="searchQuery">
                    <button
                        @click="searchQuery = ''"
                        class="px-3.5 py-1.5 text-xs font-semibold rounded-lg border border-border bg-card hover:bg-muted/30 transition-colors"
                    >
                        Clear Search
                    </button>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-border bg-background/50 py-6 px-6 text-center text-xs text-muted-foreground">
            <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2 font-medium">
                    <ShieldCheck class="h-4 w-4 text-primary" />
                    <span>Powered by {{ props.appName }} Open Source API Documentation Engine</span>
                </div>
                <div>
                    &copy; {{ new Date().getFullYear() }} {{ props.appName }}. All rights reserved.
                </div>
            </div>
        </footer>
    </div>
</template>
