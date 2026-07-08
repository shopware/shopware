/**
 * @sw-package framework
 */

/**
 * Covers how override-local state forwarding interacts with template *destructuring patterns* -
 * slot-scope and v-for aliases that use default values, computed keys, rest elements, or aliases that
 * shadow setup bindings. These are the edge cases where the transform must still detect a genuine
 * setup reference, must not mistake a pattern-local alias for one, and must edit the `#default`
 * destructure without producing invalid syntax.
 *
 * The plain (non-pattern) reference-detection and slot-injection cases live in
 * override-template.spec.ts; the script-to-hidden-component lowering and `swDefineOverride` return
 * payload in override-transform.spec.ts.
 */

import { parseExpression } from '@babel/parser';
import { getPrivateNamespace, stripIndent, transformOrFail } from './helpers';

/**
 * Evaluates the generated slot-scope destructure and returns whether it is valid, so tests can
 * catch syntax/runtime errors a plain string assertion would miss.
 *
 */
function isDestructurePatternValid(code: string): boolean {
    const slotExpression = code.match(/#default="([^"]*)"/)?.[1];

    if (!slotExpression) {
        throw new Error('No #default slot scope found in transform result.');
    }

    try {
        parseExpression(`(${slotExpression}) => undefined`);

        return true;
    } catch {
        return false;
    }
}

describe('build/vue-setup-transform override template pattern references', () => {
    it('detects override-local references in slot-scope default values', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body" #default="{ info = fallbackInfo }">
                <p>{{ info }}</p>
            </sw-block>
            </template>
            <script setup>
            const fallbackInfo = 'fallback';

            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'slot-default-reference.override.vue').code;
        const privateNamespace = getPrivateNamespace(result);

        expect(privateNamespace).toBeDefined();
        // The injected binding must precede the default that reads it so the generated slot-scope
        // destructure does not throw a temporal-dead-zone error at runtime.
        expect(result).toContain(
            `#default="{ __swOverride: { ${privateNamespace}: { fallbackInfo } }, info = fallbackInfo }"`,
        );
        expect(isDestructurePatternValid(result)).toBe(true);
    });

    it('injects public override bindings before a referenced slot-scope default', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body" #default="{ info = fallbackInfo }">
                <p>{{ info }}</p>
            </sw-block>
            </template>
            <script setup>
            const fallbackInfo = 'fallback';

            swDefineOverride({ fallbackInfo });
            </script>
        `;

        const result = transformOrFail(source, 'slot-default-public.override.vue').code;

        expect(result).not.toContain('__swOverride');
        expect(result).toContain('#default="{ fallbackInfo, info = fallbackInfo }"');
        expect(isDestructurePatternValid(result)).toBe(true);
    });

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
        const privateNamespace = getPrivateNamespace(result);

        expect(privateNamespace).toBeDefined();
        expect(result).toContain(`#default="{ __swOverride: { ${privateNamespace}: { rows, fallbackLabel } } }"`);
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
        const privateNamespace = getPrivateNamespace(result);

        expect(privateNamespace).toBeDefined();
        expect(result).toContain(`#default="{ __swOverride: { ${privateNamespace}: { rows, dynamicKey } } }"`);
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
        const privateNamespace = getPrivateNamespace(result);

        expect(privateNamespace).toBeDefined();
        // `#default="{ eventName }"` is the <Child> slot alias, scoped to its slot content only.
        // The `@[eventName]`, `:title` and `track` references sit on <Child> itself, outside that
        // scope, so they still resolve to the override's setup bindings and are forwarded through
        // the sw-block rather than being shadowed by the same-element alias.
        expect(result).toContain(
            `#default="{ __swOverride: { ${privateNamespace}: { eventName, title, track } } }"`,
        );
    });

    it('does not expose setup state for slot defaults that reference earlier slot aliases', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body" #default="{ info, label = info }">
                <p>{{ label }}</p>
            </sw-block>
            </template>
            <script setup>
            const info = 'setup info';

            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'slot-default-local-alias.override.vue').code;

        expect(result).not.toContain('__swOverride');
        expect(result).toContain('return {};');
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

    it('rejects a rest element in an extended default slot scope', () => {
        // The override transform injects override state into this slot scope invisibly, so a rest
        // element would silently stop capturing any injected binding referenced elsewhere in the
        // template. That surprise, plus no real use case, is why the pattern is rejected outright.
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body" #default="{ info = fallbackInfo, ...rest }">
                <p>{{ info }} {{ rest }}</p>
            </sw-block>
            </template>
            <script setup>
            const fallbackInfo = 'fallback';

            swDefineOverride({});
            </script>
        `;

        expect(() => transformOrFail(source, 'slot-default-rest.override.vue')).toThrow(
            'A rest element (...) is not supported in a <sw-block extends="..."> default slot scope.',
        );
    });
});
