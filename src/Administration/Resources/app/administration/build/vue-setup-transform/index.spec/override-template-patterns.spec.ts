/**
 * @sw-package framework
 */

/**
 * Covers how override-local state forwarding interacts with template *destructuring patterns* -
 * v-for aliases and child-component slot scopes that use default values, computed keys, or aliases
 * that shadow setup bindings. These are the edge cases where the transform must still detect a
 * genuine setup reference and must not mistake a pattern-local alias for one.
 *
 * The plain reference-detection, slot-scope generation, and rejected sw-block bindings live in
 * override-template.spec.ts; the script-to-hidden-component lowering and `swDefineOverride` return
 * payload in override-transform.spec.ts.
 */

import { stripIndent, transformOrFail } from './helpers';

describe('build/vue-setup-transform override template pattern references', () => {
    it('detects override-local references in v-for alias default values', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <p v-for="{ label = fallbackLabel } in rows">{{ label }}</p>
            </sw-block>
            </template>
            <script setup>
            const rows = [];
            const fallbackLabel = 'fallback';

            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'v-for-default-reference.override.vue').code;

        expect(result).toContain(`#default="{ __swOverride: { [__swSetupNamespace]: { rows, fallbackLabel } } }"`);
    });

    it('detects override-local references in v-for alias computed keys', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <p v-for="{ [dynamicKey]: value } in rows">{{ value }}</p>
            </sw-block>
            </template>
            <script setup>
            const rows = [];
            const dynamicKey = 'label';

            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'v-for-computed-key-reference.override.vue').code;

        expect(result).toContain(`#default="{ __swOverride: { [__swSetupNamespace]: { rows, dynamicKey } } }"`);
    });

    it('does not let child component slot scopes shadow same-element directive references', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <Child #default="{ eventName }" @[eventName]="track" :title="title" />
            </sw-block>
            </template>
            <script setup>
            const eventName = 'click';
            const title = 'Title';
            const track = () => {};

            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'same-element-slot-scope.override.vue').code;

        // `#default="{ eventName }"` is the <Child> slot alias, scoped to its slot content only.
        // The `@[eventName]`, `:title` and `track` references sit on <Child> itself, outside that
        // scope, so they still resolve to the override's setup bindings and are forwarded through
        // the sw-block rather than being shadowed by the same-element alias.
        expect(result).toContain(`#default="{ __swOverride: { [__swSetupNamespace]: { eventName, title, track } } }"`);
    });

    it('does not expose setup state for v-for defaults that reference earlier object aliases', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <p v-for="{ info, label = info } in rows">{{ label }}</p>
            </sw-block>
            </template>
            <script setup>
            const info = 'setup info';
            const rows = [];

            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'v-for-object-default-local-alias.override.vue').code;

        expect(result).not.toMatch(/\n\s+info,/);
        expect(result).toMatch(/\n\s+rows,/);
    });

    it('does not expose setup state for v-for defaults that reference earlier array aliases', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <p v-for="[info, label = info] in rows">{{ label }}</p>
            </sw-block>
            </template>
            <script setup>
            const info = 'setup info';
            const rows = [];

            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'v-for-array-default-local-alias.override.vue').code;

        expect(result).not.toMatch(/\n\s+info,/);
        expect(result).toMatch(/\n\s+rows,/);
    });
});
