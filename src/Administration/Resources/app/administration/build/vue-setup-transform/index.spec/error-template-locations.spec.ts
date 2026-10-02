/**
 * @sw-package framework
 */

import { captureTransformError } from './helpers';

describe('build/vue-setup-transform template diagnostic locations', () => {
    it.each([
        {
            name: 'wrong-mode identity',
            markup: '<sw-block extends="example" />',
            token: 'extends',
            message: 'only valid in an override component',
        },
        {
            name: 'generated data binding',
            markup: '<sw-block name="example" :data="{}" />',
            token: ':data',
            message: 'data binding',
        },
        { name: 'dynamic identity', markup: '<sw-block :name="name" />', token: ':name', message: 'Only a static "name"' },
        {
            name: 'unsupported attribute',
            markup: '<sw-block name="example" class="demo" />',
            token: 'class',
            message: 'Only a static "name"',
        },
        {
            name: 'object binding',
            markup: '<sw-block name="example" v-bind="props" />',
            token: 'v-bind',
            message: 'Only a static "name"',
        },
        {
            name: 'default slot',
            markup: '<sw-block name="example" #default="slot" />',
            token: '#default',
            message: 'default slot scope',
        },
        {
            name: 'nested default slot',
            markup: '<sw-block name="example"><template #default="slot"><div /></template></sw-block>',
            token: '<template',
            message: 'default slot scope',
        },
        {
            name: 'named slot',
            markup: '<sw-block name="example"><template #header><div /></template></sw-block>',
            token: '<template',
            message: 'non-default named slot',
        },
    ])('points at the $name in the original template', ({ markup, token, message }) => {
        const source = `<script setup>\nswDefinePublic({});\n</script>\n<template>\n    ${markup}\n</template>`;
        const error = captureTransformError(source, 'sw-template.vue');

        expect(error.message).toContain(message);
        expect(error.loc).toEqual({ file: 'sw-template.vue', line: 5, column: 4 + markup.indexOf(token) });
        expect(error.index).toBe(source.lastIndexOf(token));
        expect(error.frame).toContain(`5  |      ${markup}`);
    });

    it.each([
        '<div />',
        '{{ count }}',
        'plain text',
    ])('locates unsupported override content: %s', (markup) => {
        const source = `<script setup>\nconst count = 0;\nswDefineOverride({ count });\n</script>\n<template>\n${markup}\n</template>`;
        const error = captureTransformError(source, 'sw-content.override.vue');

        expect(error.message).toContain('may only contain <sw-block extends');
        expect(error.loc).toEqual({ file: 'sw-content.override.vue', line: 6, column: 0 });
        expect(error.index).toBe(source.indexOf(markup));
        expect(error.frame?.split('\n').filter((line) => line.startsWith('   |'))).toHaveLength(1);
    });
});
