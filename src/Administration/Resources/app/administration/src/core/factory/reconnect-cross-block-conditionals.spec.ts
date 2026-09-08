/**
 * @sw-package framework
 */

import { compile } from '@vue/compiler-dom';
import {
    normalizeCrossBlockConditionals,
    reconnectCrossBlockConditionals,
} from 'src/core/factory/reconnect-cross-block-conditionals';

const GUARD_COMMENT = '<!-- Keeps the conditional chain connected across sw-block. -->';

const markup = (...lines: string[]): string => lines.join('\n');

/** The wrapper the template factory puts around a natively overridden Twig block. */
const wrap = (name: string, inner: string): string =>
    `<sw-block name="${name}" :data="$dataScope" :sw-internal-legacy-shim="false">${inner}</sw-block>`;

function compilerErrors(template: string): string[] {
    const errors: string[] = [];

    compile(template, { onError: (error) => errors.push(error.message) });

    return errors;
}

describe('core/factory/reconnect-cross-block-conditionals.ts', () => {
    describe('normalizeCrossBlockConditionals()', () => {
        /** Normalizes and asserts the outcome is markup Vue accepts. */
        function normalize(source: string): string {
            const result = normalizeCrossBlockConditionals(source);

            expect(result.blockers).toEqual([]);
            expect(typeof result.template).toBe('string');
            expect(compilerErrors(result.template as string)).toEqual([]);

            return result.template as string;
        }

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
                    '<sw-block name="sw_first"><div v-if="first">first</div></sw-block>',
                    '<sw-block name="sw_second"><div v-else-if="second">second</div></sw-block>',
                    '<sw-block name="sw_third"><div v-else>fallback</div></sw-block>',
                ),
            );

            expect(result).toContain(`<template v-if="(first)">${GUARD_COMMENT}</template>`);
            expect(result).toContain(`<template v-if="(first) || (second)">${GUARD_COMMENT}</template>`);
            expect(result.match(/<template v-if=/g)).toHaveLength(2);
        });

        it('keeps a chain open across an empty extension-point block', () => {
            const result = normalize(
                markup('<div v-if="loading">loading</div>', '<sw-block name="sw_gap"></sw-block>', '<div v-else>done</div>'),
            );

            expect(result).toContain(`<template v-if="(loading)">${GUARD_COMMENT}</template>\n<div v-else>done</div>`);
        });

        it('carries the branches a block opened itself into the guard of the next block', () => {
            const result = normalize(
                markup(
                    '<sw-block name="sw_first"><div v-if="first">first</div></sw-block>',
                    '<sw-block name="sw_second"><div v-else-if="second">second</div><div v-else-if="third">third</div></sw-block>',
                    '<sw-block name="sw_third"><div v-else>fallback</div></sw-block>',
                ),
            );

            expect(result).toContain(`<template v-if="(first) || (second) || (third)">${GUARD_COMMENT}</template>`);
        });

        it('walks into nested wrappers instead of only looking at root siblings', () => {
            const result = normalize(
                markup(
                    '<section>',
                    '    <sw-block name="sw_first"><p v-if="x">a</p></sw-block>',
                    '    <sw-block name="sw_second"><p v-else>b</p></sw-block>',
                    '</section>',
                ),
            );

            expect(result).toContain(`<template v-if="(x)">${GUARD_COMMENT}</template>`);
        });

        it('escapes double quotes so the guard stays a valid attribute value', () => {
            const result = normalize(
                markup(
                    '<sw-block name="sw_first"><p v-if="mode === &quot;x&quot;">a</p></sw-block>',
                    '<sw-block name="sw_second"><p v-else>b</p></sw-block>',
                ),
            );

            expect(result).toContain('<template v-if="(mode === &quot;x&quot;)">');
        });

        it.each([
            [
                'a chain that stayed inside one block',
                '<sw-block name="sw_one"><p v-if="x">a</p><p v-else>b</p></sw-block>',
            ],
            [
                'blocks without conditionals',
                '<sw-block name="sw_one"><p>a</p></sw-block><sw-block name="sw_two"><p>b</p></sw-block>',
            ],
            [
                'a sw-block whose name is bound dynamically',
                '<sw-block :name="one"><p v-if="x">a</p></sw-block><sw-block :name="two"><p v-else>b</p></sw-block>',
            ],
        ])('leaves %s untouched', (_label, source) => {
            expect(normalizeCrossBlockConditionals(source)).toEqual({ template: source, blockers: [] });
        });

        it('ignores comments between the branches, they never break adjacency', () => {
            const result = normalize(
                markup(
                    '<sw-block name="sw_first"><p v-if="x">a</p></sw-block>',
                    '<!-- a comment -->',
                    '<sw-block name="sw_second"><p v-else>b</p></sw-block>',
                ),
            );

            expect(result).toContain(`<template v-if="(x)">${GUARD_COMMENT}</template>`);
        });

        it.each([
            [
                'text between the branches broke the chain',
                markup(
                    '<sw-block name="sw_first"><p v-if="x">a</p></sw-block>',
                    'text',
                    '<sw-block name="sw_second"><p v-else>b</p></sw-block>',
                ),
            ],
            [
                'nothing before the continuation opens a chain',
                '<sw-block name="sw_second"><p v-else>b</p></sw-block>',
            ],
        ])('reports the orphan when %s', (_label, source) => {
            expect(normalizeCrossBlockConditionals(source)).toEqual({
                template: null,
                blockers: ['orphaned cross-block v-else (no preceding v-if)'],
            });
        });

        it('leaves markup Vue cannot parse to the compiler', () => {
            const source = '<div v-if="x"><span></div>';

            expect(normalizeCrossBlockConditionals(source)).toEqual({ template: source, blockers: [] });
        });

        it('accepts every condition when no safety check is passed', () => {
            const result = normalize(`<p v-if="acl.can('x')">a</p>${wrap('sw_second', '<p v-else>b</p>')}`);

            expect(result).toContain(`<template v-if="(acl.can('x'))">${GUARD_COMMENT}</template>`);
        });

        it('refuses a guard when the passed safety check rejects a condition', () => {
            const isSafeCondition = jest.fn((expression: string) => !expression.includes('('));

            const result = normalizeCrossBlockConditionals(
                `<p v-if="acl.can('x')">a</p>${wrap('sw_second', '<p v-else>b</p>')}`,
                { isSafeCondition },
            );

            expect(isSafeCondition).toHaveBeenCalledWith("acl.can('x')");
            expect(result).toEqual({
                template: null,
                blockers: ['cross-block conditional contains a side-effecting expression'],
            });
        });
    });

    describe('reconnectCrossBlockConditionals()', () => {
        let consoleWarn: jest.SpyInstance;

        beforeEach(() => {
            consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});
        });

        afterEach(() => {
            consoleWarn.mockRestore();
        });

        it.each([
            [
                'no extension point',
                '<div><p v-if="a">1</p><p v-else>2</p></div>',
            ],
            [
                'no chain continuation',
                `<div>${wrap('sw_one', '<p v-if="a">1</p>')}<p>2</p></div>`,
            ],
        ])('hands the template back untouched when it has %s', (_label, html) => {
            // The same instance, so callers can compare by identity and skip further work.
            expect(reconnectCrossBlockConditionals(html, 'sw-test')).toBe(html);
        });

        it('guards a v-else inside an extension point whose v-if is plain markup before it', () => {
            const result = reconnectCrossBlockConditionals(
                `<div><p v-if="c1">one</p>${wrap('sw_two', '<p v-else>two</p>')}</div>`,
                'sw-test',
            );

            expect(result).toBe(
                `<div><p v-if="c1">one</p>${wrap('sw_two', `<template v-if="(c1)">${GUARD_COMMENT}</template>\n<p v-else>two</p>`)}</div>`,
            );
            expect(compilerErrors(result)).toEqual([]);
        });

        it('guards a plain v-else whose v-if sits inside an extension point before it', () => {
            const result = reconnectCrossBlockConditionals(
                `<div>${wrap('sw_one', '<p v-if="c1">one</p>')}<p v-else>two</p></div>`,
                'sw-test',
            );

            expect(result).toBe(
                `<div>${wrap('sw_one', '<p v-if="c1">one</p>')}<template v-if="(c1)">${GUARD_COMMENT}</template>\n<p v-else>two</p></div>`,
            );
            expect(compilerErrors(result)).toEqual([]);
        });

        it('guards both sides of an extension point in the middle of a chain', () => {
            const result = reconnectCrossBlockConditionals(
                `<div><p v-if="c1">one</p>${wrap('sw_two', '<p v-else-if="c2">two</p>')}<p v-else>three</p></div>`,
                'sw-test',
            );

            expect(result).toContain(`<template v-if="(c1)">${GUARD_COMMENT}</template>\n<p v-else-if="c2">two</p>`);
            expect(result).toContain(`<template v-if="(c1) || (c2)">${GUARD_COMMENT}</template>\n<p v-else>three</p>`);
            expect(compilerErrors(result)).toEqual([]);
        });

        it('accepts a condition with a call expression, the runtime cannot refuse a template', () => {
            const result = reconnectCrossBlockConditionals(
                `<div><p v-if="acl.can('x')">one</p>${wrap('sw_two', '<p v-else>two</p>')}</div>`,
                'sw-test',
            );

            expect(result).toContain(`<template v-if="(acl.can('x'))">`);
            expect(compilerErrors(result)).toEqual([]);
            expect(consoleWarn).not.toHaveBeenCalled();
        });

        it('changes nothing on a second pass over already guarded markup', () => {
            const once = reconnectCrossBlockConditionals(
                `<div><p v-if="c1">one</p>${wrap('sw_two', '<p v-else>two</p>')}</div>`,
                'sw-test',
            );

            expect(reconnectCrossBlockConditionals(once, 'sw-test')).toBe(once);
        });

        it('warns and leaves the template unchanged when the continuation has no preceding v-if', () => {
            const html = `<div>${wrap('sw_two', '<p v-else>two</p>')}</div>`;

            expect(reconnectCrossBlockConditionals(html, 'sw-orphan')).toBe(html);
            expect(consoleWarn).toHaveBeenCalledTimes(1);
            expect(consoleWarn).toHaveBeenCalledWith('[TemplateFactory]', expect.stringContaining('"sw-orphan"'));
            expect(consoleWarn).toHaveBeenCalledWith(
                '[TemplateFactory]',
                expect.stringContaining('orphaned cross-block v-else'),
            );
        });
    });
});
