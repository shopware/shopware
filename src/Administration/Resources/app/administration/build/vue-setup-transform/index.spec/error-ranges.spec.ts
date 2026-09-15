/**
 * @sw-package framework
 */

import { captureTransformError } from './helpers';

describe('build/vue-setup-transform diagnostic ranges', () => {
    it.each([
        {
            name: 'top-level await',
            script: 'const value = await load();',
            token: 'await load()',
            column: 14,
            message: 'Top-level await',
        },
        {
            name: 'runtime export',
            script: 'export const value = 1;',
            token: 'export const value = 1;',
            column: 0,
            message: 'ES module exports',
        },
        {
            name: 'default export',
            script: 'export default {};',
            token: 'export default {};',
            column: 0,
            message: 'ES module exports',
        },
        {
            name: 'for-await loop',
            script: 'for await (const item of items) {}',
            token: 'for await (const item of items) {}',
            column: 0,
            message: 'Top-level await',
        },
        {
            name: 'unsupported macro',
            script: 'defineModel();',
            token: 'defineModel()',
            column: 0,
            message: 'defineModel() is not supported',
        },
        {
            name: 'wrong-mode marker',
            script: 'swDefineOverride({});',
            token: 'swDefineOverride({})',
            column: 0,
            message: 'compile-time macro for override components',
        },
        {
            name: 'nested wrong-mode helper',
            script: 'const fn = () => useSwProps();',
            token: 'useSwProps()',
            column: 17,
            message: 'only supported in override',
        },
        {
            name: 'assigned marker',
            script: 'const result = swDefinePublic({});',
            token: 'swDefinePublic({})',
            column: 15,
            message: 'returns nothing',
        },
        {
            name: 'missing argument',
            script: 'swDefinePublic();',
            token: 'swDefinePublic()',
            column: 0,
            message: 'exactly one object-literal argument',
        },
        {
            name: 'spread entry',
            script: 'swDefinePublic({ ...values });',
            token: '...values',
            column: 17,
            message: 'Spread properties',
        },
        {
            name: 'renamed entry',
            script: 'swDefinePublic({ alias: value });',
            token: 'alias: value',
            column: 17,
            message: 'only supports shorthand',
        },
        {
            name: 'method entry',
            script: 'swDefinePublic({ method() {} });',
            token: 'method() {}',
            column: 17,
            message: 'only supports plain object properties',
        },
        {
            name: 'reserved helper',
            script: 'const useSwProps = () => ({}); swDefinePublic({});',
            token: 'useSwProps',
            column: 6,
            message: 'must not be declared or imported',
        },
        {
            name: 'reserved prefix',
            script: 'const __swSetupValue = 1; swDefinePublic({});',
            token: '__swSetupValue',
            column: 6,
            message: 'reserved "__swSetup" prefix',
        },
        {
            name: 'prototype binding',
            script: 'const __proto__ = 1; swDefinePublic({});',
            token: '__proto__',
            column: 6,
            message: 'prototype-setter syntax',
        },
    ])('locates $name at its full source range', ({ script, token, column, message }) => {
        const source = `<template><div /></template>\n<script setup>\n${script}\n</script>`;
        const error = captureTransformError(source, 'sw-range.vue');

        expect(error.message).toContain(message);
        expect(error.loc).toEqual({ file: 'sw-range.vue', line: 3, column });
        expect(error.index).toBe(source.indexOf(token));
        expect(error.endIndex).toBe(source.indexOf(token) + token.length);
        expect(error.frame).toContain(`3  |  ${script}\n   |  ${' '.repeat(column)}${'^'.repeat(token.length)}`);
        // Column-zero ranges must not also underline the previous line's newline.
        expect(error.frame?.split('\n').filter((line) => line.startsWith('   |'))).toHaveLength(1);
    });

    it.each([
        {
            name: 'marker call',
            script: 'swDefinePublic({});\nswDefinePublic({});',
            token: 'swDefinePublic({})',
            column: 0,
            message: 'Only one swDefinePublic()',
        },
        {
            name: 'public entry',
            script: 'const count = 1;\nswDefinePublic({ count, count });',
            token: 'count',
            column: 24,
            message: 'Duplicate public',
        },
        {
            name: 'runtime binding',
            script: 'var count = 1;\nvar count = 2;\nswDefinePublic({});',
            token: 'count',
            column: 4,
            message: 'Duplicate top-level',
        },
    ])('points at the second $name, not the first', ({ script, token, column, message }) => {
        const source = `<template><div /></template>\n<script setup>\n${script}\n</script>`;
        const error = captureTransformError(source, 'sw-duplicate.vue');

        expect(error.message).toContain(message);
        expect(error.loc).toEqual({ file: 'sw-duplicate.vue', line: 4, column });
        expect(error.index).toBe(source.lastIndexOf(token));
        expect(error.endIndex).toBe(source.lastIndexOf(token) + token.length);
        expect(error.frame?.split('\n').filter((line) => line.startsWith('   |'))).toHaveLength(1);
    });

    it.each([
        { name: 'default import', script: "import Shopware from 'library';", token: 'Shopware', line: 3, column: 7 },
        { name: 'namespace import', script: "import * as Shopware from 'library';", token: 'Shopware', line: 3, column: 12 },
        {
            name: 'multiline aliased import',
            script: "import {\n    ref,\n    computed as Shopware,\n} from 'vue';",
            token: 'Shopware',
            line: 5,
            column: 16,
        },
    ])('locates the reserved name in a $name', ({ script, token, line, column }) => {
        const source = `<template><div /></template>\n<script setup>\n${script}\nswDefinePublic({});\n</script>`;
        const error = captureTransformError(source, 'sw-import.vue');

        expect(error.message).toContain('"Shopware" is reserved');
        expect(error.loc).toEqual({ file: 'sw-import.vue', line, column });
        expect(error.index).toBe(source.indexOf(token));
        expect(error.endIndex).toBe(source.indexOf(token) + token.length);
    });
});
