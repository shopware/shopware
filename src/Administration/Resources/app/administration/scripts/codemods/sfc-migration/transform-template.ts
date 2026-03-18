const BLOCK_START_REGEX = /\{%\s*block\s+([^%\s}]+)\s*%\}/g;
const BLOCK_END_REGEX = /\{%\s*endblock\s*%\}/g;
const BLOCK_PARENT_REGEX = /\{[{%]\s*parent\(?\)?\s*[%}]\}/g;
const BLOCK_EXTENDS_REGEX = /\{%\s*extends\s+'[^']+'\s*%\}/g;
const ESLINT_DISABLE_COMMENT = '<!-- eslint-disable-next-line sw-deprecation-rules/no-twigjs-blocks -->';

/**
 * Replaces TwigJS block syntax with `<sw-block>` / `<sw-block-parent/>` Vue components.
 *
 * Returns `null` when the input contains no twig block tags, signalling that no
 * transformation is needed and the original content can be used as-is.
 */
export function replaceBlocks(code: string): string | null {
    if (!BLOCK_START_REGEX.test(code)) {
        return null;
    }

    // Reset lastIndex after the test() call that consumed it
    BLOCK_START_REGEX.lastIndex = 0;

    return code
        .split('\n')
        .filter((line) => !BLOCK_EXTENDS_REGEX.test(line))
        .filter((line) => line.trim() !== ESLINT_DISABLE_COMMENT)
        .map((line) => line.replace(ESLINT_DISABLE_COMMENT, ''))
        .map((line) => line.replace(BLOCK_START_REGEX, '<sw-block name="$1" :data="$dataScope">'))
        .map((line) => line.replace(BLOCK_END_REGEX, '</sw-block>'))
        .map((line) => line.replace(BLOCK_PARENT_REGEX, '<sw-block-parent/>'))
        .join('\n');
}

/**
 * Wraps a template string in a `<template>` tag suitable for a Vue SFC.
 */
export function wrapInTemplate(content: string): string {
    return `<template>\n${content}\n</template>`;
}
