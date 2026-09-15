/**
 * @sw-package framework
 */

import { captureTransformError, stripIndent } from './helpers';

describe('build/vue-setup-transform error locations', () => {
    it.each([
        '\n',
        '\r\n',
    ])('reports script syntax errors in original SFC coordinates with %j line endings', (newline) => {
        const source = stripIndent`
            <template>
                <div />
            </template>
            <script setup lang="ts">
            const broken = { a: 1 b: 2 };
            swDefinePublic({});
            </script>
        `.replaceAll('\n', newline);

        const error = captureTransformError(source, 'sw-broken.vue');

        expect(error.message).toBe('Unable to parse Shopware setup script: Unexpected token, expected ","');
        expect(error.loc).toEqual({ file: 'sw-broken.vue', line: 5, column: 22 });
        expect(error.index).toBe(source.indexOf('b: 2'));
        expect(error.frame).toContain('5  |  const broken = { a: 1 b: 2 };');
        expect(error.frame).toContain(`   |  ${' '.repeat(22)}^`);
    });

    it('resolves errors thrown before a script block is selected', () => {
        const error = captureTransformError('<template><div /></template>', 'sw-no-script.vue');

        expect(error.index).toBe(0);
        expect(error.loc).toEqual({ file: 'sw-no-script.vue', line: 1, column: 0 });
        expect(error.frame).toContain('1  |  <template><div /></template>');
    });

    it('resolves template diagnostics against the template rather than the script', () => {
        const source = stripIndent`
            <script setup>
            swDefinePublic({});
            </script>
            <template>
                <sw-block extends="example" />
            </template>
        `;

        const error = captureTransformError(source, 'sw-template-error.vue');

        expect(error.loc).toEqual({ file: 'sw-template-error.vue', line: 5, column: 14 });
        expect(error.frame).toContain('<sw-block extends="example" />');
    });

    it.each([
        { name: 'unknown entry', script: 'swDefinePublic({ missing });', token: 'missing', column: 17 },
        { name: 'unterminated string', script: "const text = 'broken;", token: "'broken", column: 13 },
    ])('keeps $name locations correct with a long preamble', ({ script, token, column }) => {
        const source = `<template>\n${'    <div />\n'.repeat(1000)}</template>\n<script setup>\n${script}\n</script>`;
        const error = captureTransformError(source, 'sw-long.vue');

        expect(error.loc).toEqual({ file: 'sw-long.vue', line: 1004, column });
        expect(error.index).toBe(source.indexOf(token));
        expect(error.frame).toContain(`1004|  ${script}`);
    });

    it('underlines a multiline range through its exclusive end', () => {
        const source = stripIndent`
            <template><div /></template>
            <script setup>
            const value = await load(
                'value'
            );
            swDefinePublic({});
            </script>
        `;
        const error = captureTransformError(source, 'sw-multiline.vue');

        expect(error.loc).toEqual({ file: 'sw-multiline.vue', line: 3, column: 14 });
        expect(error.index).toBe(source.indexOf('await'));
        expect(error.endIndex).toBe(source.indexOf(');') + 1);
        expect(error.frame).toContain(
            "3  |  const value = await load(\n   |                ^^^^^^^^^^^\n4  |      'value'\n   |  ^^^^^^^^^^^\n5  |  );\n   |  ^",
        );
    });

    it.each([
        '\n',
        '\r\n',
    ])('locates end-of-script syntax errors with %j line endings', (newline) => {
        const source = [
            '<template><div /></template>',
            '<script setup>',
            'const value =',
            '</script>',
        ].join(newline);
        const error = captureTransformError(source, 'sw-eof.vue');

        expect(error.message).toContain('Unexpected token');
        expect(error.index).toBe(source.indexOf('</script>'));
        expect(error.loc).toEqual({ file: 'sw-eof.vue', line: 4, column: 0 });
        expect(error.frame).toContain('4  |  </script>\n   |  ^');
        expect(error.frame?.split('\n').filter((line) => line.startsWith('   |'))).toHaveLength(1);
    });

    it('preserves tab indentation and UTF-16 offsets after an emoji', () => {
        const source =
            "<template><div>😀</div></template>\n<script setup>\n\tconst emoji = '😀'; swDefinePublic({ missing });\n</script>";
        const error = captureTransformError(source, 'sw-unicode.vue');

        expect(error.index).toBe(source.indexOf('missing'));
        expect(error.loc).toEqual({ file: 'sw-unicode.vue', line: 3, column: 38 });
        expect(error.frame).toContain(`   |  \t${' '.repeat(37)}^^^^^^^`);
    });

    it('includes the opening tag in the column for inline script bodies', () => {
        const source = '<template><div /></template>\n<script setup>swDefinePublic({ missing });</script>';
        const error = captureTransformError(source, 'sw-inline.vue');

        expect(error.loc).toEqual({ file: 'sw-inline.vue', line: 2, column: 31 });
        expect(error.index).toBe(source.indexOf('missing'));
    });

    it.each([
        { filename: 'sw-missing.vue', message: 'must declare its extension surface' },
        { filename: 'sw-missing.override.vue', message: 'must be called exactly once' },
    ])('anchors missing-marker errors to the script block in $filename', ({ filename, message }) => {
        const source = '<template><div /></template>\n<script setup>const value = 1;</script>';
        const error = captureTransformError(source, filename);

        expect(error.message).toContain(message);
        expect(error.index).toBe(source.indexOf('const value'));
        expect(error.loc).toEqual({ file: filename, line: 2, column: 14 });
    });

    it('locates a conflicting normal script before the setup block is selected', () => {
        const source =
            '<template><div /></template>\n<script>export default {};</script>\n<script setup>swDefinePublic({});</script>';
        const error = captureTransformError(source, 'sw-scripts.vue');

        expect(error.message).toContain('cannot be combined with another <script>');
        expect(error.loc).toEqual({ file: 'sw-scripts.vue', line: 2, column: 8 });
        expect(error.index).toBe(source.indexOf('export default'));
    });

    it('locates unsupported script languages at the start of their body', () => {
        const source = '<template><div /></template>\n<script setup lang="coffee">value = 1</script>';
        const error = captureTransformError(source, 'sw-language.vue');

        expect(error.message).toContain('Unsupported <script setup lang="coffee">');
        expect(error.loc).toEqual({ file: 'sw-language.vue', line: 2, column: 28 });
    });

    it.each([
        'js',
        'tsx',
    ])('reports parser errors with lang=%s', (lang) => {
        const source = `<template><div /></template>\n<script setup lang="${lang}">\nconst broken = ;\n</script>`;
        const error = captureTransformError(source, 'sw-parser.vue');

        expect(error.message).toContain('Unable to parse Shopware setup script');
        expect(error.loc).toEqual({ file: 'sw-parser.vue', line: 3, column: 15 });
        expect(error.index).toBe(source.indexOf(';'));
    });

    it.each([
        '\r',
        '\u2028',
        '\u2029',
    ])('resolves JavaScript line separators %j', (newline) => {
        const source = `<template><div /></template>\n<script setup>\nconst value = 1;${newline}const broken = ;\n</script>`;
        const error = captureTransformError(source, 'sw-separators.vue');

        expect(error.loc).toEqual({ file: 'sw-separators.vue', line: 4, column: 15 });
        expect(error.index).toBe(source.lastIndexOf(';'));
        expect(error.frame).toContain('4  |  const broken = ;');
    });

    it('preserves offsets after a BOM and a migration module script', () => {
        const source =
            '\uFEFF<script data-sfc-migration-module>export const legacy = true;</script>\n<template><div /></template>\n<script setup>\nswDefinePublic({ missing });\n</script>';
        const error = captureTransformError(source, 'sw-migrated.vue');

        expect(error.loc).toEqual({ file: 'sw-migrated.vue', line: 4, column: 17 });
        expect(error.index).toBe(source.indexOf('missing'));
        expect(error.endIndex).toBe(source.indexOf('missing') + 7);
    });

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
        {
            name: 'unknown entry',
            script: 'swDefinePublic({ missing });',
            token: 'missing',
            column: 17,
            message: 'unknown local binding "missing"',
        },
        {
            name: 'imported entry',
            script: "swDefinePublic({ helper }); import { helper } from './helper';",
            token: 'helper',
            column: 17,
            message: 'Imported binding "helper" cannot be exposed',
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
