<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Sun, Moon, Menu, X } from 'lucide-vue-next';
import { ref, onMounted, computed, nextTick } from 'vue';
import { login, register, dashboard } from '@/routes';

const isMenuOpen = ref(false);
const isDark = ref(true);

const page = usePage();
const user = computed(() => page.props.auth?.user);

onMounted(() => {
    // Sync with HTML class
    isDark.value = document.documentElement.classList.contains('dark');
});

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

    if (!document.startViewTransition) {
        applyTheme();

        return;
    }

    const x = event.clientX;
    const y = event.clientY;
    const endRadius = Math.hypot(
        Math.max(x, window.innerWidth - x),
        Math.max(y, window.innerHeight - y),
    );

    const transition = document.startViewTransition(async () => {
        applyTheme();
        await nextTick();
    });

    transition.ready.then(() => {
        const clipPath = [
            `circle(0px at ${x}px ${y}px)`,
            `circle(${endRadius}px at ${x}px ${y}px)`,
        ];

        document.documentElement.animate(
            {
                clipPath: clipPath,
            },
            {
                duration: 500,
                easing: 'ease-out',
                pseudoElement: '::view-transition-new(root)',
            },
        );
    });
};

const dashboardUrl = computed(() => {
    if (!user.value) {
        return '#';
    }

    return dashboard().url;
});
</script>

