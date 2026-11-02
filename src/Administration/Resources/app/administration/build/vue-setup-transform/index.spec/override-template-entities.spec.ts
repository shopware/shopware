/**
 * @sw-package framework
 *
 * Covers forwarding through `<sw-block extends>` expressions that contain HTML entities.
 *
 * Vue decodes entities before the transform sees an expression, so every offset inside it is in
 * decoded coordinates while the rewrite edits the raw source. These cases pin that each forwarded
 * reference still lands on its own characters and that the entity itself survives untouched.
 */

import { expectVueCompilerScriptToCompile, stripIndent, transformOrFail } from './helpers';

const local = (name: string) => `__swSetupOverrideScope(__swSetupScope).${name}`;

describe('build/vue-setup-transform override template HTML entities', () => {
    it.each([
        {
            title: 'inside a string literal after the reference',
            template: `<p :title="label + ' &amp; more'">x</p>`,
            expected: `<p :title="${local('label')} + ' &amp; more'">x</p>`,
        },
        {
            title: 'as an operator in an interpolation',
            template: `<p>{{ count &gt; 1 ? label : '' }}</p>`,
            expected: `<p>{{ ${local('count')} &gt; 1 ? ${local('label')} : '' }}</p>`,
        },
        {
            title: 'in a v-for alias default before the source',
            template: `<span v-for="({ size = count &gt; 1 ? count : 1 }) in items">{{ size }}</span>`,
            expected:
                `<span v-for="({ size = ${local('count')} &gt; 1 ? ${local('count')} : 1 }) in ${local('items')}">` +
                '{{ size }}</span>',
        },
        {
            title: 'in a slot pattern default',
            template: `<Child><template #item="{ text = '&laquo; ' + label }">{{ text }}</template></Child>`,
            expected: `<Child><template #item="{ text = '&laquo; ' + ${local('label')} }">{{ text }}</template></Child>`,
        },
    ])('rewrites forwarded references around an HTML entity $title', ({ template, expected }) => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                ${template}
            </sw-block>
            </template>
            <script setup>
            const count = 2;
            const label = 'local';
            const items = [];

            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'entity-forwarded-reference.override.vue').code;

        expect(result).toContain(expected);
        expectVueCompilerScriptToCompile(result, 'entity-forwarded-reference.override.vue');
    });
});
