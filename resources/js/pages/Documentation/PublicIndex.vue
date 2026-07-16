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
    ShieldCheck,
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
            (doc.collection_description &&
                doc.collection_description.toLowerCase().includes(query)) ||
            doc.version.toLowerCase().includes(query),
    );
});
</script>

<template>
    <Head :title="props.appName + ' - API Documentation Hub'" />

    <div
        class="flex min-h-screen flex-col bg-background font-sans text-foreground transition-colors duration-300"
    >
        <!-- Top Navigation Header -->
        <header
            class="sticky top-0 z-40 flex w-full items-center justify-between border-b border-border bg-background/90 px-6 py-4 backdrop-blur-md select-none"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-9 w-9 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 shadow-xs"
                >
                    <BookOpen class="h-5 w-5 text-primary" />
                </div>
                <div>
                    <h1
                        class="flex items-center gap-2 text-base font-extrabold tracking-tight text-foreground"
                    >
                        {{ props.appName }}
                        <span
                            class="rounded-full border border-primary/20 bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary"
                        >
                            Developer Docs
                        </span>
                    </h1>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button
                    @click="toggleTheme"
                    class="rounded-lg border border-border bg-muted/30 p-2 text-muted-foreground transition-all hover:text-foreground"
                    aria-label="Toggle Theme"
                >
                    <Sun v-if="isDark" class="h-4 w-4" />
                    <Moon v-else class="h-4 w-4" />
                </button>

                <a
                    href="/login"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3.5 py-1.5 text-xs font-semibold text-primary-foreground shadow-xs transition-all hover:bg-primary/90"
                >
                    Team Login
                    <ExternalLink class="h-3 w-3" />
                </a>
            </div>
        </header>

        <!-- Main Hero Section -->
        <main
            class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-10 px-6 py-12 lg:py-16"
        >
            <!-- Hero Title Banner -->
            <div class="mx-auto max-w-3xl space-y-4 text-center">
                <div
                    class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-semibold text-primary"
                >
                    <Sparkles class="h-3.5 w-3.5" />
                    <span>API Reference & Developer Hub</span>
                </div>
                <h2
                    class="text-3xl font-extrabold tracking-tight text-foreground sm:text-4xl lg:text-5xl"
                >
                    Explore API Collections
                </h2>
                <p
                    class="text-sm leading-relaxed text-muted-foreground sm:text-base"
                >
                    Browse our published interactive endpoints, authentication
                    methods, and code snippets to seamlessly integrate with our
                    services.
                </p>

                <!-- Search Input -->
                <div class="mx-auto max-w-md pt-3">
                    <div class="relative w-full">
                        <Search
                            class="absolute top-3 left-3.5 h-4 w-4 text-muted-foreground"
                        />
                        <input
                            type="text"
                            v-model="searchQuery"
                            placeholder="Search API documentation by name or description..."
                            class="h-10 w-full rounded-xl border border-border bg-muted/20 pr-4 pl-10 text-sm shadow-xs transition-all placeholder:text-muted-foreground/70 focus:border-primary focus:ring-2 focus:ring-primary/40 focus:outline-hidden"
                        />
                    </div>
                </div>
            </div>

            <!-- Collections Grid -->
            <div
                v-if="filteredDocs.length > 0"
                class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3"
            >
                <div
                    v-for="doc in filteredDocs"
                    :key="doc.id"
                    class="group relative flex flex-col justify-between rounded-2xl border border-border bg-card/60 p-6 shadow-xs transition-all duration-300 hover:border-primary/40 hover:bg-card hover:shadow-lg"
                >
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl border border-primary/20 bg-primary/10 text-primary transition-transform duration-300 group-hover:scale-110"
                            >
                                <Terminal class="h-5 w-5" />
                            </div>
                            <span
                                class="inline-flex items-center rounded-full border border-border bg-muted px-2.5 py-0.5 text-[11px] font-bold text-muted-foreground"
                            >
                                v{{ doc.version }}
                            </span>
                        </div>

                        <div class="space-y-1.5">
                            <h3
                                class="flex items-center gap-1.5 text-lg font-bold text-foreground transition-colors group-hover:text-primary"
                            >
                                {{ doc.collection_name }}
                            </h3>
                            <p
                                class="line-clamp-3 text-xs leading-relaxed text-muted-foreground"
                            >
                                {{
                                    doc.collection_description ||
                                    'No detailed description provided for this collection yet.'
                                }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="mt-6 flex items-center justify-between border-t border-border/60 pt-4 text-xs text-muted-foreground"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="inline-flex items-center gap-1 font-medium text-foreground"
                            >
                                <Code class="h-3.5 w-3.5 text-primary" />
                                {{ doc.requests_count }}
                                {{
                                    doc.requests_count === 1
                                        ? 'Endpoint'
                                        : 'Endpoints'
                                }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <Clock class="h-3.5 w-3.5" />
                                {{ doc.updated_at }}
                            </span>
                        </div>

                        <Link
                            :href="`/docs/${doc.collection_id}/${doc.public_slug}`"
                            class="inline-flex items-center gap-1 font-bold text-primary transition-transform group-hover:translate-x-1"
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
            <div
                v-else
                class="mx-auto max-w-xl space-y-4 rounded-2xl border border-dashed border-border bg-muted/10 px-6 py-16 text-center"
            >
                <div
                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-muted/40 text-muted-foreground"
                >
                    <Layers class="h-6 w-6" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-base font-bold text-foreground">
                        {{
                            searchQuery
                                ? 'No matching collections found'
                                : 'No public API collections available'
                        }}
                    </h3>
                    <p class="mx-auto max-w-md text-xs text-muted-foreground">
                        {{
                            searchQuery
                                ? 'Try adjusting your search terms to find what you are looking for.'
                                : 'No documentation collections have been published as public yet. Team administrators can publish documentation from the dashboard.'
                        }}
                    </p>
                </div>
                <div v-if="searchQuery">
                    <button
                        @click="searchQuery = ''"
                        class="rounded-lg border border-border bg-card px-3.5 py-1.5 text-xs font-semibold transition-colors hover:bg-muted/30"
                    >
                        Clear Search
                    </button>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer
            class="border-t border-border bg-background/50 px-6 py-6 text-center text-xs text-muted-foreground"
        >
            <div
                class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 sm:flex-row"
            >
                <div class="flex items-center gap-2 font-medium">
                    <ShieldCheck class="h-4 w-4 text-primary" />
                    <span
                        >Powered by {{ props.appName }} Open Source API
                        Documentation Engine</span
                    >
                </div>
                <div>
                    &copy; {{ new Date().getFullYear() }} {{ props.appName }}.
                    All rights reserved.
                </div>
            </div>
        </footer>
    </div>
</template>