<template>
    <div
        class="flex min-h-screen flex-col bg-[#FDFDFC] font-sans text-[#1b1b18] antialiased selection:bg-green-500/20 selection:text-green-500 dark:bg-[#0a0a0a] dark:text-[#f3f3f3]"
    >
        <!-- Background Grid overlay -->
        <div
            class="pointer-events-none absolute inset-0 z-0 bg-[linear-gradient(to_right,#8080800a_1px,transparent_1px),linear-gradient(to_bottom,#8080800a_1px,transparent_1px)] bg-[size:24px_24px]"
        ></div>
        <div
            class="pointer-events-none absolute top-0 left-1/2 z-0 h-[400px] w-full max-w-7xl -translate-x-1/2 bg-gradient-to-b from-green-500/5 via-transparent to-transparent blur-[120px]"
        ></div>

        <!-- Sticky Header -->
        <header
            class="sticky top-0 z-50 w-full border-b border-[#19140015] bg-[#FDFDFC]/80 backdrop-blur-md dark:border-[#1f1f1e] dark:bg-[#0a0a0a]/80"
        >
            <div
                class="relative z-10 mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8"
            >
                <!-- Logo -->
                <div class="flex items-center gap-8">
                    <Link href="/" class="group flex items-center gap-2.5">
                        <img
                            src="/logo.svg"
                            class="hidden h-9 w-auto dark:block"
                            alt="InstaRequest"
                        />
                        <img
                            src="/logo-light.svg"
                            class="block h-9 w-auto dark:hidden"
                            alt="InstaRequest"
                        />
                        <span
                            class="rounded border border-green-500/30 bg-green-500/5 px-1.5 py-0.5 font-mono text-[9px] tracking-widest text-green-500 uppercase"
                            >Beta</span
                        >
                    </Link>
                </div>

                <!-- Right Side CTAs -->
                <div class="flex items-center gap-4">
                    <!-- Theme Toggle -->
                    <button
                        @click="toggleDarkMode($event)"
                        class="rounded-lg border border-[#19140015] p-2 text-[#1b1b18] transition-all hover:bg-[#19140008] dark:border-[#222] dark:text-[#EDEDEC] dark:hover:bg-[#161615]"
                        aria-label="Toggle theme"
                    >
                        <Sun
                            v-if="isDark"
                            class="h-4 w-4 text-[#EDEDEC] transition-colors hover:text-green-400"
                        />
                        <Moon
                            v-else
                            class="h-4 w-4 text-[#1b1b18] transition-colors hover:text-green-600"
                        />
                    </button>

                    <!-- Auth Actions -->
                    <div class="hidden items-center gap-3 sm:flex">
                        <template v-if="user">
                            <Link
                                :href="dashboardUrl"
                                class="rounded-md border border-[#19140020] px-4 py-2 text-xs font-semibold transition-all hover:bg-muted dark:border-[#3E3E3A]"
                            >
                                Dashboard
                            </Link>
                        </template>
                        <template v-else>
                            <Link
                                :href="login()"
                                class="px-3 py-2 text-xs font-semibold text-muted-foreground transition-colors hover:text-foreground"
                            >
                                Log in
                            </Link>
                            <Link
                                :href="register()"
                                class="rounded-md bg-[#1b1b18] px-4 py-2 text-xs font-semibold text-white shadow-[0_1px_2px_rgba(0,0,0,0.1)] transition-all hover:bg-black dark:bg-[#eeeeec] dark:text-black dark:hover:bg-white"
                            >
                                Register
                            </Link>
                        </template>
                    </div>

                    <!-- Mobile Menu Button -->
                    <button
                        @click="isMenuOpen = !isMenuOpen"
                        class="rounded-md border border-transparent p-2 text-muted-foreground transition-all hover:border-border/40 hover:text-foreground md:hidden"
                    >
                        <X v-if="isMenuOpen" class="h-5 w-5" />
                        <Menu v-else class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <!-- Mobile Dropdown Menu -->
            <div
                v-if="isMenuOpen"
                class="absolute top-16 left-0 z-50 w-full border-b border-[#19140015] bg-[#FDFDFC] py-4 shadow-xl md:hidden dark:border-[#1f1f1e] dark:bg-[#0a0a0a]"
            >
                <nav class="flex flex-col gap-3 px-4">
                    <div class="flex items-center gap-3 pt-1">
                        <template v-if="user">
                            <Link
                                :href="dashboardUrl"
                                @click="isMenuOpen = false"
                                class="flex-1 rounded-md border border-[#19140020] px-4 py-2 text-center text-xs font-semibold dark:border-[#3E3E3A]"
                            >
                                Dashboard
                            </Link>
                        </template>
                        <template v-else>
                            <Link
                                :href="login()"
                                @click="isMenuOpen = false"
                                class="flex-1 rounded-md border border-[#19140010] py-2 text-center text-xs font-semibold text-muted-foreground hover:bg-muted dark:border-[#222]"
                            >
                                Log in
                            </Link>
                            <Link
                                :href="register()"
                                @click="isMenuOpen = false"
                                class="flex-1 rounded-md bg-[#1b1b18] py-2 text-center text-xs font-semibold text-white dark:bg-[#eeeeec] dark:text-black"
                            >
                                Register
                            </Link>
                        </template>
                    </div>
                </nav>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="relative z-10 flex-grow">
            <slot />
        </main>

        <!-- Footer -->
        <footer
            class="border-t border-[#19140015] bg-[#fbfbfa] dark:border-[#1f1f1e] dark:bg-[#070707]"
        >
            <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-10 md:grid-cols-12 md:gap-8">
                    <!-- Column 1: Core positioning -->
                    <div class="flex flex-col gap-4 md:col-span-6">
                        <Link
                            href="/"
                            class="group flex items-center gap-2 self-start"
                        >
                            <img
                                src="/logo.svg"
                                class="hidden h-8 w-auto dark:block"
                                alt="InstaRequest"
                            />
                            <img
                                src="/logo-light.svg"
                                class="block h-8 w-auto dark:hidden"
                                alt="InstaRequest"
                            />
                        </Link>
                        <p
                            class="text-xs leading-relaxed text-muted-foreground"
                        >
                            A premium developer-first collaborative API platform
                            and modern alternative to Postman built for realtime
                            teamwork, API monitoring, and high-performance
                            workflows.
                        </p>
                        <div
                            class="mt-2 flex items-center gap-3 text-muted-foreground"
                        >
                            <a
                                href="https://github.com"
                                target="_blank"
                                class="rounded border border-border/40 bg-muted/20 p-1.5 transition-colors hover:text-foreground"
                                aria-label="GitHub"
                            >
                                <Github class="h-4 w-4" />
                            </a>
                            <span
                                class="flex items-center gap-1.5 rounded border border-border bg-muted/20 px-2 py-0.5 font-mono text-[11px]"
                            >
                                <span
                                    class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"
                                ></span>
                                All Systems Operational
                            </span>
                        </div>
                    </div>
                </div>

                <div
                    class="mt-12 flex flex-col items-center justify-between gap-4 border-t border-[#19140015] pt-8 text-center sm:flex-row sm:text-left dark:border-[#1f1f1e]"
                >
                    <span class="text-xs text-muted-foreground">
                        &copy; 2026 InstaRequest API. Infrastructure-grade
                        collaborative API platform.
                    </span>
                    <div class="flex gap-6 text-xs text-muted-foreground">
                        <Link
                            href="/privacy-policy"
                            class="transition-colors hover:text-foreground"
                            >Privacy Policy</Link
                        >
                        <Link
                            href="/terms-of-service"
                            class="transition-colors hover:text-foreground"
                            >Terms of Service</Link
                        >
                        <a
                            href="#"
                            class="transition-colors hover:text-foreground"
                            >Status</a
                        >
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
