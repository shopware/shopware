/**
 * @sw-package framework
 */

import { captureTransformError, transformOrFail } from './helpers';

function overrideSource(markup: string): string {
    return `<script setup>
let count = 0;
const form = { count: 0 };
swDefineOverride({ count, form });
</script>
<template>
<sw-block extends="example">
    ${markup}
</sw-block>
</template>`;
}

describe('build/vue-setup-transform override write locations', () => {
    it.each([
        [
            'multiple statements',
            'save(); count++',
            'count++',
        ],
        [
            'multiline handler',
            'save();\n    count++',
            'count++',
        ],
        [
            'CRLF handler',
            'save();\r\n    count = 1',
            'count =',
        ],
        [
            'prefix update',
            'save(); ++count',
            'count',
        ],
        [
            'earlier read',
            'save(count); count++',
            'count++',
        ],
        [
            'repeated writes',
            'save(); count++; count = 1',
            'count++',
        ],
        [
            'named entities',
            'ready &amp;&amp; count++',
            'count++',
        ],
        [
            'decimal entities',
            'ready &#38;&#38; count++',
            'count++',
        ],
        [
            'hex entities',
            'ready &#x26;&#x26; count++',
            'count++',
        ],
        [
            'entities without semicolons',
            'ready &amp&amp count++',
            'count++',
        ],
        [
            'ambiguous ampersand',
            "save('&ampx'); count++",
            'count++',
        ],
        [
            'encoded identifier',
            'save(); &#99;ount++',
            '&#99;ount',
        ],
        [
            'encoded newline',
            'save();&#10;count++',
            'count++',
        ],
        [
            'astral entity',
            "save('&#x1F600;'); count++",
            'count++',
        ],
        [
            'two-codepoint entity',
            "save('&NotEqualTilde;'); count++",
            'count++',
        ],
        [
            'unknown entity',
            "save('&unknown;'); count++",
            'count++',
        ],
        [
            'literal ampersand',
            "save('&'); count++",
            'count++',
        ],
    ])('locates the write: %s', (_name, expression, token) => {
        const source = overrideSource(`<button @click="${expression}" />`);
        const error = captureTransformError(source, 'sw-write.override.vue');
        const expectedIndex = source.indexOf(token, source.indexOf('<button'));
        const precedingLines = source.slice(0, expectedIndex).split(/\r\n|\r|\n/);

        expect(error.message).toContain('Cannot assign to "count"');
        expect(error.index).toBe(expectedIndex);
        expect(error.loc).toEqual({
            file: 'sw-write.override.vue',
            line: precedingLines.length,
            column: precedingLines[precedingLines.length - 1].length,
        });
        expect(error.frame).toContain('^');
    });

    it.each([
        'v-model="count"',
        'v-model.trim="count"',
        'v-model:value="count"',
        'v-model:[field]="count"',
        'v-model="  count  "',
        'v-model="(count)"',
        'v-model="count!"',
        'v-model="count as number"',
        'v-model="count satisfies number"',
        'v-model="(count as number)!"',
        'v-model="&#99;ount"',
    ])('rejects the implicit write in %s', (directive) => {
        const source = overrideSource(`<input ${directive} />`);
        const error = captureTransformError(source, 'sw-model.override.vue');
        const token = directive.includes('&#99;') ? '&#99;ount' : 'count';

        expect(error.message).toContain('Cannot assign to "count"');
        expect(error.index).toBe(source.indexOf(token, source.indexOf('<input')));
    });

    it.each([
        '<input v-model="form.count" />',
        '<input v-model="form[key]" />',
        '<input v-for="count in items" v-model="count" />',
        '<div v-for="count in items"><input v-model="count" /></div>',
        '<Widget><template #default="{ count }"><input v-model="count" /></template></Widget>',
        '<Widget><template #item="{ count }"><input v-model="count" /></template></Widget>',
        '<Widget v-slot="{ count }"><input v-model="count" /></Widget>',
    ])('preserves member writes and template scopes: %s', (markup) => {
        expect(transformOrFail(overrideSource(markup), 'sw-model.override.vue')).toBeDefined();
    });

    it('does not let a sibling template scope hide an invalid v-model', () => {
        const source = overrideSource(`<div v-for="count in items"><input v-model="count" /></div>
    <input v-model="count" />`);
        const error = captureTransformError(source, 'sw-model.override.vue');

        expect(error.message).toContain('Cannot assign to "count"');
        expect(error.index).toBe(source.lastIndexOf('count'));
    });

    it.each([
        'let count = 0;',
        'const count = useSwPreviousState();',
        'const count = useSwProps();',
        'const count = useSwContext();',
    ])('rejects v-model on private state and input aliases: %s', (declaration) => {
        const source = `<script setup>${declaration} swDefineOverride({});</script>
<template><sw-block extends="example"><input v-model="count" /></sw-block></template>`;
        const error = captureTransformError(source, 'sw-model.override.vue');

        expect(error.message).toContain('Cannot assign to "count"');
        expect(error.index).toBe(source.lastIndexOf('count'));
    });

    it('allows v-model on a base setup binding', () => {
        const source = `<script setup>let count = 0; swDefinePublic({ count });</script>
<template><sw-block name="example"><input v-model="count" /></sw-block></template>`;

        expect(transformOrFail(source, 'sw-base.vue')).toBeDefined();
    });
});
