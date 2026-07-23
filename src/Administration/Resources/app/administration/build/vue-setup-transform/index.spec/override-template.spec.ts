/**
 * @sw-package framework
 */

/**
 * Covers template-driven override-local state forwarding: which setup references the transform detects
 * in an override template, how it generates the `<sw-block extends>` default slot scope from them -
 * including runtime input aliases - and the authored sw-block bindings it rejects because they are
 * generated (`#default`, `data`, `v-bind`).
 *
 * The pure script/return lowering lives in override-transform.spec.ts; the destructuring-pattern edge
 * cases (v-for defaults, computed keys, pattern-local aliases) in override-template-patterns.spec.ts.
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
        const privateNamespace = getPrivateNamespace(result);

        // Only `rows` and `items` are genuine setup references; every other name is shadowed by a
        // v-for alias, slot scope, or nested callback parameter and must not be forwarded.
        expect(privateNamespace).toBeDefined();
        expect(result).toContain(`#default="{ __swOverride: { ${privateNamespace}: { rows, items } } }"`);
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

    it('rejects authored data bindings on extended sw-blocks', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body" :data="scope">
                <p>{{ info }}</p>
            </sw-block>
            </template>
            <script setup>
            const scope = {};
            const info = 'local';

            swDefineOverride({});
            </script>
        `;

        expect(() => transformOrFail(source, 'authored-data.override.vue')).toThrow(
            'The data binding of <sw-block> is generated by the Shopware setup transform and must not be authored.',
        );
    });

    it('rejects v-bind objects on extended sw-blocks', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body" v-bind="attrs">
                <p>{{ info }}</p>
            </sw-block>
            </template>
            <script setup>
            const attrs = {};
            const info = 'local';

            swDefineOverride({});
            </script>
        `;

        expect(() => transformOrFail(source, 'authored-v-bind.override.vue')).toThrow('"v-bind" is not supported');
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

    it('rejects a bound :extends on sw-block (only a static extends is allowed)', () => {
        const source = stripIndent`
            <template>
            <sw-block :extends="blockName">
                <p>{{ body }}</p>
            </sw-block>
            </template>
            <script setup>
            const blockName = 'sw_example_component_body';
            const body = 'local';

            swDefineOverride({});
            </script>
        `;

        expect(() => transformOrFail(source, 'bound-extends.override.vue')).toThrow(
            'Only a static "extends" attribute is allowed on <sw-block>; ":extends" is not supported.',
        );
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
        const privateNamespace = getPrivateNamespace(result);

        // useSwProps() is both a setup input and a runtime input alias; like useSwPreviousState() its
        // referenced name must reach the generated slot scope, or `props` resolves against the hidden
        // boot component and `props.title` throws during the base component's render.
        expect(privateNamespace).toBeDefined();
        expect(result).toContain(`#default="{ __swOverride: { ${privateNamespace}: { props } } }"`);
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
        const privateNamespace = getPrivateNamespace(result);

        // The default expression of the named slot `#item` must be scanned like a default slot's, or
        // `fallbackLabel` resolves against the hidden component and `label` silently becomes undefined.
        expect(privateNamespace).toBeDefined();
        expect(result).toContain(`#default="{ __swOverride: { ${privateNamespace}: { fallbackLabel } } }"`);
    });

    it('rejects an authored #default supplied through a child <template>', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <template #default="{ body }">
                    <p>{{ body }}</p>
                </template>
            </sw-block>
            </template>
            <script setup>
            swDefineOverride({});
            </script>
        `;

        // The generated #default is injected on the element, so a child <template #default> must be
        // rejected with the same message instead of colliding into an opaque Vue codegen error.
        expect(() => transformOrFail(source, 'child-template-default.override.vue')).toThrow(
            'The default slot scope of <sw-block> is generated by the Shopware setup transform and must not be authored.',
        );
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

    it('rejects top-level override content that is not a sw-block extends block', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <p>{{ body }}</p>
            </sw-block>
            <div>{{ stray }}</div>
            </template>
            <script setup>
            const body = 'x';
            const stray = 'y';

            swDefineOverride({});
            </script>
        `;

        // An override renders only inside the blocks it extends; a top-level <div> would never render.
        expect(() => transformOrFail(source, 'stray-top-level.override.vue')).toThrow(
            'An override template may only contain <sw-block extends="..."> blocks at its top level.',
        );
    });

    it('rejects a base-style named sw-block nested inside an override extends block', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <sw-block name="sw_new_block">
                    <p>content</p>
                </sw-block>
            </sw-block>
            </template>
            <script setup>
            swDefineOverride({});
            </script>
        `;

        expect(() => transformOrFail(source, 'nested-named-block.override.vue')).toThrow(
            '<sw-block name="..."> is only valid in a base component.',
        );
    });

    it('rejects a base-style named sw-block at the top level of an override', () => {
        const source = stripIndent`
            <template>
            <sw-block name="sw_new_block">
                <p>content</p>
            </sw-block>
            </template>
            <script setup>
            swDefineOverride({});
            </script>
        `;

        expect(() => transformOrFail(source, 'top-level-named-block.override.vue')).toThrow(
            'An override template may only contain <sw-block extends="..."> blocks at its top level.',
        );
    });

    it('rejects directives on the sw-block extends element itself', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body" v-if="showOverride">
                <p>text</p>
            </sw-block>
            </template>
            <script setup>
            import { ref } from 'vue';

            const showOverride = ref(true);

            swDefineOverride({});
            </script>
        `;

        // The generated slot scope only exists inside the block, so `showOverride` on the element itself
        // resolves against the hidden component and the block never mounts - reject it loudly instead.
        expect(() => transformOrFail(source, 'extends-element-directive.override.vue')).toThrow('"v-if" is not supported');
    });
});
