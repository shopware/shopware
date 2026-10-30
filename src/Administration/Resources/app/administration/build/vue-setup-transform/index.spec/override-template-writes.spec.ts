/**
 * @sw-package framework
 *
 * Covers template writes to forwarded override bindings inside `<sw-block extends>` content.
 *
 * A write is rewritten like any read, so it reaches the real binding through the generated slot scope.
 * These cases pin the write shapes the rewrite has to address - statement handlers, implicit `v-model`
 * writes with modifiers, arguments and TypeScript wrappers - and the template scopes it must leave alone.
 */

import { expectVueCompilerScriptToCompile, transformOrFail } from './helpers';

const PUBLIC_COUNT = '__swSetupScope.count';
const PRIVATE_COUNT = '__swSetupOverrideScope(__swSetupScope).count';

function overrideSource(markup: string): string {
    return `<script setup lang="ts">
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

function transformCompiled(source: string, filename: string): string {
    const { code } = transformOrFail(source, filename);

    expectVueCompilerScriptToCompile(code, filename);

    return code;
}

describe('build/vue-setup-transform override template writes', () => {
    it.each([
        ['multiple statements', 'save(); count++'],
        ['multiline handler', 'save();\n    count++'],
        ['CRLF handler', 'save();\r\n    count = 1'],
        ['prefix update', 'save(); ++count'],
        ['earlier read', 'save(count); count++'],
        ['repeated writes', 'save(); count++; count = 1'],
        ['logical operator', 'enabled && (count = 1)'],
    ])('rewrites every write in an event handler: %s', (_name, expression) => {
        const code = transformCompiled(overrideSource(`<button @click="${expression}" />`), 'sw-write.override.vue');

        expect(code).toContain(`<button @click="${expression.replace(/\bcount\b/g, PUBLIC_COUNT)}" />`);
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
    ])('rewrites the implicit write in %s', (directive) => {
        // A component, since Vue only accepts a v-model argument there.
        const code = transformCompiled(overrideSource(`<Widget ${directive} />`), 'sw-model.override.vue');

        expect(code).toContain(`<Widget ${directive.replace(/\bcount\b/, PUBLIC_COUNT)} />`);
    });

    it('rewrites a write behind an HTML entity', () => {
        const code = transformCompiled(
            overrideSource('<button @click="ready &amp;&amp; count++" />'),
            'sw-write.override.vue',
        );

        expect(code).toContain(`<button @click="ready &amp;&amp; ${PUBLIC_COUNT}++" />`);
    });

    it.each([
        ['<input v-model="form.count" />', '<input v-model="__swSetupScope.form.count" />'],
        ['<input v-model="form[key]" />', '<input v-model="__swSetupScope.form[key]" />'],
    ])('rewrites the object of a member write: %s', (markup, expected) => {
        expect(transformCompiled(overrideSource(markup), 'sw-model.override.vue')).toContain(expected);
    });

    it.each([
        '<input v-for="count in items" v-model="count" />',
        '<div v-for="count in items"><input v-model="count" /></div>',
        '<Widget><template #default="{ count }"><input v-model="count" /></template></Widget>',
        '<Widget><template #item="{ count }"><input v-model="count" /></template></Widget>',
        '<Widget v-slot="{ count }"><input v-model="count" /></Widget>',
    ])('leaves a write to a template-scoped name alone: %s', (markup) => {
        // Not compiled: Vue itself rejects v-model on a v-for or slot-scope variable.
        const { code } = transformOrFail(overrideSource(markup), 'sw-model.override.vue');

        expect(code).toContain(markup);
    });

    it('rewrites a write next to a sibling template scope of the same name', () => {
        const { code } = transformOrFail(
            overrideSource(`<div v-for="count in items"><input v-model="count" /></div>
    <input v-model="count" />`),
            'sw-model.override.vue',
        );

        expect(code).toContain('<div v-for="count in items"><input v-model="count" /></div>');
        expect(code).toContain(`<input v-model="${PUBLIC_COUNT}" />`);
    });

    it.each([
        'let count = 0;',
        'const count = useSwPreviousState();',
        'const count = useSwProps();',
        'const count = useSwContext();',
    ])('rewrites v-model on private state and input aliases: %s', (declaration) => {
        const source = `<script setup>${declaration} swDefineOverride({});</script>
<template><sw-block extends="example"><input v-model="count" /></sw-block></template>`;

        expect(transformCompiled(source, 'sw-model.override.vue')).toContain(`<input v-model="${PRIVATE_COUNT}" />`);
    });

    it('leaves v-model on a base setup binding to Vue', () => {
        const source = `<script setup>let count = 0; swDefinePublic({ count });</script>
<template><sw-block name="example"><input v-model="count" /></sw-block></template>`;

        expect(transformOrFail(source, 'sw-base.vue')).toBeDefined();
    });
});
