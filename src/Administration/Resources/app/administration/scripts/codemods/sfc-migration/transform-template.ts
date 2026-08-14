/**
 * @sw-package framework
 */

/**
 * Converts a TwigJS component template into native setup SFC template markup.
 *
 * The Administration's twig layer only ever uses `{% block %}`, `{% parent %}` and `{# comments #}`
 * (verified over all 994 templates), so this stays a handful of text replacements. Anything else is
 * reported as a blocker instead of being guessed at. The `:data` binding of `<sw-block>` is owned by
 * the Shopware setup transform and must never be authored here.
 */

type TemplateResult = {
    template: string | null;
    blockers: string[];
};

const ESLINT_BLOCK_DISABLE =
    /[^\S\n]*<!--\s*eslint-disable(?:-next-line)?\s+sw-deprecation-rules\/no-twigjs-blocks\s*-->\n?/g;
const TWIG_COMMENT = /\{#([\s\S]*?)#\}/g;
const TWIG_BLOCK_START = /\{%-?\s*block\s+([\w-]+)\s*-?%\}/g;
const TWIG_BLOCK_END = /\{%-?\s*endblock\s*-?%\}/g;
const TWIG_PARENT = /\{\{\s*parent\(\)\s*\}\}|\{%-?\s*parent\s*-?%\}/g;

function transformTemplate(twig: string): TemplateResult {
    const template = twig
        .replace(ESLINT_BLOCK_DISABLE, '')
        .replace(TWIG_COMMENT, '<!--$1-->')
        .replace(TWIG_BLOCK_START, '<sw-block name="$1">')
        .replace(TWIG_BLOCK_END, '</sw-block>')
        .replace(TWIG_PARENT, '<sw-block-parent />');

    const leftoverTwig = template.match(/\{[%#][^\n]*/);

    if (leftoverTwig) {
        return {
            template: null,
            blockers: [`unsupported twig syntax: ${leftoverTwig[0].trim()}`],
        };
    }

    return { template, blockers: [] };
}

export { transformTemplate, type TemplateResult };
