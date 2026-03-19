/**
 * Twig is a line-oriented text format, not a language ts-morph understands.
 * Regex is the right tool here: every pattern (block tags, endblock, parent())
 * is a single fixed token that never nests inside JS expressions.
 */

const BLOCK_START_RE = /\{%\s*block\s+([^%\s}]+)\s*%\}/g;
const BLOCK_END_RE = /\{%\s*endblock\s*%\}/g;
const PARENT_RE = /\{[{%]\s*parent\(?\)?\s*[%}]\}/g;
const EXTENDS_RE = /\{%\s*extends\s+'[^']+'\s*%\}/;
const TWIG_COMMENT_RE = /\{#([\s\S]*?)#\}/g;
const ESLINT_DISABLE_TWIG = '<!-- eslint-disable-next-line sw-deprecation-rules/no-twigjs-blocks -->';

/**
 * Converts a `.html.twig` file's content into a Vue `<template>` block.
 *
 * - `{% block name %}` → `<sw-block name="name" :data="$dataScope">`
 * - `{% endblock %}`  → `</sw-block>`
 * - `{{ parent() }}`  → `<sw-block-parent/>`
 * - `{% extends '…' %}` lines are removed entirely
 * - Accompanying eslint-disable-next-line comments are removed
 * - Plain HTML / Vue expressions pass through unchanged
 */
export function transformTemplate(twigContent: string): string {
    const hasTwigBlocks = BLOCK_START_RE.test(twigContent);
    BLOCK_START_RE.lastIndex = 0; // reset after .test()

    let body = twigContent;

    // Convert Twig comments to HTML comments regardless of block usage
    body = body.replace(TWIG_COMMENT_RE, (_, content) => `<!--${content}-->`);

    if (hasTwigBlocks) {
        body = body
            .split('\n')
            .filter((line) => !EXTENDS_RE.test(line))
            .filter((line) => line.trim() !== ESLINT_DISABLE_TWIG)
            .map((line) => line.replace(ESLINT_DISABLE_TWIG, ''))
            .map((line) => line.replace(BLOCK_START_RE, '<sw-block name="$1" :data="$dataScope">'))
            .map((line) => line.replace(BLOCK_END_RE, '</sw-block>'))
            .map((line) => line.replace(PARENT_RE, '<sw-block-parent/>'))
            .join('\n');
    }

    return `<template>\n${body}\n</template>`;
}
