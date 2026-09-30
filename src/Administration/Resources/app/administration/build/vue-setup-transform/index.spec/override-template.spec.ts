/**
 * @sw-package framework
 *
 * Covers which override-local setup bindings reach a `<sw-block extends>` slot scope, and how the
 * references that read them are rewritten into it.
 *
 * These are the positive cases: reference detection across Vue expression positions, input-alias
 * forwarding, and the scope rules that decide a binding is *not* forwarded (v-for aliases, slot
 * scopes, nested callback patterns). Rejections live in `override-template-guards.spec.ts`.
 */

import { expectVueCompilerScriptToCompile, stripIndent, stripWhitespace, transformOrFail } from './helpers';

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

        // The whole data scope arrives under one name and every reference reads through it: a declared
        // override binding directly, an override-local one under this file's namespace.
        expect(result).toContain(
            `<sw-block sw-internal-component-name='sw-example-component' extends="sw_example_component_body" #default="__swSetupScope">`,
        );
        expect(result).toContain('<p>{{ __swSetupScope.body }}</p>');
        expect(result).toContain('<small>{{ __swSetupOverrideScope(__swSetupScope).info }}</small>');
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

        expect(result).toContain(
            `<sw-block sw-internal-component-name='override-sw-block-data' extends="sw_example_component_headline" #default="__swSetupScope">`,
        );
        expect(result).toContain('<h2>{{ __swSetupScope.headline }}</h2>');
        expect(result).not.toContain(':data="$dataScope"');
    });

    it('rewrites override-local template references in every Vue expression position', () => {
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
        const info = '__swSetupOverrideScope(__swSetupScope).info';

        expect(result).toContain(`<p v-if="__swSetupOverrideScope(__swSetupScope).visible">{{ ${info} }}</p>`);
        expect(result).toContain(
            `@[__swSetupOverrideScope(__swSetupScope).eventName]="__swSetupOverrideScope(__swSetupScope).track(${info})"`,
        );
        expect(result).toContain(`:title="${info}"`);
        expect(result).toContain(`:[__swSetupOverrideScope(__swSetupScope).dynamicProp]="${info}"`);
        // Vue's same-name shorthand has no value to rewrite, so the rewrite is the value it stood for.
        expect(result).toContain(`:info="${info}"`);
        // A shorthand object property shares its range with the key, which has to survive the rewrite.
        expect(result).toContain(`v-bind="{ info: ${info}, label: __swSetupOverrideScope(__swSetupScope).infoLabel }"`);
        expect(result).toContain(
            `<span v-for="item in __swSetupOverrideScope(__swSetupScope).items">{{ item }}{{ ${info} }}</span>`,
        );
        // The v-for alias `item` is a template-local binding, not a setup reference.
        expect(result).not.toMatch(/\bitem,/);
        expectVueCompilerScriptToCompile(result, 'template-references.override.vue');
    });

    it('keeps rewritten references inside dynamic directive arguments parseable by Vue', () => {
        const source = stripIndent`
            <template>
            <sw-block extends="sw_example_component_body">
                <Child @[eventName]="() => {}" :[dynamicProp]="'value'" :[headlineProp]="'value'" />
                <Child>
                    <template #[slotName]="{ x = count }">{{ x }}</template>
                </Child>
            </sw-block>
            </template>
            <script setup>
            const eventName = 'click';
            const dynamicProp = 'title';
            const headlineProp = 'headline';
            const slotName = 'item';
            const count = 0;

            swDefineOverride({ headlineProp });
            </script>
        `;

        const result = transformOrFail(source, 'dynamic-arguments.override.vue').code;

        // Vue ends a dynamic argument at its first `]`, so no rewritten path may contain one - an
        // override-local reference reads its namespace through the generated accessor instead of a
        // computed member.
        expect(result).toContain(
            '<Child @[__swSetupOverrideScope(__swSetupScope).eventName]="() => {}" ' +
                ':[__swSetupOverrideScope(__swSetupScope).dynamicProp]="\'value\'" ' +
                ':[__swSetupScope.headlineProp]="\'value\'" />',
        );
        expect(result).toContain(
            '<template #[__swSetupOverrideScope(__swSetupScope).slotName]="{ x = __swSetupOverrideScope(__swSetupScope).count }">',
        );
        expectVueCompilerScriptToCompile(result, 'dynamic-arguments.override.vue');
    });

    it('rewrites override-local references in TypeScript and optional-chain template expressions', () => {
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
        const namespaced = (name: string) => `__swSetupOverrideScope(__swSetupScope).${name}`;

        expect(result).toContain(`{{ (${namespaced('maybeInfo')} as string | undefined)?.toUpperCase() }}`);
        expect(result).toContain(`{{ ${namespaced('source')}?.[${namespaced('dynamicKey')}] }}`);
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
        expect(result).toContain('{{ __swSetupOverrideScope(__swSetupScope).previousState.body }}');
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
        // v-for alias, slot scope, or nested callback parameter and must neither be forwarded nor
        // rewritten - a rewritten `info` would read the override's setup state instead of the alias.
        expect(result).toContain(`#default="__swSetupScope"`);
        expect(result).toContain('{{ info }}{{ localLabel }}{{ index }}');
        expect(result).toContain('{{ info }}{{ localInfo }}{{ firstItem }}');
        expect(result).toContain('__swSetupOverrideScope(__swSetupScope).rows.length');
        expect(result).toContain(
            "{{ __swSetupOverrideScope(__swSetupScope).items.map(({ info, label: localLabel }) => info + localLabel).join(',') }}",
        );
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

        expect(result.extendedBlockNames).toEqual(['sw_example_component_headline', 'sw_example_component_body']);
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
        expect(result).toContain('{{ __swSetupOverrideScope(__swSetupScope).props.title }}');
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
        expect(result).toContain('<template #item="{ label = __swSetupOverrideScope(__swSetupScope).fallbackLabel }">');
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
