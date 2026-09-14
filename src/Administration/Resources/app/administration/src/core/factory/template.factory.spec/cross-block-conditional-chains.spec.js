/**
 * @sw-package framework
 */

/**
 * Covers the case the static reconnect pass exists for: a `v-if` chain written across two Twig blocks
 * of one component, where the template factory wraps a block that became a native extension target in
 * `<sw-block>`. The wrapper splits the chain, and the guard has to put it back together inside or after
 * the wrapper - depending on which block is the target.
 *
 * The parser-level behaviour of the pass lives in reconnect-cross-block-conditionals.spec.ts; the
 * mounted runtime behaviour in async-component.factory.spec/native-extension-point.spec.js.
 */

import TemplateFactory from 'src/core/factory/template.factory';
import { registerNativeExtensionTargets } from 'src/core/factory/native-extension-targets';
import { compile } from '@vue/compiler-dom';

const WRAP_OPEN = (name) => `<sw-block name="${name}" :data="$dataScope" :sw-internal-legacy-shim="false">`;
const WRAP_CLOSE = '</sw-block>';
const GUARD = '<!-- Keeps the conditional chain connected across sw-block. -->';

const START_OPTIONS = "{ segmentCaseIndex: 0, isStartingCondition: true, renderOrderSegment: 'defaultSlot' }";
const ELSE_OPTIONS = "{ segmentCaseIndex: 1, isStartingCondition: false, renderOrderSegment: 'defaultSlot' }";

