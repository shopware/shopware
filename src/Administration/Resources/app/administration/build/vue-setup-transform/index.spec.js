/**
 * @sw-package framework
 */

const { STATEMENT_TYPES } = require('@babel/types');
const { transformShopwareSetupSfc } = require('./index');

/**
 * @typedef {NonNullable<ReturnType<typeof transformShopwareSetupSfc>>} TransformResult
 */

/**
 * Keeps positive transform assertions typed and avoids repeated non-null assumptions.
 *
 * @param {string} source
 * @param {string} filename
 * @returns {TransformResult}
 */
function transformOrFail(source, filename) {
    const result = transformShopwareSetupSfc(source, filename);

    expect(result).not.toBeNull();

    return result;
}

describe('build/vue-setup-transform', () => {
    it('keeps known statements aligned with Babel statements', () => {
        const reviewedStatementTypes = [
            'BlockStatement',
            'BreakStatement',
            'ClassDeclaration',
            'ContinueStatement',
            'DebuggerStatement',
            'DeclareClass',
            'DeclareExportAllDeclaration',
            'DeclareExportDeclaration',
            'DeclareFunction',
            'DeclareInterface',
            'DeclareModule',
            'DeclareModuleExports',
            'DeclareOpaqueType',
            'DeclareTypeAlias',
            'DeclareVariable',
            'DoWhileStatement',
            'EmptyStatement',
            'EnumDeclaration',
            'ExportAllDeclaration',
            'ExportDefaultDeclaration',
            'ExportNamedDeclaration',
            'ExpressionStatement',
            'ForInStatement',
            'ForOfStatement',
            'ForStatement',
            'FunctionDeclaration',
            'IfStatement',
            'ImportDeclaration',
            'InterfaceDeclaration',
            'LabeledStatement',
            'OpaqueType',
            'ReturnStatement',
            'SwitchStatement',
            'TSDeclareFunction',
            'TSEnumDeclaration',
            'TSExportAssignment',
            'TSImportEqualsDeclaration',
            'TSInterfaceDeclaration',
            'TSModuleDeclaration',
            'TSNamespaceExportDeclaration',
            'TSTypeAliasDeclaration',
            'ThrowStatement',
            'TryStatement',
            'TypeAlias',
            'VariableDeclaration',
            'WhileStatement',
            'WithStatement',
        ];

        expect(
            [
                ...STATEMENT_TYPES,
            ].sort(),
        ).toEqual(reviewedStatementTypes.sort());
    });

    it('transforms base Shopware setup blocks with auto-private state and explicit public state', () => {
        const source = `<template><div>{{ count }}{{ foo2 }}</div></template>
<script setup lang="ts" sw-component="sw-my-component">
import { ref, computed } from 'vue';

const props = useSwProps();
const count = ref(props.initialCount ?? 0);
const doubled = computed(() => count.value * 2);
const internalThing = ref('secret');
const foo2 = ref('bar');

swDefinePublic({
    count,
    doubled,
    'foo': foo2
});
</script>`;

        const expected = `<template><div>{{ count }}{{ foo2 }}</div></template>
<script setup lang="ts">
import { ref, computed } from 'vue';

const {
    count,
    doubled,
    internalThing,
    'foo': foo2,
} = Shopware.Component.createScriptSetupExtendableComponent()('sw-my-component', (__shopwareSetupBindings) => {
    const useSwContext = () => __shopwareSetupBindings.context;

    const props = __shopwareSetupBindings.props;
    const count = ref(props.initialCount ?? 0);
    const doubled = computed(() => count.value * 2);
    const internalThing = ref('secret');
    const foo2 = ref('bar');

    return {
        public: {
            count,
            doubled,
            'foo': foo2,
        },
        private: {
            internalThing,
        },
    };
});
</script>`;

        expect(transformOrFail(source, 'base.vue').code).toBe(expected);
    });

    it('transforms override Shopware setup blocks into hidden override components', () => {
        const source = `<script setup sw-override="sw-my-component">
import { computed } from 'vue';

const previousState = useSwPreviousState();
const props = useSwProps();
const context = useSwContext();

const doubled = computed(() => previousState.count.value * 2);

swDefineOverride({
    doubled,
});
</script>`;

        const expected = `<script>
import { computed } from 'vue';

export default {
    setup() {
        Shopware.Component.overrideComponentSetup()('sw-my-component', (__swPreviousState, __swProps, __swContext) => {
            const useSwPreviousState = () => __swPreviousState;
            const useSwProps = () => __swProps;
            const useSwContext = () => __swContext;

            const previousState = useSwPreviousState();
            const props = useSwProps();
            const context = useSwContext();

            const doubled = computed(() => previousState.count.value * 2);

            return {
                doubled,
            };
        });

        return () => null;
    },
};
</script>`;

        expect(transformOrFail(source, 'component.override.vue').code).toBe(expected);
    });

    it('keeps base defineProps() outside the extendable setup callback and passes props into the bridge', () => {
        const source = `<template><div>{{ count }}</div></template>
<script setup lang="ts" sw-component="sw-my-component">
import { ref } from 'vue';

const props = defineProps<{
    initialCount?: number;
}>();
const count = ref(props.initialCount ?? 0);

swDefinePublic({
    count,
});
</script>`;

        const result = transformOrFail(source, 'base-props.vue').code;

        expect(result).toContain(`const props = defineProps<{
    initialCount?: number;
}>();`);
        expect(result).toContain(
            "Shopware.Component.createScriptSetupExtendableComponent()('sw-my-component', props, (__shopwareSetupBindings) => {",
        );
        expect(result).toContain('const props = __shopwareSetupBindings.props;');
        expect(result).toContain('const count = ref(props.initialCount ?? 0);');
        expect(result).not.toContain('const useSwProps =');
        expect(result.indexOf('const props = defineProps')).toBeLessThan(
            result.indexOf('Shopware.Component.createScriptSetupExtendableComponent()'),
        );
    });

    it('replaces defineProps() destructuring inside the extendable setup callback', () => {
        const source = `<script setup sw-component="sw-my-component">
const { initialCount = 0 } = defineProps();
const count = initialCount;

swDefinePublic({
    count,
});
</script>`;

        const result = transformOrFail(source, 'base-destructured-props.vue').code;

        expect(result).toContain('const props = defineProps();');
        expect(result).toContain('const { initialCount = 0 } = __shopwareSetupBindings.props;');
    });

    it('keeps base withDefaults(defineProps()) outside the extendable setup callback', () => {
        const source = `<script setup lang="ts" sw-component="sw-my-component">
const props = withDefaults(defineProps<{
    initialCount?: number;
    labels?: string[];
}>(), {
    initialCount: 3,
    labels: () => ['main'],
});
const count = props.initialCount;

swDefinePublic({
    count,
});
</script>`;

        const result = transformOrFail(source, 'base-props-with-defaults.vue').code;

        expect(result).toContain(`const props = withDefaults(defineProps<{
    initialCount?: number;
    labels?: string[];
}>(), {
    initialCount: 3,
    labels: () => ['main'],
});`);
        expect(result).toContain(
            "Shopware.Component.createScriptSetupExtendableComponent()('sw-my-component', props, (__shopwareSetupBindings) => {",
        );
        expect(result).toContain('const props = __shopwareSetupBindings.props;');
        expect(result).toContain('const count = props.initialCount;');
        expect(result.match(/defineProps/g)).toHaveLength(1);
        expect(result.match(/withDefaults/g)).toHaveLength(1);
    });

    it('replaces withDefaults() destructuring inside the extendable setup callback', () => {
        const source = `<script setup lang="ts" sw-component="sw-my-component">
const { initialCount } = withDefaults(defineProps<{
    initialCount?: number;
}>(), {
    initialCount: 3,
});
const count = initialCount;

swDefinePublic({
    count,
});
</script>`;

        const result = transformOrFail(source, 'base-destructured-props-with-defaults.vue').code;

        expect(result).toContain(`const props = withDefaults(defineProps<{
    initialCount?: number;
}>(), {
    initialCount: 3,
});`);
        expect(result).toContain('const { initialCount } = __shopwareSetupBindings.props;');
        expect(result.match(/defineProps/g)).toHaveLength(1);
        expect(result.match(/withDefaults/g)).toHaveLength(1);
    });

    it('rejects multiple base props macro declarations', () => {
        const source = `<script setup lang="ts" sw-component="sw-my-component">
const props = defineProps<{ count?: number }>();
const propsWithDefaults = withDefaults(defineProps<{ label?: string }>(), {
    label: 'fallback',
});
const count = props.count ?? propsWithDefaults.label.length;

swDefinePublic({
    count,
});
</script>`;

        expect(() => transformShopwareSetupSfc(source, 'base-duplicate-props-macros.vue')).toThrow(
            'Only one props declaration macro is allowed in a base Shopware setup block.',
        );
    });

    it('replaces base useSwProps() calls instead of injecting a helper', () => {
        const source = `<script setup sw-component="sw-my-component">
const props = useSwProps();
const count = props.initialCount ?? 0;

swDefinePublic({
    count,
});
</script>`;

        const result = transformOrFail(source, 'base-use-sw-props.vue').code;

        expect(result).toContain('const props = __shopwareSetupBindings.props;');
        expect(result).not.toContain('const useSwProps =');
    });

    it('rewrites props access by source ranges instead of placeholder string replacement', () => {
        const source = `<script setup sw-component="sw-my-component">
const props = defineProps();
const literal = '__SHOPWARE_SETUP_DEFINE_PROPS__ __SHOPWARE_SETUP_USE_SW_PROPS__';
const count = props.initialCount ?? literal.length;

swDefinePublic({
    count,
});
</script>`;

        const result = transformOrFail(source, 'base-props-placeholder-literal.vue').code;

        expect(result).toContain("const literal = '__SHOPWARE_SETUP_DEFINE_PROPS__ __SHOPWARE_SETUP_USE_SW_PROPS__';");
        expect(result).toContain('const props = __shopwareSetupBindings.props;');
    });

    it('transforms sw-override blocks in .override.vue files', () => {
        const source = `<script setup sw-override="sw-my-component">
const count = 1;
swDefineOverride({ count });
</script>`;

        const result = transformOrFail(source, 'component-name.override.vue');

        expect(result.mode).toBe('override');
        expect(result.filename).toBe('component-name.override.vue');
        expect(result.code).toContain("Shopware.Component.overrideComponentSetup()('sw-my-component'");
    });

    it('keeps imports out of returned override state', () => {
        const source = `<script setup sw-override="sw-my-component">
import { computed } from 'vue';

const doubled = computed(() => 2);

swDefineOverride({
    doubled,
});
</script>`;

        const result = transformOrFail(source, 'component.override.vue').code;

        expect(result).toContain('return {\n                doubled,\n            };');
        expect(result).not.toContain('computed,');
    });

    it('uses swDefineOverride() as the explicit override payload and keeps unused local state private', () => {
        const source = `<script setup sw-override="sw-my-component">
import { computed, ref } from 'vue';

const previousState = useSwPreviousState();
const body = computed(() => previousState.body.value);
const localInfo = ref('only for script logic');
const localHeadline = computed(() => localInfo.value);
const localFooter = computed(() => localInfo.value);

swDefineOverride({
    body,
    headline: localHeadline,
    'footer-text': localFooter,
});
</script>`;

        const result = transformOrFail(source, 'explicit-payload.override.vue').code;

        expect(result).toContain(
            "return {\n                body,\n                headline: localHeadline,\n                'footer-text': localFooter,\n            };",
        );
        expect(result).not.toContain('__swOverride_');
        expect(result).not.toContain('localInfo,');
    });

    it('returns template-used override-local state through deterministic private aliases', () => {
        const source = `<template>
<sw-block extends="sw_example_component_body">
    <p>{{ body }}</p>
    <small>{{ info }}</small>
</sw-block>
</template>
<script setup lang="ts" sw-override="sw-example-component">
import { computed, ref } from 'vue';

const previousState = useSwPreviousState();
const info = ref('local');
const unused = ref('not exposed');
const body = computed(() => previousState.body.value + info.value);

swDefineOverride({
    body,
});
</script>`;

        const result = transformOrFail(source, 'src/plugin/sw-example-component.override.vue').code;
        const privateAlias = result.match(/__swOverride_[a-f0-9]{5}_info/)?.[0];

        expect(privateAlias).toBeDefined();
        expect(result).toContain(
            `<sw-block extends="sw_example_component_body" #default="{ ${privateAlias}: info, body }">`,
        );
        expect(result).toContain(`return {\n                body,\n                ${privateAlias}: info,\n            };`);
        expect(result).not.toContain('__swOverride_00000_unused');
        expect(result).not.toContain('unused,');
    });

    it('merges private aliases into existing object default slot scopes', () => {
        const source = `<template>
<sw-block extends="sw_example_component_body" #default="{ body, ...previousState }">
    <p>{{ body }}</p>
    <small>{{ info }}</small>
</sw-block>
</template>
<script setup sw-override="sw-example-component">
const body = 1;
const info = 2;

swDefineOverride({
    body,
});
</script>`;

        const result = transformOrFail(source, 'object-slot.override.vue').code;
        const privateAlias = result.match(/__swOverride_[a-f0-9]{5}_info/)?.[0];

        expect(privateAlias).toBeDefined();
        expect(result).toContain(`#default="{ body, ${privateAlias}: info, ...previousState }"`);
    });

    it('merges private aliases into existing identifier default slot scopes', () => {
        const source = `<template>
<sw-block extends="sw_example_component_body" #default="previousState">
    <small>{{ info }}</small>
</sw-block>
</template>
<script setup sw-override="sw-example-component">
const info = 2;

swDefineOverride({});
</script>`;

        const result = transformOrFail(source, 'identifier-slot.override.vue').code;
        const privateAlias = result.match(/__swOverride_[a-f0-9]{5}_info/)?.[0];

        expect(privateAlias).toBeDefined();
        expect(result).toContain(`#default="{ ${privateAlias}: info, ...previousState }"`);
    });

    it('adds private aliases to a default slot without an existing expression', () => {
        const source = `<template>
<sw-block extends="sw_example_component_body" #default>
    <small>{{ info }}</small>
</sw-block>
</template>
<script setup sw-override="sw-example-component">
const info = 2;

swDefineOverride({});
</script>`;

        const result = transformOrFail(source, 'empty-slot.override.vue').code;
        const privateAlias = result.match(/__swOverride_[a-f0-9]{5}_info/)?.[0];

        expect(privateAlias).toBeDefined();
        expect(result).toContain(`#default="{ ${privateAlias}: info }"`);
    });

    it('detects override-local template references in Vue expression positions', () => {
        const source = `<template>
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
<script setup sw-override="sw-example-component">
const visible = true;
const info = 'local';
const eventName = 'click';
const track = () => {};
const dynamicProp = 'title';
const infoLabel = 'label';
const items = [];

swDefineOverride({});
</script>`;

        const result = transformOrFail(source, 'template-references.override.vue').code;

        [
            'visible',
            'info',
            'eventName',
            'track',
            'dynamicProp',
            'infoLabel',
            'items',
        ].forEach((name) => {
            const privateAlias = result.match(new RegExp(`__swOverride_[a-f0-9]{5}_${name}`))?.[0];

            expect(privateAlias).toBeDefined();
            expect(result).toContain(`${privateAlias}: ${name}`);
        });

        expect(result).not.toMatch(/__swOverride_[a-f0-9]{5}_item(?![A-Za-z0-9_$])/);
    });

    it('ignores template identifiers that are not override-local setup references', () => {
        const source = `<template>
<sw-block
    extends="sw_example_component_body"
    class="info"
    data-label="track"
    #default="{ providedInfo }"
>
    plain info text
    <p>{{ providedInfo }}</p>
    <p>{{ previousState.body }}</p>
    <p>{{ [1].map((info) => info).join(',') }}</p>
    <p>{{ ({ info: localInfo }) => localInfo }}</p>
    <p>{{ ({ info: 'static key only' }) }}</p>
</sw-block>
</template>
<script setup sw-override="sw-example-component">
const previousState = useSwPreviousState();
const info = 'local';
const track = () => {};

swDefineOverride({});
</script>`;

        const result = transformOrFail(source, 'ignored-template-references.override.vue').code;

        expect(result).not.toContain('__swOverride_');
        expect(result).toContain('return {};');
    });

    it('does not expose override-local state when an existing default slot scope shadows it', () => {
        const source = `<template>
<sw-block
    extends="sw_example_component_body"
    #default="{ info }"
>
    <small>{{ info }}</small>
</sw-block>
</template>
<script setup sw-override="sw-example-component">
const info = 'local';

swDefineOverride({});
</script>`;

        const result = transformOrFail(source, 'shadowed-slot-scope.override.vue').code;

        expect(result).toContain('#default="{ info }"');
        expect(result).not.toContain('__swOverride_');
        expect(result).toContain('return {};');
    });

    it('ignores plain native script setup blocks', () => {
        const source = `<script setup>
const count = 1;
</script>`;

        expect(transformShopwareSetupSfc(source, 'native.vue')).toBeNull();
    });

    it('ignores Shopware attributes outside script setup blocks', () => {
        const source = `<template>
    <div sw-component="sw-my-component"></div>
</template>
<script setup>
const count = 1;
</script>`;

        expect(transformShopwareSetupSfc(source, 'template-attribute.vue')).toBeNull();
    });

    it('keeps the Vue script setup range when an attribute value contains a script-like string', () => {
        const source = `<script setup sw-component="sw-my-component" data-example="<script">
const count = 1;
</script>`;

        const result = transformOrFail(source, 'script-attribute.vue').code;

        expect(result).toContain("Shopware.Component.createScriptSetupExtendableComponent()('sw-my-component'");
    });

    it('preserves script setup attributes that do not belong to the Shopware transform', () => {
        const source = `<script setup lang="ts" sw-component="sw-my-component" generic="TValue" future-flag>
const count = 1;
</script>`;

        const result = transformOrFail(source, 'passthrough-attributes.vue').code;

        expect(result).toContain('<script setup lang="ts" generic="TValue" future-flag>');
        expect(result).not.toContain('sw-component=');
    });

    it('preserves override script attributes that do not belong to the Shopware transform', () => {
        const source = `<script setup lang="ts" sw-override="sw-my-component" future-flag>
const count = 1;
swDefineOverride({ count });
</script>`;

        const result = transformOrFail(source, 'override-passthrough-attributes.vue').code;

        expect(result).toContain('<script lang="ts" future-flag>');
        expect(result).not.toContain('sw-override=');
        expect(result).not.toContain('<script setup');
    });

    it.each([
        [
            'defineEmits()',
            'Vue macro defineEmits() is not supported inside Shopware setup blocks.',
        ],
        [
            'defineExpose()',
            'Vue macro defineExpose() is not supported inside Shopware setup blocks.',
        ],
        [
            'defineOptions()',
            'Vue macro defineOptions() is not supported inside Shopware setup blocks.',
        ],
        [
            'defineSlots()',
            'Vue macro defineSlots() is not supported inside Shopware setup blocks.',
        ],
        [
            'defineModel()',
            'Vue macro defineModel() is not supported inside Shopware setup blocks.',
        ],
    ])('rejects unsupported Vue macro %s', (macro, expectedMessage) => {
        const source = `<script setup sw-component="sw-my-component">
${macro};
const count = 1;
</script>`;

        expect(() => transformShopwareSetupSfc(source, 'macro.vue')).toThrow(expectedMessage);
    });

    it('rejects defineProps() in override mode', () => {
        const source = `<script setup sw-override="sw-my-component">
const props = defineProps();
swDefineOverride({});
</script>`;

        expect(() => transformShopwareSetupSfc(source, 'override-props.vue')).toThrow(
            'defineProps() is only supported in base Shopware setup blocks.',
        );
    });

    it('rejects withDefaults() in override mode', () => {
        const source = `<script setup lang="ts" sw-override="sw-my-component">
const props = withDefaults(defineProps<{ label?: string }>(), {
    label: 'fallback',
});
swDefineOverride({});
</script>`;

        expect(() => transformShopwareSetupSfc(source, 'override-props-with-defaults.vue')).toThrow(
            'withDefaults() is only supported in base Shopware setup blocks.',
        );
    });

    it('rejects top-level await', () => {
        const source = `<script setup sw-override="sw-my-component">
const value = await loadValue();
</script>`;

        expect(() => transformShopwareSetupSfc(source, 'await.vue')).toThrow(
            'Top-level await is not supported inside Shopware setup blocks.',
        );
    });

    it('rejects unsupported script languages', () => {
        const source = `<script setup lang="coffee" sw-component="sw-my-component">
const count = 1;
</script>`;

        expect(() => transformShopwareSetupSfc(source, 'lang.vue')).toThrow(
            'Unsupported Shopware setup script language "coffee". Supported languages are js, jsx, ts, and tsx.',
        );
    });

    it('rejects TypeScript declare declarations because they are not runtime state', () => {
        const source = `<script setup lang="ts" sw-component="sw-my-component">
declare const count: number;
</script>`;

        expect(() => transformShopwareSetupSfc(source, 'declare.vue')).toThrow(
            'TypeScript declare declarations are not runtime Shopware setup bindings.',
        );
    });

    it('rejects ES module exports like native script setup', () => {
        const source = `<script setup sw-component="sw-my-component">
export const count = 1;
</script>`;

        expect(() => transformShopwareSetupSfc(source, 'export.vue')).toThrow(
            '<script setup> cannot contain ES module exports.',
        );
    });

    it('rejects destructured runtime declarations instead of returning each binding like Vue', () => {
        const source = `<script setup sw-component="sw-my-component">
const { count } = useThing();
</script>`;

        expect(() => transformShopwareSetupSfc(source, 'destructure.vue')).toThrow(
            'Shopware setup only supports top-level runtime declarations with identifier bindings in v1.',
        );
    });

    it('rejects bound mode attributes', () => {
        const source = `<script setup :sw-component="componentName">
const count = 1;
</script>`;

        expect(() => transformShopwareSetupSfc(source, 'bound.vue')).toThrow(
            'Shopware setup mode attributes must be static strings, not bound expressions.',
        );
    });

    it('rejects backslashes in setup script attributes', () => {
        const source = `<script setup sw-component="sw\\my-component">
const count = 1;
</script>`;

        expect(() => transformShopwareSetupSfc(source, 'backslash.vue')).toThrow(
            'Backslashes are not supported in Shopware setup script attributes.',
        );
    });

    it('ignores Shopware mode attributes on non-setup script blocks', () => {
        const source = `<script sw-component="sw-my-component">
const count = 1;
</script>`;

        expect(transformShopwareSetupSfc(source, 'normal-script.vue')).toBeNull();
    });

    it('rejects an additional normal script block next to Shopware setup', () => {
        const source = `<script>
export const moduleValue = 1;
</script>
<script setup sw-component="sw-my-component">
const count = 1;
</script>`;

        expect(() => transformShopwareSetupSfc(source, 'two-scripts.vue')).toThrow(
            'A Shopware setup block cannot be combined with another <script> block',
        );
    });

    it.each([
        [
            'swDefinePublic({ [dynamicKey]: count });',
            'Computed keys in swDefinePublic() are intentionally unsupported',
        ],
        [
            'swDefinePublic({ ...publicState });',
            'Spread properties are not supported inside swDefinePublic().',
        ],
        [
            'swDefinePublic(publicState);',
            'swDefinePublic() requires exactly one object-literal argument.',
        ],
        [
            'if (true) { swDefinePublic({ count }); }',
            'swDefinePublic() must be called once at the top level',
        ],
    ])('rejects invalid swDefinePublic usage: %s', (publicMarker, expectedMessage) => {
        const source = `<script setup sw-component="sw-my-component">
const count = 1;
${publicMarker}
</script>`;

        expect(() => transformShopwareSetupSfc(source, 'public.vue')).toThrow(expectedMessage);
    });

    it.each([
        [
            'swDefineOverride({ [dynamicKey]: count });',
            'Computed keys in swDefineOverride() are intentionally unsupported',
        ],
        [
            'swDefineOverride({ ...overrideState });',
            'Spread properties are not supported inside swDefineOverride().',
        ],
        [
            'swDefineOverride(overrideState);',
            'swDefineOverride() requires exactly one object-literal argument.',
        ],
        [
            'if (true) { swDefineOverride({ count }); }',
            'swDefineOverride() must be called once at the top level',
        ],
        [
            'swDefineOverride({ count, count });',
            'Duplicate override Shopware setup binding key "count".',
        ],
    ])('rejects invalid swDefineOverride usage: %s', (overrideMarker, expectedMessage) => {
        const source = `<script setup sw-override="sw-my-component">
const count = 1;
${overrideMarker}
</script>`;

        expect(() => transformShopwareSetupSfc(source, 'override.vue')).toThrow(expectedMessage);
    });

    it('requires swDefineOverride() in override mode', () => {
        const source = `<script setup sw-override="sw-my-component">
const count = 1;
</script>`;

        expect(() => transformShopwareSetupSfc(source, 'missing-override.vue')).toThrow(
            'swDefineOverride() must be called exactly once at the top level of an override Shopware setup block.',
        );
    });

    it('rejects swDefineOverride() in base mode', () => {
        const source = `<script setup sw-component="sw-my-component">
const count = 1;
swDefineOverride({ count });
</script>`;

        expect(() => transformShopwareSetupSfc(source, 'base-override.vue')).toThrow(
            'swDefineOverride() is only valid in override Shopware setup blocks.',
        );
    });

    it('rejects imported and unknown swDefineOverride() bindings', () => {
        const source = `<script setup sw-override="sw-my-component">
import { computed } from 'vue';

const count = 1;

swDefineOverride({
    imported: computed,
    missing,
});
</script>`;

        expect(() => transformShopwareSetupSfc(source, 'override-import.vue')).toThrow(
            'Imported binding "computed" cannot be exposed with swDefineOverride().',
        );
    });

    it('ignores fake Shopware setup script tags in non-top-level contexts', () => {
        const source = `<!-- <script setup sw-component="from-comment"></script> -->
<template>
    <div data-example="<script setup sw-component='from-template'>"></div>
</template>
<style>
.example::before { content: "<script setup sw-component='from-style'>"; }
</style>
<script setup sw-component="real-component">
// <script setup sw-component='from-line-comment'>
/* <script setup sw-component='from-block-comment'> */
const single = '<script setup sw-component="from-single-string">';
const fake = "<script setup sw-component='from-string'>";
const template = \`<script setup sw-component="from-template-literal">\${'<script setup sw-component="from-template-expression">'}\`;
const count = 1;
</script>`;

        const result = transformOrFail(source, 'scanner.vue').code;

        expect(result).toContain("Shopware.Component.createScriptSetupExtendableComponent()('real-component'");
        expect(result).not.toContain("Shopware.Component.createScriptSetupExtendableComponent()('from-comment'");
        expect(result).not.toContain("Shopware.Component.createScriptSetupExtendableComponent()('from-template'");
        expect(result).not.toContain("Shopware.Component.createScriptSetupExtendableComponent()('from-style'");
        expect(result).not.toContain("Shopware.Component.createScriptSetupExtendableComponent()('from-line-comment'");
        expect(result).not.toContain("Shopware.Component.createScriptSetupExtendableComponent()('from-block-comment'");
        expect(result).not.toContain("Shopware.Component.createScriptSetupExtendableComponent()('from-single-string'");
        expect(result).not.toContain("Shopware.Component.createScriptSetupExtendableComponent()('from-string'");
        expect(result).not.toContain("Shopware.Component.createScriptSetupExtendableComponent()('from-template-literal'");
        expect(result).not.toContain("Shopware.Component.createScriptSetupExtendableComponent()('from-template-expression'");
    });

    it('skips transformation when Vue reports SFC parse errors', () => {
        const source = `<template>
    <div>
</template>
<script setup sw-component="sw-my-component">
const count = 1;`;

        expect(transformShopwareSetupSfc(source, 'malformed.vue')).toBeNull();
    });
});
