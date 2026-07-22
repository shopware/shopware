/**
 * @sw-package framework
 */

/**
 * Covers template-driven override-local state forwarding: which setup references the transform detects
 * in an override template, and how it injects or merges that state into the `<sw-block extends>`
 * default slot scope - including runtime input aliases, top-level shadowing, and the rejected
 * slot-scope bindings.
 *
 * The pure script/return lowering lives in override-transform.spec.ts; the destructuring-pattern edge
 * cases (defaults, computed keys, rest, pattern-local aliases) in override-template-patterns.spec.ts.
 */

import { getPrivateNamespace, stripIndent, transformOrFail } from './helpers';

describe('build/vue-setup-transform override template forwarding', () => {
    it('returns template-used override-local state through a deterministic private namespace', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <p>{{ body }}</p>
                <small>{{ info }}</small>
            </sw-block>
            </template>
            <script setup lang="ts">
            import { computed, ref } from 'vue';

            const previousState = useSwPreviousState();
            const info = ref('local');
            const unused = ref('not exposed');
            const body = computed(() => previousState.body.value + info.value);

            swDefineOverride({
                body,
            });
            </script>
        `;

        const result = transformOrFail(source, 'src/plugin/sw-example-component.override.vue').code;
        const privateNamespace = getPrivateNamespace(result);

        expect(privateNamespace).toBeDefined();
        expect(result).toContain(
            `<sw-block extends="sw_example_component_body" #default="{ __swOverride: { ${privateNamespace}: { info } }, body }">`,
        );
        expect(result).toContain(`return {
        body,
        __swOverride: {
            ${privateNamespace}: {
                info,
            },
        },
    };`);
        expect(result).not.toContain('unused,');
    });

    it('adds private namespaces to a default slot without an existing expression', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body" #default>
                <small>{{ info }}</small>
            </sw-block>
            </template>
            <script setup>
            const info = 2;

            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'empty-slot.override.vue').code;
        const privateNamespace = getPrivateNamespace(result);

        expect(privateNamespace).toBeDefined();
        expect(result).toContain(`#default="{ __swOverride: { ${privateNamespace}: { info } } }"`);
    });

    it('does not add generated data scope to override sw-block extensions', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_headline">
                <h2>{{ headline }}</h2>
            </sw-block>
            </template>
            <script setup>
            const headline = 'Headline';

            swDefineOverride({
                headline,
            });
            </script>
        `;

        const result = transformOrFail(source, 'override-sw-block-data.override.vue').code;

        expect(result).toContain('<sw-block extends="sw_example_component_headline" #default="{ headline }">');
        expect(result).not.toContain(':data="$dataScope"');
    });

    it('merges private namespaces into an existing object default slot scope', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body" #default="{ body }">
                <p>{{ body }}</p>
                <small>{{ info }}</small>
            </sw-block>
            </template>
            <script setup>
            const body = 1;
            const info = 2;

            swDefineOverride({
                body,
            });
            </script>
        `;

        const result = transformOrFail(source, 'object-slot.override.vue').code;
        const privateNamespace = getPrivateNamespace(result);

        expect(privateNamespace).toBeDefined();
        expect(result).toContain(`#default="{ body, __swOverride: { ${privateNamespace}: { info } } }"`);
    });

    it('detects override-local template references in Vue expression positions', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <p v-if="visible">{{ info }}</p>
                <button
                    @[eventName]="track(info)"
                    :title="info"
                    :[dynamicProp]="info"
                    :info
                    v-bind="{ info, label: infoLabel }"
                />
                <span v-for="item in items">{{ item }}{{ info }}</span>
            </sw-block>
            </template>
            <script setup>
            const visible = true;
            const info = 'local';
            const eventName = 'click';
            const track = () => {};
            const dynamicProp = 'title';
            const infoLabel = 'label';
            const items = [];

            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'template-references.override.vue').code;
        const privateNamespace = getPrivateNamespace(result);

        expect(privateNamespace).toBeDefined();
        expect(result).toContain(
            `#default="{ __swOverride: { ${privateNamespace}: { visible, info, eventName, track, dynamicProp, infoLabel, items } } }"`,
        );
        // The v-for alias `item` is a template-local binding, not a setup reference.
        expect(result).not.toMatch(/\bitem,/);
    });

    it('detects override-local references in TypeScript and optional-chain template expressions', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <p>{{ (maybeInfo as string | undefined)?.toUpperCase() }}</p>
                <p>{{ source?.[dynamicKey] }}</p>
            </sw-block>
            </template>
            <script setup lang="ts">
            const maybeInfo = 'local';
            const source = {
                headline: 'Headline',
            };
            const dynamicKey = 'headline';

            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'typescript-template-references.override.vue').code;
        const privateNamespace = getPrivateNamespace(result);

        expect(privateNamespace).toBeDefined();
        expect(result).toContain(`#default="{ __swOverride: { ${privateNamespace}: { maybeInfo, source, dynamicKey } } }"`);
    });

    it('forwards override input-alias references used in the template', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <p>{{ previousState.body }}</p>
            </sw-block>
            </template>
            <script setup>
            const previousState = useSwPreviousState();

            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'input-alias-reference.override.vue').code;
        const privateNamespace = getPrivateNamespace(result);

        // useSwPreviousState()/useSwProps()/useSwContext() are not returned as independent state, but an
        // override template may still read them, so a referenced alias is forwarded like any setup local.
        expect(privateNamespace).toBeDefined();
        expect(result).toContain(`#default="{ __swOverride: { ${privateNamespace}: { previousState } } }"`);
    });

    it('ignores template identifiers that are not override-local setup references', () => {
        const source = stripIndent`
            <template>
            <sw-block
                extends="sw_example_component_body"
                class="info"
                data-label="track"
                #default="{ providedInfo }"
            >
                plain info text
                <p>{{ providedInfo }}</p>
                <p>{{ [1].map((info) => info).join(',') }}</p>
                <p>{{ ({ info: localInfo }) => localInfo }}</p>
                <p>{{ ({ info: 'static key only' }) }}</p>
            </sw-block>
            </template>
            <script setup>
            const info = 'local';
            const track = () => {};

            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'ignored-template-references.override.vue').code;

        expect(result).not.toContain('__swOverride');
        expect(result).toContain('return {};');
    });

    it('ignores identifiers shadowed by v-for aliases, slot scopes, and nested callback patterns', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <p v-for="({ info, label: localLabel }, index) in rows">
                    {{ info }}{{ localLabel }}{{ index }}{{ rows.length }}
                </p>

                <Child #default="{ info, nested: { localInfo }, items: [firstItem] }">
                    {{ info }}{{ localInfo }}{{ firstItem }}{{ rows.length }}
                </Child>

                <p>{{ items.map(({ info, label: localLabel }) => info + localLabel).join(',') }}</p>
            </sw-block>
            </template>
            <script setup>
            const info = 'setup info';
            const localInfo = 'setup nested info';
            const firstItem = 'setup first item';
            const localLabel = 'setup label';
            const index = 0;
            const rows = [];
            const items = [];

            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'template-shadowing-patterns.override.vue').code;
        const privateNamespace = getPrivateNamespace(result);

        // Only `rows` and `items` are genuine setup references; every other name is shadowed by a
        // v-for alias, slot scope, or nested callback parameter and must not be forwarded.
        expect(privateNamespace).toBeDefined();
        expect(result).toContain(`#default="{ __swOverride: { ${privateNamespace}: { rows, items } } }"`);
    });

    it('does not expose override-local state when an existing default slot scope shadows it', () => {
        const source = stripIndent`
            <template>
            <sw-block
                extends="sw_example_component_body"
                #default="{ info }"
            >
                <small>{{ info }}</small>
            </sw-block>
            </template>
            <script setup>
            const info = 'local';

            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'shadowed-slot-scope.override.vue').code;

        expect(result).toContain('#default="{ info }"');
        expect(result).not.toContain('__swOverride');
        expect(result).toContain('return {};');
    });

    it('rejects a bare identifier default slot scope', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body" #default="slotProps">
                <small>{{ info }}</small>
            </sw-block>
            </template>
            <script setup>
            const info = 2;

            swDefineOverride({});
            </script>
        `;

        expect(() => transformOrFail(source, 'identifier-slot.override.vue')).toThrow(
            'A bare identifier default slot scope (for example #default="slotProps") is not supported',
        );
    });

    it.each([
        '#default="{ __swOverride }"',
        '#default="{ __swOverride: overrideState }"',
        '#default="{ nested: { __swOverride } }"',
        '#default="{ ...__swOverride }"',
    ])('rejects user-authored %s slot bindings that would block private namespace injection', (slotScope) => {
        const source = stripIndent`
            <template>
            <sw-block
                extends="sw_example_component_body"
                ${slotScope}
            >
                <small>{{ info }}</small>
            </sw-block>
            </template>
            <script setup>
            const info = 'local';

            swDefineOverride({});
            </script>
        `;

        expect(() => transformOrFail(source, 'reserved-slot-scope.override.vue')).toThrow(
            '"__swOverride" is reserved for Shopware override-private state and must not be used as a slot-scope binding.',
        );
    });
});
