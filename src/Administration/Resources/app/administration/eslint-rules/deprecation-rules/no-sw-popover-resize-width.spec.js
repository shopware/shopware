/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-sw-popover-resize-width');

const RESIZE_WIDTH = 'resize-width of sw-popover is removed in 6.8. Use match-reference-width instead.';
const FLOATING_UI = 'sw-popover is deprecated. Move to mt-floating-ui: v-if → :is-opened, popover-class → class; z-index has no replacement.';

const twigTodo = `{# TODO Codemod(6.8, sw-popover): ${FLOATING_UI} #}`;
const template = (...lines) => ['<template>', ...lines, '</template>'].join('\n');

new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } }).run('no-sw-popover-resize-width', rule, {
    valid: [
        {
            name: 'ignores mt-floating-ui',
            filename: 'test.html.twig',
            code: template('    <mt-floating-ui :is-opened="showList" match-reference-width />'),
        },
    ],
    invalid: [
        {
            name: 'renames resize-width and marks the move to mt-floating-ui',
            filename: 'test.html.twig',
            code: template(
                '    <sw-popover v-if="showList" :resize-width="true" :z-index="1100">',
                '        <acme-result-list />',
                '    </sw-popover>',
                '    <sw-popover resize-width popover-class="acme-popover" />',
            ),
            output: template(
                `    ${twigTodo}`,
                '    <sw-popover v-if="showList" :match-reference-width="true" :z-index="1100">',
                '        <acme-result-list />',
                '    </sw-popover>',
                `    ${twigTodo}`,
                '    <sw-popover match-reference-width popover-class="acme-popover" />',
            ),
            errors: [
                { message: FLOATING_UI },
                { message: RESIZE_WIDTH },
                { message: FLOATING_UI },
                { message: RESIZE_WIDTH },
            ],
        },
        {
            name: 'keeps the camelCase spelling and removes resize-width next to match-reference-width',
            filename: 'test.vue',
            code: template(
                '    <sw-popover :resizeWidth="fit" />',
                '    <sw-popover resize-width :match-reference-width="fit" />',
            ),
            output: template(
                `    <!-- TODO Codemod(6.8, sw-popover): ${FLOATING_UI} -->`,
                '    <sw-popover :matchReferenceWidth="fit" />',
                `    <!-- TODO Codemod(6.8, sw-popover): ${FLOATING_UI} -->`,
                '    <sw-popover :match-reference-width="fit" />',
            ),
            errors: [
                { message: FLOATING_UI },
                { message: RESIZE_WIDTH },
                { message: FLOATING_UI },
                { message: RESIZE_WIDTH },
            ],
        },
        {
            name: 'a second run only reports the marked sw-popover',
            filename: 'test.html.twig',
            code: template(`    ${twigTodo}`, '    <sw-popover v-if="showList" :match-reference-width="true" />'),
            output: null,
            errors: [{ message: FLOATING_UI }],
        },
    ],
});
