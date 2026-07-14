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

    // 1. Fenced Code Blocks: store in array to protect from inline/block formatting
    const codeBlocks: string[] = [];

    // Match multi-line and indented fenced code blocks: ```lang ... ``` or ~~~lang ... ~~~
    html = html.replace(
        /^[ \t]*(?:&gt;[ \t]*)?(```|~~~)[ \t]*([a-zA-Z0-9_-]*)[ \t]*\r?\n([\s\S]*?)\r?\n[ \t]*(?:&gt;[ \t]*)?\1[ \t]*(?:\r?\n|$)/gm,
        (match, _, lang, code) => {
            const isBlockquote = match.trim().startsWith('&gt;');
            const cleanCode = isBlockquote
                ? code
                      .split(/\r?\n/)
                      .map((line: string) => line.replace(/^[ \t]*&gt;[ \t]?/, ''))
                      .join('\n')
                      .trim()
                : code.trim();

            const languageClass = lang ? `class="language-${lang.trim()}"` : '';
            const placeholder = `@@@FENCEDCODEBLOCK${codeBlocks.length}@@@`;

            codeBlocks.push(
                `<pre class="bg-muted p-4 rounded-lg my-4 overflow-x-auto border border-border text-xs font-mono text-foreground not-italic font-normal"><code ${languageClass}>${cleanCode}</code></pre>`,
            );

            return isBlockquote ? `&gt; ${placeholder}\n\n` : `${placeholder}\n\n`;
        },
    );

    // Also catch any remaining code blocks that don't start at the beginning of a line
    html = html.replace(
        /(?:&gt;[ \t]*)?(```|~~~)[ \t]*([a-zA-Z0-9_-]*)[ \t]*\r?\n([\s\S]*?)\r?\n[ \t]*(?:&gt;[ \t]*)?\1/g,
        (match, _, lang, code) => {
            const isBlockquote = match.trim().startsWith('&gt;');
            const cleanCode = isBlockquote
                ? code
                      .split(/\r?\n/)
                      .map((line: string) => line.replace(/^[ \t]*&gt;[ \t]?/, ''))
                      .join('\n')
                      .trim()
                : code.trim();

            const languageClass = lang ? `class="language-${lang.trim()}"` : '';
            const placeholder = `@@@FENCEDCODEBLOCK${codeBlocks.length}@@@`;

            codeBlocks.push(
                `<pre class="bg-muted p-4 rounded-lg my-4 overflow-x-auto border border-border text-xs font-mono text-foreground not-italic font-normal"><code ${languageClass}>${cleanCode}</code></pre>`,
            );

            return isBlockquote ? `&gt; ${placeholder}\n\n` : `${placeholder}\n\n`;
        },
    );

    // 2. Inline Code: extract so bold/italic doesn't modify text inside backticks
    const inlineCodeBlocks: string[] = [];
    html = html.replace(/`([^`]+)`/g, (_, code) => {
        const placeholder = `@@@INLINECODE${inlineCodeBlocks.length}@@@`;
        inlineCodeBlocks.push(
            `<code class="bg-muted px-1.5 py-0.5 rounded text-xs font-mono font-semibold text-primary">${code}</code>`,
        );
        return placeholder;
    });

    // 3. Blockquotes: group consecutive lines starting with &gt; into a single blockquote
    html = html.replace(
        /^(?:[ \t]*&gt;[^\n]*\r?\n?)+/gm,
        (blockquoteBlock) => {
            const innerLines = blockquoteBlock
                .trim()
                .split(/\r?\n/)
                .map((line) => line.replace(/^[ \t]*&gt;[ \t]?/, '').trim())
                .filter(Boolean);

            if (innerLines.length === 0) {
                return '';
            }

            const formattedContent = innerLines
                .map((line) => {
                    if (/^@@@FENCEDCODEBLOCK\d+@@@$/.test(line) || /^@@@INLINECODE\d+@@@$/.test(line)) {
                        return line;
                    }
                    if (/^(<h[1-6]|<ul|<ol|<li|<div|<table|<hr)/.test(line)) {
                        return line;
                    }
                    return `<p class="my-1">${line}</p>`;
                })
                .join('\n');

            return `<blockquote class="border-l-4 border-primary pl-4 italic text-muted-foreground my-4 space-y-2">\n${formattedContent}\n</blockquote>\n\n`;
        },
    );

    // 4. Headers: # through ######
    html = html.replace(/^######\s+(.+)$/gm, '<h6 class="text-sm font-semibold mt-4 mb-2 text-foreground">$1</h6>');
    html = html.replace(/^#####\s+(.+)$/gm, '<h5 class="text-base font-semibold mt-4 mb-2 text-foreground">$1</h5>');
    html = html.replace(/^####\s+(.+)$/gm, '<h4 class="text-lg font-semibold mt-5 mb-2 text-foreground">$1</h4>');
    html = html.replace(/^###\s+(.+)$/gm, '<h3 class="text-xl font-bold mt-6 mb-3 text-foreground">$1</h3>');
    html = html.replace(/^##\s+(.+)$/gm, '<h2 class="text-2xl font-bold mt-8 mb-4 border-b border-border pb-1 text-foreground">$1</h2>');
    html = html.replace(/^#\s+(.+)$/gm, '<h1 class="text-3xl font-extrabold mt-10 mb-6 border-b border-border pb-2 text-foreground">$1</h1>');

    // 5. Horizontal Rules: --- or *** or ___ on their own line
    html = html.replace(/^[ \t]*([-*_])[ \t]*\1[ \t]*\1+[ \t]*$/gm, '<hr class="my-6 border-t border-border" />');

    // 6. Bold & Italic
    html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    html = html.replace(/_([^_]+)_/g, '<em>$1</em>');

    // 7. Links: [text](url)
    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" class="text-primary underline hover:text-primary/80">$1</a>');

    // 8. Bullet Lists (Unordered & Ordered)
    html = html.replace(/^\s*[\*\-]\s+(.+)$/gm, '<li class="ml-4 list-disc text-muted-foreground">$1</li>');
    html = html.replace(/^\s*\d+\.\s+(.+)$/gm, '<li class="ml-4 list-decimal text-muted-foreground">$1</li>');

    // Wrap list items in list container tags
    html = html.replace(/(<li[\s\S]*?<\/li>)/g, (match) => {
        return `<ul class="my-2 space-y-1">${match}</ul>`;
    });
    // Deduplicate consecutive list wraps
    html = html.replace(/<\/ul>\s*<ul class="my-2 space-y-1">/g, '');

    // 9. Markdown Tables (GFM style)
    html = html.replace(
        /^(([ \t]*\|?([^\n]+?)\|?[ \t]*\r?\n[ \t]*\|?([ \t]*:?[-=]+:?[ \t]*\|?)+[ \t]*\r?\n)([ \t]*\|?[^\n]+\|?[ \t]*(?:\r?\n|$))*)/gm,
        (tableBlock) => {
            const lines = tableBlock.trim().split(/\r?\n/);
            if (lines.length < 2) {
                return tableBlock;
            }

            if (!/^[ \t]*\|?[ \t]*:?[-=]+:?[ \t]*(\|([ \t]*:?[-=]+:?[ \t]*\|?)+)?$/.test(lines[1])) {
                return tableBlock;
            }

            const parseRow = (rowStr: string, isHeader: boolean) => {
                let trimmedRow = rowStr.trim();
                if (trimmedRow.startsWith('|')) {
                    trimmedRow = trimmedRow.slice(1);
                }
                if (trimmedRow.endsWith('|')) {
                    trimmedRow = trimmedRow.slice(0, -1);
                }

                const cells = trimmedRow.split('|');
                const tag = isHeader ? 'th' : 'td';
                const cellClass = isHeader
                    ? 'border border-border bg-muted/80 px-4 py-2.5 text-left font-semibold text-foreground'
                    : 'border border-border px-4 py-2.5 text-muted-foreground';

                return `<tr>${cells.map((c) => `<${tag} class="${cellClass}">${c.trim()}</${tag}>`).join('')}</tr>`;
            };

            const headerHtml = parseRow(lines[0], true);
            const bodyRowsHtml = lines.slice(2).map((r) => parseRow(r, false)).join('');

            return `<div class="overflow-x-auto my-6 border border-border rounded-lg shadow-sm"><table class="w-full border-collapse text-xs md:text-sm"><thead>${headerHtml}</thead><tbody>${bodyRowsHtml}</tbody></table></div>\n\n`;
        },
    );

    // 10. Paragraphs: split by double newlines and wrap in <p> if not inside other block elements
    const lines = html.split(/\n\n+/);
    html = lines
        .map((line) => {
            const trimmed = line.trim();

            if (!trimmed) {
                return '';
            }

            // If it starts with block-level tags or code block placeholder, don't wrap in <p>
            if (/^(<pre|<h[1-6]|<blockquote|<ul|<ol|<li|<div|<table|<hr|@@@FENCEDCODEBLOCK)/.test(trimmed)) {
                return trimmed;
            }

            return `<p class="leading-relaxed text-muted-foreground mb-4">${trimmed}</p>`;
        })
        .filter(Boolean)
        .join('');

    // Restore inline code blocks
    inlineCodeBlocks.forEach((blockHtml, idx) => {
        const placeholderRegex = new RegExp(
            `@@@INLINECODE${idx}@@@`,
            'g',
        );
        html = html.replace(placeholderRegex, blockHtml);
    });

    // Restore fenced code blocks
    codeBlocks.forEach((blockHtml, idx) => {
        const placeholderRegex = new RegExp(
            `@@@FENCEDCODEBLOCK${idx}@@@`,
            'g',
        );
        html = html.replace(placeholderRegex, blockHtml);
    });

    return html;
}
