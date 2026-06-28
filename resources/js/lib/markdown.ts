/**
 * Lightweight, safe client-side Markdown to HTML compiler.
 */
export function parseMarkdown(markdown: string | null | undefined): string {
    if (!markdown) {
return '';
}

    // Escape HTML to prevent XSS
    let html = markdown
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    // 1. Fenced Code Blocks: ```json ... ```
    html = html.replace(/```(\w*)\n([\s\S]*?)\n```/g, (_, lang, code) => {
        const languageClass = lang ? `class="language-${lang}"` : '';

        return `<pre class="bg-muted p-4 rounded-lg my-4 overflow-x-auto border border-border"><code ${languageClass}>${code.trim()}</code></pre>`;
    });

    // 2. Blockquotes: > text
    html = html.replace(/^&gt;\s+(.+)$/gm, '<blockquote class="border-l-4 border-primary pl-4 italic text-muted-foreground my-4">$1</blockquote>');

    // 3. Headers: # through ######
    html = html.replace(/^######\s+(.+)$/gm, '<h6 class="text-sm font-semibold mt-4 mb-2 text-foreground">$1</h6>');
    html = html.replace(/^#####\s+(.+)$/gm, '<h5 class="text-base font-semibold mt-4 mb-2 text-foreground">$1</h5>');
    html = html.replace(/^####\s+(.+)$/gm, '<h4 class="text-lg font-semibold mt-5 mb-2 text-foreground">$1</h4>');
    html = html.replace(/^###\s+(.+)$/gm, '<h3 class="text-xl font-bold mt-6 mb-3 text-foreground">$1</h3>');
    html = html.replace(/^##\s+(.+)$/gm, '<h2 class="text-2xl font-bold mt-8 mb-4 border-b border-border pb-1 text-foreground">$1</h2>');
    html = html.replace(/^#\s+(.+)$/gm, '<h1 class="text-3xl font-extrabold mt-10 mb-6 border-b border-border pb-2 text-foreground">$1</h1>');

    // 4. Inline Code: `code`
    html = html.replace(/`([^`]+)`/g, '<code class="bg-muted px-1.5 py-0.5 rounded text-xs font-mono font-semibold text-primary">$1</code>');

    // 5. Bold & Italic
    html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    html = html.replace(/_([^_]+)_/g, '<em>$1</em>');

    // 6. Links: [text](url)
    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" class="text-primary underline hover:text-primary/80">$1</a>');

    // 7. Bullet Lists (Unordered & Ordered)
    html = html.replace(/^\s*[\*\-]\s+(.+)$/gm, '<li class="ml-4 list-disc text-muted-foreground">$1</li>');
    html = html.replace(/^\s*\d+\.\s+(.+)$/gm, '<li class="ml-4 list-decimal text-muted-foreground">$1</li>');

    // Wrap list items in list container tags
    html = html.replace(/(<li[\s\S]*?<\/li>)/g, (match) => {
        return `<ul class="my-2 space-y-1">${match}</ul>`;
    });
    // Deduplicate consecutive list wraps
    html = html.replace(/<\/ul>\s*<ul class="my-2 space-y-1">/g, '');

    // 8. Paragraphs: split by double newlines and wrap in <p> if not inside other block elements
    const lines = html.split(/\n\n+/);
    html = lines
        .map((line) => {
            const trimmed = line.trim();

            if (!trimmed) {
return '';
}

            // If it starts with block-level tags, don't wrap in <p>
            if (/^(<pre|<h[1-6]|<blockquote|<ul|<ol|<li)/.test(trimmed)) {
                return trimmed;
            }

            return `<p class="leading-relaxed text-muted-foreground mb-4">${trimmed}</p>`;
        })
        .filter(Boolean)
        .join('');

    return html;
}
