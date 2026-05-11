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
    const useSwProps = () => __shopwareSetupBindings.props;
    const useSwContext = () => __shopwareSetupBindings.context;

    const props = useSwProps();
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

    it('transforms sw-override blocks in .override.vue files', () => {
        const source = `<script setup sw-override="sw-my-component">
const count = 1;
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
</script>`;

        const result = transformOrFail(source, 'component.override.vue').code;

        expect(result).toContain('return {\n                doubled,\n            };');
        expect(result).not.toContain('computed,');
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
</script>`;

        const result = transformOrFail(source, 'override-passthrough-attributes.vue').code;

        expect(result).toContain('<script lang="ts" future-flag>');
        expect(result).not.toContain('sw-override=');
        expect(result).not.toContain('<script setup');
    });

    it.each([
        [
            'defineProps()',
            'Vue macro defineProps() is not supported inside Shopware setup blocks.',
        ],
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
        [
            'withDefaults()',
            'Vue macro withDefaults() is not supported inside Shopware setup blocks.',
        ],
    ])('rejects unsupported Vue macro %s', (macro, expectedMessage) => {
        const source = `<script setup sw-component="sw-my-component">
${macro};
const count = 1;
</script>`;

        expect(() => transformShopwareSetupSfc(source, 'macro.vue')).toThrow(expectedMessage);
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
