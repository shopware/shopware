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
const UNSUPPORTED_EXTENDS_ERROR = 'Twig extends is not supported by the SFC migration codemod.';

function isTwigBlockMigrationLine(line: string): boolean {
    return BLOCK_START_LINE_RE.test(line) || BLOCK_END_LINE_RE.test(line) || PARENT_LINE_RE.test(line);
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
export function transformTemplate(twigContent: string): { template: string; useDataScope: boolean } {
    const BLOCK_START_RE = /\{%\s*block\s+([^%\s}]+)\s*%\}/g;
    const BLOCK_END_RE = /\{%\s*endblock(?:\s+\w+)?\s*%\}/g;
    const PARENT_RE = /\{[{%]\s*parent\(?\)?\s*[%}]\}/g;

    const hasTwigBlocks = BLOCK_START_LINE_RE.test(twigContent);

    if (EXTENDS_RE.test(twigContent)) {
        throw new Error(UNSUPPORTED_EXTENDS_ERROR);
    }

    let body = twigContent;

    // Convert Twig comments to HTML comments regardless of block usage
    body = body.replace(TWIG_COMMENT_RE, (_, content) => `<!--${content}-->`);

    const cleanedLines = body.split('\n').filter((line, index, lines) => {
        const trimmed = line.trim();
        const nextLine = lines[index + 1] ?? '';
        const previousLine = lines[index - 1] ?? '';

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

    const transformed = `<template>\n${body}\n</template>`;
    const useDataScope = transformed.includes('$dataScope');
    return { template: transformed, useDataScope };
}
