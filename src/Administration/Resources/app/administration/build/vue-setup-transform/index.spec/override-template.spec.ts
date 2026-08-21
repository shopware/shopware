/**
 * @sw-package framework
 *
 * Covers which override-local setup bindings reach a `<sw-block extends>` slot scope.
 *
 * These are the positive cases: reference detection across Vue expression positions, input-alias
 * forwarding, and the scope rules that decide a binding is *not* forwarded (v-for aliases, slot
 * scopes, nested callback patterns). Rejections live in `override-template-guards.spec.ts`.
 */

import { stripIndent, stripWhitespace, transformOrFail } from './helpers';

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

        expect(result).toContain(
            `<sw-block extends="sw_example_component_body" #default="{ __swOverride: { [__swSetupNamespace]: { info } }, body }">`,
        );
        expect(stripWhitespace(result)).toContain(stripWhitespace`
            return {
                body,
                __swOverride: {
                    [__swSetupNamespace]: {
                        info,
                    },
                },
            };
        `);
        expect(result).not.toContain('unused,');
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

        expect(result).toContain(
            `#default="{ __swOverride: { [__swSetupNamespace]: { visible, info, eventName, track, dynamicProp, infoLabel, items } } }"`,
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

        expect(result).toContain(`#default="{ __swOverride: { [__swSetupNamespace]: { maybeInfo, source, dynamicKey } } }"`);
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

        // useSwPreviousState()/useSwProps()/useSwContext() are not returned as independent state, but an
        // override template may still read them, so a referenced alias is forwarded like any setup local.
        expect(result).toContain(`#default="{ __swOverride: { [__swSetupNamespace]: { previousState } } }"`);
    });

    it('ignores template identifiers that are not override-local setup references', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                plain info text
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

        // Only `rows` and `items` are genuine setup references; every other name is shadowed by a
        // v-for alias, slot scope, or nested callback parameter and must not be forwarded.
        expect(result).toContain(`#default="{ __swOverride: { [__swSetupNamespace]: { rows, items } } }"`);
    });

    it.each([
        '#default="{ body }"',
        '#default="slotProps"',
        '#default',
        'v-slot="{ body }"',
    ])('rejects the authored default slot scope %s on sw-block', (slotBinding) => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body" ${slotBinding}>
                <p>{{ info }}</p>
            </sw-block>
            </template>
            <script setup>
            const info = 'local';

            swDefineOverride({});
            </script>
        `;

        expect(() => transformOrFail(source, 'authored-slot-scope.override.vue')).toThrow(
            'The default slot scope of <sw-block> is generated by the Shopware setup transform and must not be authored.',
        );
    });

    it('emits the extended block names for the ownership cross-check', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_headline">
                <h2>headline</h2>
            </sw-block>
            <sw-block extends="sw_example_component_body">
                <p>body</p>
            </sw-block>
            </template>
            <script setup>
            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'extended-names.override.vue');

        expect(result.extendedBlockNames).toEqual([
            'sw_example_component_headline',
            'sw_example_component_body',
        ]);
        expect(result.ownedBlockNames).toEqual([]);
    });

    it('forwards useSwProps() aliases referenced in override slot content', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <p>{{ props.title }}</p>
            </sw-block>
            </template>
            <script setup>
            const props = useSwProps();

            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'usesw-props-forward.override.vue').code;

        // useSwProps() is both a setup input and a runtime input alias; like useSwPreviousState() its
        // referenced name must reach the generated slot scope, or `props` resolves against the hidden
        // boot component and `props.title` throws during the base component's render.
        expect(result).toContain(`#default="{ __swOverride: { [__swSetupNamespace]: { props } } }"`);
    });

    it('forwards references inside named slot binding-pattern defaults', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <Child>
                    <template #item="{ label = fallbackLabel }">{{ label }}</template>
                </Child>
            </sw-block>
            </template>
            <script setup>
            const fallbackLabel = 'fallback';

            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'named-slot-default-ref.override.vue').code;

        // The default expression of the named slot `#item` must be scanned like a default slot's, or
        // `fallbackLabel` resolves against the hidden component and `label` silently becomes undefined.
        expect(result).toContain(`#default="{ __swOverride: { [__swSetupNamespace]: { fallbackLabel } } }"`);
    });

    it('does not forward a setup binding shadowed by a named slot scope', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <Child>
                    <template #item="{ info }">{{ info }}</template>
                </Child>
            </sw-block>
            </template>
            <script setup>
            const info = 'local';

            swDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'named-slot-shadow.override.vue').code;

        // `info` inside `#item="{ info }"` is the slot's own binding, so the setup `info` is shadowed
        // and must not be forwarded (over-detection fix).
        expect(result).not.toContain('__swOverride');
        expect(result).toContain('return {};');
    });
});
