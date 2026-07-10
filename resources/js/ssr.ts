import { createInertiaApp } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import type { DefineComponent } from 'vue';
import { createSSRApp, h, defineComponent } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const appName = import.meta.env.VITE_APP_NAME || 'InstaRequest';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => (title ? `${title} - ${appName}` : appName),
        resolve: (name) => {
            const page = resolvePageComponent(
                `./pages/${name}.vue`,
                import.meta.glob<DefineComponent>('./pages/**/*.vue')
            );

            return page.then((module) => {
                if (module.default) {
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
        setup({ App, props, plugin }) {
            return createSSRApp({ render: () => h(App, props) })
                .use(createPinia())
                .use(plugin);
        },
    })
);
