<script setup lang="ts">
import { Link, usePage, useForm } from '@inertiajs/vue3';
import { login, register, dashboard } from '@/routes';
import {
    Sun, Moon, Menu, X, ArrowRight, Github,
    Terminal, Activity, Users, Layers, ShieldCheck,
    Loader2, CheckCircle2, Sparkles, AlertCircle
} from 'lucide-vue-next';
import { ref, onMounted, computed } from 'vue';

const isMenuOpen = ref(false);
const isDark = ref(true);

const page = usePage();
const user = computed(() => page.props.auth?.user);



onMounted(() => {
    // Sync with HTML class
    isDark.value = document.documentElement.classList.contains('dark');
});

import { nextTick } from 'vue';

const toggleDarkMode = (event: MouseEvent) => {
    const isDarkNext = !isDark.value;
    
    const applyTheme = () => {
        isDark.value = isDarkNext;

        if (isDarkNext) {
            document.documentElement.classList.add('dark');
            localStorage.setItem('appearance', 'dark');
            document.documentElement.style.backgroundColor = 'oklch(0.145 0 0)';
        } else {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('appearance', 'light');
            document.documentElement.style.backgroundColor = 'oklch(1 0 0)';
        }
    };

    // @ts-ignore
    if (!document.startViewTransition) {
        applyTheme();

        return;
    }

    const x = event.clientX;
    const y = event.clientY;
    const endRadius = Math.hypot(
        Math.max(x, window.innerWidth - x),
        Math.max(y, window.innerHeight - y)
    );

    // @ts-ignore
    const transition = document.startViewTransition(async () => {
        applyTheme();
        await nextTick();
    });

    transition.ready.then(() => {
        const clipPath = [
            `circle(0px at ${x}px ${y}px)`,
            `circle(${endRadius}px at ${x}px ${y}px)`
        ];
        
        document.documentElement.animate(
            {
                clipPath: clipPath,
            },
            {
                duration: 500,
                easing: 'ease-out',
                pseudoElement: '::view-transition-new(root)',
            }
        );
    });
};



const dashboardUrl = computed(() => {
    if (!user.value) {
return '#';
}

    const currentTeam = page.props.currentTeam as any;

    return currentTeam ? dashboard(currentTeam.slug).url : '/dashboard';
});
</script>

