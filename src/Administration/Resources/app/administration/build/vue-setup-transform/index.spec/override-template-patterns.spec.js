/**
 * @sw-package framework
 */

import { stripIndent, transformOrFail } from './helpers';

/**
 * Evaluates the generated slot-scope destructure and returns whether it is valid, so tests can
 * catch syntax/runtime errors a plain string assertion would miss.
 *
 * @param {string} code
 * @returns {boolean}
 */
function isDestructurePatternValid(code) {
    const slotExpression = code.match(/#default="([^"]*)"/)?.[1];

    if (!slotExpression) {
        throw new Error('No #default slot scope found in transform result.');
    }

    const createSlotScope = new Function(`return (${slotExpression}) => undefined;`);

    try {
        createSlotScope()({});

        return true;
    } catch {
        return false;
    }
}

describe('build/vue-setup-transform override template pattern references', () => {
    it('does not let child component slot scopes shadow same-element directive references', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <Child #default="{ eventName }" @[eventName]="track" :title="title" />
            </sw-block>
            </template>
            <script setup sw-override="sw-example-component">
            const eventName = 'click';
            const title = 'Title';
            const track = () => {};
            
            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'same-element-slot-scope.override.vue').code;

        [
            'eventName',
            'title',
            'track',
        ].forEach((name) => {
            const privateAlias = result.match(new RegExp(`__swOverride_[a-f0-9]{5}_${name}`))?.[0];

            expect(privateAlias).toBeDefined();
            expect(result).toContain(`${privateAlias}: ${name}`);
        });
    });

    it('detects override-local references in slot-scope default values', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body" #default="{ info = fallbackInfo }">
                <p>{{ info }}</p>
            </sw-block>
            </template>
            <script setup sw-override="sw-example-component">
            const fallbackInfo = 'fallback';
            
            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'slot-default-reference.override.vue').code;
        const privateAlias = result.match(/__swOverride_[a-f0-9]{5}_fallbackInfo/)?.[0];

        expect(privateAlias).toBeDefined();
        expect(result).toContain(`${privateAlias}: fallbackInfo`);
        // The injected binding must precede the default that reads it so the generated slot-scope
        // destructure does not throw a temporal-dead-zone error at runtime.
        expect(result).toContain(`#default="{ ${privateAlias}: fallbackInfo, info = fallbackInfo }"`);
        expect(isDestructurePatternValid(result)).toBe(true);
    });

    it('keeps a rest element last when injecting before a referenced default', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body" #default="{ info = fallbackInfo, ...rest }">
                <p>{{ info }} {{ rest }}</p>
            </sw-block>
            </template>
            <script setup sw-override="sw-example-component">
            const fallbackInfo = 'fallback';

            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'slot-default-rest.override.vue').code;
        const privateAlias = result.match(/__swOverride_[a-f0-9]{5}_fallbackInfo/)?.[0];

        expect(privateAlias).toBeDefined();
        expect(result).toContain(`#default="{ ${privateAlias}: fallbackInfo, info = fallbackInfo, ...rest }"`);
        expect(isDestructurePatternValid(result)).toBe(true);
    });

    it('injects public override bindings before a referenced slot-scope default', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body" #default="{ info = fallbackInfo }">
                <p>{{ info }}</p>
            </sw-block>
            </template>
            <script setup sw-override="sw-example-component">
            const fallbackInfo = 'fallback';

            swDefineOverride({ fallbackInfo });
            </script>
        `;

        const result = transformOrFail(source, 'slot-default-public.override.vue').code;

        expect(result).not.toContain('__swOverride_');
        expect(result).toContain('#default="{ fallbackInfo, info = fallbackInfo }"');
        expect(isDestructurePatternValid(result)).toBe(true);
    });

    it('does not expose setup state for slot defaults that reference earlier slot aliases', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body" #default="{ info, label = info }">
                <p>{{ label }}</p>
            </sw-block>
            </template>
            <script setup sw-override="sw-example-component">
            const info = 'setup info';
            
            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'slot-default-local-alias.override.vue').code;

        expect(result).not.toContain('__swOverride_');
        expect(result).toContain('return {};');
    });

    it('detects override-local references in v-for alias default values', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <p v-for="{ label = fallbackLabel } in rows">{{ label }}</p>
            </sw-block>
            </template>
            <script setup sw-override="sw-example-component">
            const rows = [];
            const fallbackLabel = 'fallback';
            
            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'v-for-default-reference.override.vue').code;

        [
            'rows',
            'fallbackLabel',
        ].forEach((name) => {
            const privateAlias = result.match(new RegExp(`__swOverride_[a-f0-9]{5}_${name}`))?.[0];

            expect(privateAlias).toBeDefined();
            expect(result).toContain(`${privateAlias}: ${name}`);
        });
    });

    it('does not expose setup state for v-for defaults that reference earlier object aliases', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <p v-for="{ info, label = info } in rows">{{ label }}</p>
            </sw-block>
            </template>
            <script setup sw-override="sw-example-component">
            const info = 'setup info';
            const rows = [];
            
            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'v-for-object-default-local-alias.override.vue').code;

        expect(result).not.toMatch(/__swOverride_[a-f0-9]{5}_info/);
        expect(result).toMatch(/__swOverride_[a-f0-9]{5}_rows/);
    });

    it('does not expose setup state for v-for defaults that reference earlier array aliases', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <p v-for="[info, label = info] in rows">{{ label }}</p>
            </sw-block>
            </template>
            <script setup sw-override="sw-example-component">
            const info = 'setup info';
            const rows = [];
            
            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'v-for-array-default-local-alias.override.vue').code;

        expect(result).not.toMatch(/__swOverride_[a-f0-9]{5}_info/);
        expect(result).toMatch(/__swOverride_[a-f0-9]{5}_rows/);
    });

    it('detects override-local references in v-for alias computed keys', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <p v-for="{ [dynamicKey]: value } in rows">{{ value }}</p>
            </sw-block>
            </template>
            <script setup sw-override="sw-example-component">
            const rows = [];
            const dynamicKey = 'label';
            
            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'v-for-computed-key-reference.override.vue').code;

        [
            'rows',
            'dynamicKey',
        ].forEach((name) => {
            const privateAlias = result.match(new RegExp(`__swOverride_[a-f0-9]{5}_${name}`))?.[0];

            expect(privateAlias).toBeDefined();
            expect(result).toContain(`${privateAlias}: ${name}`);
        });
    });
});
