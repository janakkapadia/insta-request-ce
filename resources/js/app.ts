import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import type { DefineComponent } from 'vue';
import { createSSRApp, h, defineComponent } from 'vue';

import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { breadcrumbsMap } from '@/lib/layoutState';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => {
        const page = resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue')
        );

        return page.then((module) => {
            if (module.default) {
                // Save layout configuration (like dynamic breadcrumbs) to the global registry
                // Only save if it hasn't been overwritten by our own global layouts yet (for cached modules)
                if (module.default.layout !== AppLayout && module.default.layout !== AuthLayout && !Array.isArray(module.default.layout)) {
                    breadcrumbsMap.set(name, module.default.layout);
                }

                const marketingPages = [
                    'Documentation/PublicViewer',
                    'PrivacyPolicy',
                    'TermsOfService'
                ];

                if (marketingPages.includes(name)) {
                    module.default.layout = null;
                } else if (name.startsWith('auth/')) {
                    module.default.layout = AuthLayout;
                } else if (name.startsWith('settings/') || name.startsWith('teams/')) {
                    module.default.layout = [AppLayout, SettingsLayout];
                } else {
                    module.default.layout = AppLayout;
                }
            }

            return module;
        });
    },
    setup({ el, App, props, plugin }) {
        const app = createSSRApp({ render: () => h(App, props) });
        app.use(createPinia());
        app.use(plugin);
        app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
