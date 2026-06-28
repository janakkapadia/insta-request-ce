export const getMethodTextColor = (method?: string) => {
    if (!method) {
return 'text-gray-500 dark:text-gray-400';
}

    switch (method.toUpperCase()) {
        case 'GET': return 'text-emerald-500 dark:text-emerald-400';
        case 'POST': return 'text-amber-500 dark:text-amber-400';
        case 'PUT': return 'text-sky-500 dark:text-sky-400';
        case 'PATCH': return 'text-indigo-500 dark:text-indigo-400';
        case 'DELETE': return 'text-rose-500 dark:text-rose-400';
        case 'OPTIONS': return 'text-purple-500 dark:text-purple-400';
        case 'HEAD': return 'text-teal-500 dark:text-teal-400';
        default: return 'text-muted-foreground';
    }
};

export const getMethodBadgeColors = (method?: string) => {
    if (!method) {
return 'text-gray-500 bg-gray-500/10 border-gray-500/20';
}

    switch (method.toUpperCase()) {
        case 'GET': return 'text-emerald-500 bg-emerald-500/10 border-emerald-500/20';
        case 'POST': return 'text-amber-500 bg-amber-500/10 border-amber-500/20';
        case 'PUT': return 'text-sky-500 bg-sky-500/10 border-sky-500/20';
        case 'PATCH': return 'text-indigo-500 bg-indigo-500/10 border-indigo-500/20';
        case 'DELETE': return 'text-rose-500 bg-rose-500/10 border-rose-500/20';
        case 'OPTIONS': return 'text-purple-500 bg-purple-500/10 border-purple-500/20';
        case 'HEAD': return 'text-teal-500 bg-teal-500/10 border-teal-500/20';
        default: return 'text-muted-foreground bg-muted border-border';
    }
};

// Aliased to badge colors to maintain backwards compatibility where possible,
// but explicitly using the specific ones is better.
export const getMethodColor = getMethodBadgeColors;
