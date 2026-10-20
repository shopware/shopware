/**
 * @sw-package framework
 */

import { compile } from '@vue/compiler-dom';
import { normalizeCrossBlockConditionals } from './normalize-cross-block-conditionals';

const GUARD_COMMENT = '<!-- Keeps the conditional chain connected across sw-block. -->';

const markup = (...lines: string[]): string => lines.join('\n');

function compilerErrors(template: string): string[] {
    const errors: string[] = [];

    compile(template, { onError: (error) => errors.push(error.message) });

    return errors;
}

/** Normalizes and asserts the outcome is markup Vue accepts — every guard has to earn that. */
function normalize(source: string): string {
    const result = normalizeCrossBlockConditionals(source);

    expect(result.blockers).toEqual([]);
    expect(typeof result.template).toBe('string');
    expect(compilerErrors(result.template as string)).toEqual([]);

    return result.template as string;
}

describe('scripts/codemods/sfc-migration/normalize-cross-block-conditionals', () => {
    it('inserts a guard before a v-else that lost its v-if to a block boundary', () => {
        const result = normalize(
            markup(
                '<sw-block name="sw_first">',
                '    <div v-if="active">On</div>',
                '</sw-block>',
                '<sw-block name="sw_second">',
                '    <div v-else>Off</div>',
                '</sw-block>',
            ),
        );

        expect(result).toBe(
            markup(
                '<sw-block name="sw_first">',
                '    <div v-if="active">On</div>',
                '</sw-block>',
                '<sw-block name="sw_second">',
                `    <template v-if="(active)">${GUARD_COMMENT}</template>`,
                '    <div v-else>Off</div>',
                '</sw-block>',
            ),
        );
    });

    it('collects every previous branch of a chain spread over three blocks', () => {
        const result = normalize(
            markup(
                '<sw-block name="sw_first">',
                '    <div v-if="first">first</div>',
                '</sw-block>',
                '<sw-block name="sw_second">',
                '    <div v-else-if="second">second</div>',
                '</sw-block>',
                '<sw-block name="sw_third">',
                '    <div v-else>fallback</div>',
                '</sw-block>',
            ),
        );

        expect(result).toContain(`<template v-if="(first)">${GUARD_COMMENT}</template>`);
        expect(result).toContain(`<template v-if="(first) || (second)">${GUARD_COMMENT}</template>`);
        expect(result.match(/<template v-if=/g)).toHaveLength(2);
    });

    it('keeps a chain open across an empty extension-point block', () => {
        const result = normalize(
            markup(
                '<div v-if="loading">loading</div>',
                '<sw-block name="sw_extension_point"></sw-block>',
                '<div v-else-if="empty">empty</div>',
                '<div v-else>content</div>',
            ),
        );

        // Every branch after the block boundary gets its own guard — redundant once the first one
        // restored adjacency, but each is independently correct and none is dropped.
        expect(result).toContain(`<template v-if="(loading)">${GUARD_COMMENT}</template>`);
        expect(result).toContain(`<template v-if="(loading) || (empty)">${GUARD_COMMENT}</template>`);
    });

    it('guards the continuation once when the following block carries the rest of the chain', () => {
        const result = normalize(
            markup(
                '<sw-block name="sw_first">',
                '    <div v-if="first">first</div>',
                '</sw-block>',
                '<sw-block name="sw_second">',
                '    <div v-else-if="second">second</div>',
                '    <div v-else>fallback</div>',
                '</sw-block>',
            ),
        );

        expect(result).toContain(`<template v-if="(first)">${GUARD_COMMENT}</template>`);
        expect(result.match(/<template v-if=/g)).toHaveLength(1);
    });

    it('carries the branches a block opened itself into the guard of the next block', () => {
        const result = normalize(
            markup(
                '<sw-block name="sw_first">',
                '    <div>unrelated</div>',
                '    <div v-if="first">first</div>',
                '    <div v-else-if="second">second</div>',
                '</sw-block>',
                '<sw-block name="sw_second">',
                '    <div v-else-if="third">third</div>',
                '    <div v-else>fallback</div>',
                '</sw-block>',
            ),
        );

        expect(result).toContain(`<template v-if="(first) || (second)">${GUARD_COMMENT}</template>`);
        expect(result.match(/<template v-if=/g)).toHaveLength(1);
    });

    it('walks into nested wrappers instead of only looking at root siblings', () => {
        const result = normalize(
            markup(
                '<section class="wrapper">',
                '    <sw-block name="sw_first">',
                '        <span v-if="visible">visible</span>',
                '    </sw-block>',
                '    <sw-block name="sw_second">',
                '        <span v-else>hidden</span>',
                '    </sw-block>',
                '</section>',
            ),
        );

        expect(result).toContain(`        <template v-if="(visible)">${GUARD_COMMENT}</template>\n        <span v-else>`);
    });

    it('escapes double quotes so the guard stays a valid attribute value', () => {
        const result = normalize(
            markup(
                '<sw-block name="sw_first">',
                `    <div v-if="name === 'foo&quot;bar'">quoted</div>`,
                '</sw-block>',
                '<sw-block name="sw_second">',
                '    <div v-else>fallback</div>',
                '</sw-block>',
            ),
        );

        expect(result).toContain(`<template v-if="(name === 'foo&quot;bar')">${GUARD_COMMENT}</template>`);
    });

    it('leaves a chain that stayed inside one block untouched', () => {
        const source = markup(
            '<sw-block name="sw_first">',
            '    <div v-if="active">On</div>',
            '    <div v-else>Off</div>',
            '</sw-block>',
        );

        expect(normalize(source)).toBe(source);
    });

    it('leaves blocks without conditionals untouched', () => {
        const source = markup(
            '<sw-block name="sw_card">',
            '    <div class="card">',
            '        <sw-block name="sw_card_header">',
            '            <h3>{{ title }}</h3>',
            '        </sw-block>',
            '    </div>',
            '</sw-block>',
        );

        expect(normalize(source)).toBe(source);
    });

    it('ignores comments between the branches, they never break adjacency', () => {
        const result = normalize(
            markup(
                '<sw-block name="sw_first">',
                '    <div v-if="active">On</div>',
                '</sw-block>',
                '<!-- an explanation -->',
                '<sw-block name="sw_second">',
                '    <div v-else>Off</div>',
                '</sw-block>',
            ),
        );

        expect(result).toContain(`<template v-if="(active)">${GUARD_COMMENT}</template>`);
    });

    it('reports the orphan when text between the branches broke the chain', () => {
        const result = normalizeCrossBlockConditionals(
            markup(
                '<sw-block name="sw_first">',
                '    <div v-if="active">On</div>',
                '</sw-block>',
                'stray text',
                '<sw-block name="sw_second">',
                '    <div v-else>Off</div>',
                '</sw-block>',
            ),
        );

        expect(result).toEqual({
            template: null,
            blockers: ['orphaned cross-block v-else (no preceding v-if)'],
        });
    });

    it('reports the orphan when nothing before the continuation opens a chain', () => {
        const result = normalizeCrossBlockConditionals(
            markup('<sw-block name="sw_first">', '    <div v-else>fallback</div>', '</sw-block>'),
        );

        expect(result).toEqual({
            template: null,
            blockers: ['orphaned cross-block v-else (no preceding v-if)'],
        });
    });

    it('rewrites nothing around a sw-block this codemod never emitted', () => {
        const source = markup(
            '<div v-if="active">On</div>',
            '<sw-block :name="dynamicName">',
            '    <div>content</div>',
            '</sw-block>',
        );

        expect(normalizeCrossBlockConditionals(source)).toEqual({ template: source, blockers: [] });
    });

    it('leaves markup Vue cannot parse to the validation gate', () => {
        const source = markup('<sw-block name="sw_first">', '    <div>unclosed', '</sw-block>');

        expect(normalizeCrossBlockConditionals(source)).toEqual({ template: source, blockers: [] });
    });
});