<template>
    <div class="min-h-screen flex flex-col bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#f3f3f3] font-sans antialiased selection:bg-green-500/20 selection:text-green-500">
        <!-- Background Grid overlay -->
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#8080800a_1px,transparent_1px),linear-gradient(to_bottom,#8080800a_1px,transparent_1px)] bg-[size:24px_24px] pointer-events-none z-0"></div>
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-[400px] bg-gradient-to-b from-green-500/5 via-transparent to-transparent blur-[120px] pointer-events-none z-0"></div>

        <!-- Sticky Header -->
        <header class="sticky top-0 z-50 w-full border-b border-[#19140015] dark:border-[#1f1f1e] bg-[#FDFDFC]/80 dark:bg-[#0a0a0a]/80 backdrop-blur-md">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between relative z-10">
                <!-- Logo -->
                <div class="flex items-center gap-8">
                    <Link href="/" class="flex items-center gap-2.5 group">
                        <img src="/logo-icon.svg" class="h-9 w-auto" alt="InstaRequest" />
                        <span class="font-bold text-lg tracking-tight bg-gradient-to-r from-[#1b1b18] via-[#484844] to-[#1b1b18] dark:from-[#ffffff] dark:via-[#cccccc] dark:to-[#ffffff] bg-clip-text text-transparent group-hover:text-green-500 transition-colors duration-300">
                            InstaRequest
                        </span>
                        <span class="text-[9px] font-mono border border-green-500/30 text-green-500 px-1.5 py-0.5 rounded uppercase tracking-widest bg-green-500/5">Beta</span>
                    </Link>

                    <!-- Desktop Nav Links -->
                    <nav class="hidden md:flex items-center gap-6">
                        <Link href="/request-builder" class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors py-1.5">Request Builder</Link>

                        <Link href="/api-collaboration" class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors py-1.5">Collaboration</Link>
                        <Link href="/api-monitoring" class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors py-1.5">Monitoring</Link>
                        <Link href="/realtime-api-workspace" class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors py-1.5">Workspace</Link>
                        <Link href="/postman-alternative" class="text-sm font-semibold text-green-500 hover:text-green-600 transition-colors py-1.5 flex items-center gap-1">
                            Postman Alternative
                            <Sparkles class="h-3 w-3 animate-pulse" />
                        </Link>
                    </nav>
                </div>

                <!-- Right Side CTAs -->
                <div class="flex items-center gap-4">
                    <!-- Theme Toggle -->
                    <button @click="toggleDarkMode($event)" class="p-2 rounded-lg border border-[#19140015] dark:border-[#222] hover:bg-[#19140008] dark:hover:bg-[#161615] transition-all text-[#1b1b18] dark:text-[#EDEDEC]" aria-label="Toggle theme">
                        <Sun v-if="isDark" class="h-4 w-4 text-[#EDEDEC] hover:text-green-400 transition-colors" />
                        <Moon v-else class="h-4 w-4 text-[#1b1b18] hover:text-green-600 transition-colors" />
                    </button>

                    <!-- Auth Actions -->
                    <div class="hidden sm:flex items-center gap-3">
                        <template v-if="user">
                            <Link :href="dashboardUrl" class="text-xs font-semibold px-4 py-2 border border-[#19140020] dark:border-[#3E3E3A] hover:bg-muted rounded-md transition-all">
                                Dashboard
                            </Link>
                        </template>
                        <template v-else>
                            <Link :href="login()" class="text-xs font-semibold text-muted-foreground hover:text-foreground px-3 py-2 transition-colors">
                                Log in
                            </Link>
                            <Link :href="register()" class="text-xs font-semibold px-4 py-2 bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-black rounded-md hover:bg-black dark:hover:bg-white transition-all shadow-[0_1px_2px_rgba(0,0,0,0.1)]">
                                Register
                            </Link>
                        </template>

                    </div>

                    <!-- Mobile Menu Button -->
                    <button @click="isMenuOpen = !isMenuOpen" class="md:hidden p-2 text-muted-foreground hover:text-foreground border border-transparent hover:border-border/40 rounded-md transition-all">
                        <X v-if="isMenuOpen" class="h-5 w-5" />
                        <Menu v-else class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <!-- Mobile Dropdown Menu -->
            <div v-if="isMenuOpen" class="md:hidden absolute top-16 left-0 w-full border-b border-[#19140015] dark:border-[#1f1f1e] bg-[#FDFDFC] dark:bg-[#0a0a0a] shadow-xl py-4 z-50">
                <nav class="flex flex-col px-4 gap-3">
                    <Link href="/request-builder" @click="isMenuOpen = false" class="text-sm font-medium py-2 border-b border-border/20 text-muted-foreground hover:text-foreground transition-colors">Request Builder</Link>

                    <Link href="/api-collaboration" @click="isMenuOpen = false" class="text-sm font-medium py-2 border-b border-border/20 text-muted-foreground hover:text-foreground transition-colors">Collaboration</Link>
                    <Link href="/api-monitoring" @click="isMenuOpen = false" class="text-sm font-medium py-2 border-b border-border/20 text-muted-foreground hover:text-foreground transition-colors">Monitoring</Link>
                    <Link href="/realtime-api-workspace" @click="isMenuOpen = false" class="text-sm font-medium py-2 border-b border-border/20 text-muted-foreground hover:text-foreground transition-colors">Workspace</Link>
                    <Link href="/postman-alternative" @click="isMenuOpen = false" class="text-sm font-semibold py-2 border-b border-border/20 text-green-500 hover:text-green-600 transition-colors flex items-center gap-1">
                        Postman Alternative
                        <Sparkles class="h-3 w-3 animate-pulse" />
                    </Link>

                    <div class="flex items-center gap-3 pt-3">
                        <template v-if="user">
                            <Link :href="dashboardUrl" @click="isMenuOpen = false" class="flex-1 text-center text-xs font-semibold px-4 py-2 border border-[#19140020] dark:border-[#3E3E3A] rounded-md">
                                Dashboard
                            </Link>
                        </template>
                        <template v-else>
                            <Link :href="login()" @click="isMenuOpen = false" class="flex-1 text-center text-xs font-semibold border border-[#19140010] dark:border-[#222] py-2 rounded-md hover:bg-muted text-muted-foreground">
                                Log in
                            </Link>
                            <Link :href="register()" @click="isMenuOpen = false" class="flex-1 text-center text-xs font-semibold bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-black py-2 rounded-md">
                                Register
                            </Link>
                        </template>
                    </div>
                </nav>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-grow relative z-10">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="border-t border-[#19140015] dark:border-[#1f1f1e] bg-[#fbfbfa] dark:bg-[#070707]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-10 md:gap-8">
                    <!-- Column 1: Core positioning -->
                    <div class="md:col-span-6 flex flex-col gap-4">
                        <Link href="/" class="flex items-center gap-2 group self-start">
                            <img src="/logo-icon.svg" class="h-8 w-auto" alt="InstaRequest" />
                            <span class="font-bold text-base tracking-tight text-[#1b1b18] dark:text-[#ffffff]">
                                InstaRequest
                            </span>
                        </Link>
                        <p class="text-xs text-muted-foreground leading-relaxed">
                            A premium developer-first collaborative API platform and modern alternative to Postman built for realtime teamwork, API monitoring, and high-performance workflows.
                        </p>
                        <div class="flex items-center gap-3 mt-2 text-muted-foreground">
                            <a href="https://github.com" target="_blank" class="hover:text-foreground transition-colors p-1.5 rounded border border-border/40 bg-muted/20" aria-label="GitHub">
                                <Github class="h-4 w-4" />
                            </a>
                            <span class="text-[11px] font-mono border border-border px-2 py-0.5 rounded bg-muted/20 flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                All Systems Operational
                            </span>
                        </div>
                    </div>

                    <!-- Column 2: Product Features -->
                    <div class="md:col-span-3 flex flex-col gap-3">
                        <span class="text-xs font-semibold tracking-wider text-foreground uppercase">Product</span>
                        <Link href="/request-builder" class="text-xs text-muted-foreground hover:text-foreground transition-colors">Request Builder</Link>

                        <Link href="/api-collaboration" class="text-xs text-muted-foreground hover:text-foreground transition-colors">Realtime Sync</Link>
                        <Link href="/api-monitoring" class="text-xs text-muted-foreground hover:text-foreground transition-colors">API Monitoring</Link>
                        <Link href="/realtime-api-workspace" class="text-xs text-muted-foreground hover:text-foreground transition-colors">Team Workspaces</Link>
                    </div>

                    <!-- Column 3: Comparisons -->
                    <div class="md:col-span-3 flex flex-col gap-3">
                        <span class="text-xs font-semibold tracking-wider text-foreground uppercase">Comparisons</span>
                        <Link href="/postman-alternative" class="text-xs text-muted-foreground hover:text-foreground transition-colors">Postman Alternative</Link>
                        <Link href="/postman-alternative#compare" class="text-xs text-muted-foreground hover:text-foreground transition-colors">InstaRequest vs Postman</Link>
                        <Link href="/postman-alternative#features" class="text-xs text-muted-foreground hover:text-foreground transition-colors">Core Engine</Link>
                    </div>


                </div>

                <div class="mt-12 pt-8 border-t border-[#19140015] dark:border-[#1f1f1e] flex flex-col sm:flex-row items-center justify-between gap-4 text-center sm:text-left">
                    <span class="text-xs text-muted-foreground">
                        &copy; 2026 InstaRequest API. Infrastructure-grade collaborative API platform.
                    </span>
                    <div class="flex gap-6 text-xs text-muted-foreground">
                        <Link href="/privacy-policy" class="hover:text-foreground transition-colors">Privacy Policy</Link>
                        <Link href="/terms-of-service" class="hover:text-foreground transition-colors">Terms of Service</Link>
                        <a href="#" class="hover:text-foreground transition-colors">Status</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