describe('core/factory/template.factory.js - v-if chains split by a native extension point', () => {
    beforeEach(() => {
        TemplateFactory.getTemplateRegistry().clear();
        TemplateFactory.getNormalizedTemplateRegistry().clear();
        TemplateFactory.disableTwigCache();
    });

    function resolve(name, template, blocks) {
        registerNativeExtensionTargets({ component: name, blocks });
        TemplateFactory.registerComponentTemplate(name, template);
        TemplateFactory.resolveTemplates();

        return TemplateFactory.getNormalizedTemplateRegistry().get(name).html;
    }

    function compilerErrors(template) {
        const errors = [];

        compile(template, { onError: (error) => errors.push(error.message) });

        return errors;
    }

    it('reconnects a v-else whose block is the only target', () => {
        const html = resolve(
            'tf-else-target',
            '<div>{% block tf_et_if %}<p v-if="c1">one</p>{% endblock %}{% block tf_et_else %}<p v-else>two</p>{% endblock %}</div>',
            ['tf_et_else'],
        );

        // The guard opens a chain inside the wrapper, which the legacy transform then rewrites as a
        // whole. Without the guard the v-else alone would be rewritten to a helper call that never
        // finds its chain and the branch would silently never render.
        expect(html).toBe(
            '<div><p v-if="c1">one</p>' +
                WRAP_OPEN('tf_et_else') +
                `<template v-if="$swLegacyBlockIf('tf_et_else:0', (c1), ${START_OPTIONS})">${GUARD}</template>\n` +
                `<p v-if="$swLegacyBlockElse('tf_et_else:0', ${ELSE_OPTIONS})">two</p>` +
                `${WRAP_CLOSE}</div>`,
        );
        expect(compilerErrors(html)).toEqual([]);
    });

    it('reconnects a v-else that follows the only target block', () => {
        const html = resolve(
            'tf-if-target',
            '<div>{% block tf_it_if %}<p v-if="c1">one</p>{% endblock %}{% block tf_it_else %}<p v-else>two</p>{% endblock %}</div>',
            ['tf_it_if'],
        );

        // Outside the wrapper the chain stays native: the guard gives the v-else its adjacent v-if
        // back. Without it Vue would reject the template with "v-else has no adjacent v-if".
        expect(html).toBe(
            '<div>' +
                WRAP_OPEN('tf_it_if') +
                `<p v-if="$swLegacyBlockIf('tf_it_if:0', c1, ${START_OPTIONS})">one</p>` +
                `${WRAP_CLOSE}<template v-if="(c1)">${GUARD}</template>\n<p v-else>two</p></div>`,
        );
        expect(compilerErrors(html)).toEqual([]);
    });

    it('reconnects the chain inside the second wrapper when both blocks are targets', () => {
        const html = resolve(
            'tf-both-targets',
            '<div>{% block tf_bt_if %}<p v-if="c1">one</p>{% endblock %}{% block tf_bt_else %}<p v-else>two</p>{% endblock %}</div>',
            [
                'tf_bt_if',
                'tf_bt_else',
            ],
        );

        // Each wrapper is its own chain for the runtime pass: the first keeps its v-if, the second
        // gets the guard so its v-else has a start to attach to.
        expect(html).toBe(
            '<div>' +
                WRAP_OPEN('tf_bt_if') +
                `<p v-if="$swLegacyBlockIf('tf_bt_if:0', c1, ${START_OPTIONS})">one</p>` +
                WRAP_CLOSE +
                WRAP_OPEN('tf_bt_else') +
                `<template v-if="$swLegacyBlockIf('tf_bt_else:0', (c1), ${START_OPTIONS})">${GUARD}</template>\n` +
                `<p v-if="$swLegacyBlockElse('tf_bt_else:0', ${ELSE_OPTIONS})">two</p>` +
                `${WRAP_CLOSE}</div>`,
        );
        expect(compilerErrors(html)).toEqual([]);
    });

    it('keeps the chain connected across an empty target block between the branches', () => {
        const html = resolve(
            'tf-gap-target',
            '<div>{% block tf_gt_if %}<p v-if="c1">one</p>{% endblock %}{% block tf_gt_gap %}{% endblock %}{% block tf_gt_else %}<p v-else>two</p>{% endblock %}</div>',
            ['tf_gt_gap'],
        );

        // An extension point with no content of its own still sits between the branches as an
        // element, so the v-else needs the guard just like after a filled wrapper.
        expect(html).toBe(
            `<div><p v-if="c1">one</p>${WRAP_OPEN('tf_gt_gap')}${WRAP_CLOSE}` +
                `<template v-if="(c1)">${GUARD}</template>\n<p v-else>two</p></div>`,
        );
        expect(compilerErrors(html)).toEqual([]);
    });

    it('guards both sides of a target block in the middle of a chain', () => {
        const html = resolve(
            'tf-middle-target',
            '<div>{% block tf_mt_if %}<p v-if="c1">1</p>{% endblock %}{% block tf_mt_else_if %}<p v-else-if="c2">2</p>{% endblock %}{% block tf_mt_else %}<p v-else>3</p>{% endblock %}</div>',
            ['tf_mt_else_if'],
        );

        expect(html).toContain(`<template v-if="$swLegacyBlockIf('tf_mt_else_if:0', (c1)`);
        expect(html).toContain(`${WRAP_CLOSE}<template v-if="(c1) || (c2)">${GUARD}</template>\n<p v-else>3</p>`);
        expect(compilerErrors(html)).toEqual([]);
    });

    it('carries every previous condition into the guard when the last block of a three-way chain is the target', () => {
        const html = resolve(
            'tf-last-target',
            '<div>{% block tf_lt_if %}<p v-if="c1">1</p>{% endblock %}{% block tf_lt_else_if %}<p v-else-if="c2">2</p>{% endblock %}{% block tf_lt_else %}<p v-else>3</p>{% endblock %}</div>',
            ['tf_lt_else'],
        );

        expect(html).toBe(
            '<div><p v-if="c1">1</p><p v-else-if="c2">2</p>' +
                WRAP_OPEN('tf_lt_else') +
                `<template v-if="$swLegacyBlockIf('tf_lt_else:0', (c1) || (c2), ${START_OPTIONS})">${GUARD}</template>\n` +
                `<p v-if="$swLegacyBlockElse('tf_lt_else:0', ${ELSE_OPTIONS})">3</p>` +
                `${WRAP_CLOSE}</div>`,
        );
        expect(compilerErrors(html)).toEqual([]);
    });

    it('leaves a chain alone when none of its blocks is a target', () => {
        expect(
            resolve(
                'tf-no-target',
                '<div>{% block tf_nt_if %}<p v-if="c1">one</p>{% endblock %}{% block tf_nt_else %}<p v-else>two</p>{% endblock %}</div>',
                [],
            ),
        ).toBe('<div><p v-if="c1">one</p><p v-else>two</p></div>');
    });
});
