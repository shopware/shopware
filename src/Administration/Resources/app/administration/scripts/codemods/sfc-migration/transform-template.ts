/**
 * Twig is a line-oriented text format, not a language ts-morph understands.
 * Regex is the right tool here: every pattern (block tags, endblock, parent())
 * is a single fixed token that never nests inside JS expressions.
 */

const EXTENDS_RE = /\{%\s*extends\b[\s\S]*?%\}/;
const TWIG_COMMENT_RE = /\{#([\s\S]*?)#\}/g;
const ESLINT_DISABLE_TWIG = '<!-- eslint-disable-next-line sw-deprecation-rules/no-twigjs-blocks -->';
const BLOCK_START_LINE_RE = /\{%\s*block\s+([^%\s}]+)\s*%\}/;
const BLOCK_END_LINE_RE = /\{%\s*endblock(?:\s+\w+)?\s*%\}/;
const PARENT_LINE_RE = /\{[{%]\s*parent\(?\)?\s*[%}]\}/;
const TWIG_SYNTAX_RE = /\{%\s*(?:block|endblock|extends)\b|\{[{%]\s*parent\(?\)?\s*[%}]\}/;
const UNSUPPORTED_TEMPLATE_ERROR = 'Twig template is not supported by the SFC migration codemod.';
const UNSUPPORTED_EXTENDS_ERROR = 'Twig extends is not supported by the SFC migration codemod.';
const UNSUPPORTED_EXTENDS_BLOCKER = 'twig extends';
const UNSUPPORTED_COMMENT_SYNTAX_BLOCKER = 'twig syntax inside comment';

export class TemplateTransformError extends Error {
    public constructor(
        public readonly blockers: string[],
        message = UNSUPPORTED_TEMPLATE_ERROR,
    ) {
        super(message);
        this.name = 'TemplateTransformError';
    }
}

function isTwigBlockMigrationLine(line: string): boolean {
    return BLOCK_START_LINE_RE.test(line) || BLOCK_END_LINE_RE.test(line) || PARENT_LINE_RE.test(line);
}

function hasTwigSyntaxInComment(twigContent: string): boolean {
    TWIG_COMMENT_RE.lastIndex = 0;

    // Comments are converted before block replacements. A commented-out Twig
    // block would otherwise become an HTML comment and then be migrated into
    // real <sw-block> markup inside that comment.
    return Array.from(twigContent.matchAll(TWIG_COMMENT_RE)).some((match) => TWIG_SYNTAX_RE.test(match[1] ?? ''));
}

/**
 * Converts a `.html.twig` file's content into a Vue `<template>` block.
 *
 * - `{% block name %}` → `<sw-block name="name" :data="$dataScope">`
 * - `{% endblock %}`  → `</sw-block>`
 * - `{{ parent() }}`  → `<sw-block-parent/>`
 * - `{% extends '…' %}` throws because template inheritance is unsupported
 * - Accompanying eslint-disable-next-line comments are removed
 * - Plain HTML / Vue expressions pass through unchanged
 */
export function transformTemplate(twigContent: string): { template: string } {
    const BLOCK_START_RE = /\{%\s*block\s+([^%\s}]+)\s*%\}/g;
    const BLOCK_END_RE = /\{%\s*endblock(?:\s+\w+)?\s*%\}/g;
    const PARENT_RE = /\{[{%]\s*parent\(?\)?\s*[%}]\}/g;

    const hasTwigBlocks = BLOCK_START_LINE_RE.test(twigContent);

    if (hasTwigSyntaxInComment(twigContent)) {
        throw new TemplateTransformError([UNSUPPORTED_COMMENT_SYNTAX_BLOCKER]);
    }

    if (EXTENDS_RE.test(twigContent)) {
        // Resolving Twig inheritance would require loading parent templates and
        // merging block overrides. This codemod only transforms one component
        // directory at a time, so inherited templates need manual migration.
        throw new TemplateTransformError([UNSUPPORTED_EXTENDS_BLOCKER], UNSUPPORTED_EXTENDS_ERROR);
    }

    let body = twigContent;

    // Convert Twig comments to HTML comments regardless of block usage
    body = body.replace(TWIG_COMMENT_RE, (_, content) => `<!--${content}-->`);

    const cleanedLines = body.split('\n').filter((line, index, lines) => {
        const trimmed = line.trim();
        const nextLine = lines[index + 1] ?? '';
        const previousLine = lines[index - 1] ?? '';

        // These disables targeted Twig syntax that no longer exists after
        // migration; keeping them would suppress linting for the next Vue line.
        if (
            trimmed === ESLINT_DISABLE_TWIG &&
            (isTwigBlockMigrationLine(nextLine) || isTwigBlockMigrationLine(previousLine))
        ) {
            return false;
        }

        return true;
    });

    body = cleanedLines.join('\n');

    if (hasTwigBlocks) {
        body = body
            .split('\n')
            .map((line) => line.replace(BLOCK_START_RE, '<sw-block name="$1" :data="$dataScope">'))
            .map((line) => line.replace(BLOCK_END_RE, '</sw-block>'))
            .map((line) => line.replace(PARENT_RE, '<sw-block-parent/>'))
            .join('\n');
    }
    // TODO: Silent ignore: `{{ parent() }}` outside a detected Twig block is
    // left as a Vue method call, which is syntactically valid but loses Twig
    // parent-content semantics.

    const transformed = `<template>\n${body}\n</template>`;
    return { template: transformed };
}
