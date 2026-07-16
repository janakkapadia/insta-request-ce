import { reactive } from 'vue';

export const layoutState = reactive<{
    breadcrumbs: any[];
}>({
    breadcrumbs: [],
});

export const breadcrumbsMap = new Map<string, any>();

export function updateLayoutForPage(name: string, props: any) {
    let activeBreadcrumbs: any[] = [];
    let hasConfig = false;

    // Check if the page defined a custom layout mapping via defineOptions
    const layoutConfig = breadcrumbsMap.get(name);

    if (layoutConfig) {
        hasConfig = true;
        const resolved =
            typeof layoutConfig === 'function'
                ? layoutConfig(props)
                : layoutConfig;

        if (resolved && resolved.managed) {
            return; // Component manages breadcrumbs dynamically
        }

        if (resolved && resolved.breadcrumbs) {
            activeBreadcrumbs = resolved.breadcrumbs;
        }
    }

    // Auto-generate breadcrumbs based on the route/page name if no specific config exists
    if (!hasConfig && (!activeBreadcrumbs || activeBreadcrumbs.length === 0)) {
        activeBreadcrumbs = generateBreadcrumbs(name, props);
    }

    layoutState.breadcrumbs = activeBreadcrumbs || [];
}

function generateBreadcrumbs(name: string, props: any) {
    const breadcrumbs = [];

    switch (name) {
        case 'Dashboard':
            breadcrumbs.push({ title: 'Dashboard', href: '/dashboard' });
            break;
        case 'Collections/Index':
        case 'Collections/Show':
            breadcrumbs.push({ title: 'Collections', href: '/collections' });
            break;
        case 'History/Index':
            breadcrumbs.push({ title: 'History', href: '/history' });
            break;
        case 'Environments/Index':
            breadcrumbs.push({ title: 'Environments', href: '/environments' });
            break;
        case 'Documentation/Dashboard':
            breadcrumbs.push({
                title: 'Documentation',
                href: '/documentation',
            });
            break;
        case 'teams/Index':
            breadcrumbs.push({ title: 'Teams', href: '/settings/teams' });
            break;
        case 'teams/Edit':
            breadcrumbs.push({ title: 'Teams', href: '/settings/teams' });

            if (props.team) {
                breadcrumbs.push({
                    title: props.team.name,
                    href: `/settings/teams/${props.team.slug}`,
                });
            }

            break;
        case 'settings/Profile':
            breadcrumbs.push({ title: 'Settings', href: '/settings/profile' });
            breadcrumbs.push({ title: 'Profile', href: '/settings/profile' });
            break;
        case 'settings/Appearance':
            breadcrumbs.push({ title: 'Settings', href: '/settings/profile' });
            breadcrumbs.push({
                title: 'Appearance',
                href: '/settings/appearance',
            });
            break;
        case 'settings/Security':
            breadcrumbs.push({ title: 'Settings', href: '/settings/profile' });
            breadcrumbs.push({ title: 'Security', href: '/settings/security' });
            break;
        default:
            // Generic fallback for pages that don't match specific cases
            if (name && !name.includes('auth/')) {
                const parts = name.split('/');

                for (let i = 0; i < parts.length; i++) {
                    const title = parts[i].replace(/([A-Z])/g, ' $1').trim();

                    if (title.toLowerCase() !== 'index') {
                        breadcrumbs.push({
                            title: title,
                            href:
                                typeof window !== 'undefined'
                                    ? window.location.pathname
                                    : '/', // fallback href
                        });
                    }
                }
            }

            break;
    }

    return breadcrumbs;
}
