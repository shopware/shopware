/**
 * @sw-package framework
 */

/**
 * Covers `defineModel()` in base mode: the macro stays where the author wrote it and Vue compiles it,
 * while the binding it declares is aliased and re-declared through the generated footer like any other
 * top-level binding. The names those declarations bind are forwarded to the runtime so Vue's
 * conventional local names remain valid even though they also name props.
 */

import { expectVueCompilerScriptToCompile, stripIndent, transformOrFail } from './helpers';

describe('build/vue-setup-transform base defineModel macro', () => {
    it('keeps defineModel() in place and re-declares its binding through attachOverrides', () => {
        const source = stripIndent`
            <template><input v-model="modelValue"></template>
            <script setup lang="ts">
            const modelValue = defineModel<string>();

            swDefinePublic({});
            </script>
        `;

        const result = transformOrFail(source, 'sw-my-component.vue').code;

        expect(result).toContain('const __swSetupAuthor_modelValue = defineModel<string>();');
        expect(result).toContain('modelValue: __swSetupAuthor_modelValue,');
        expect(result).toContain('    modelValue,\n} = Shopware.Component.attachOverrides({');
        expect(result).toContain("modelBindings: ['modelValue'],");
        expectVueCompilerScriptToCompile(result, 'sw-my-component.vue');
    });

    it('keeps a TypeScript-wrapped named model argument and its conventional binding name', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const title = defineModel<string>('title' as const, { default: 'fallback' });

            swDefinePublic({});
            </script>
        `;

        const result = transformOrFail(source, 'base-model-named.vue').code;

        expect(result).toContain(
            "const __swSetupAuthor_title = defineModel<string>('title' as const, { default: 'fallback' });",
        );
        expect(result).toContain("modelBindings: ['title'],");
        expectVueCompilerScriptToCompile(result, 'base-model-named.vue');
    });

    it('renames both bindings of a destructured model', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const [
                modelValue,
                modelModifiers,
            ] = defineModel<string>();
            const trimmed = modelModifiers.trim === true;

            swDefinePublic({});
            </script>
        `;

        const result = transformOrFail(source, 'base-model-destructured.vue').code;

        expect(result).toContain('__swSetupAuthor_modelValue,');
        expect(result).toContain('__swSetupAuthor_modelModifiers,');
        expect(result).toContain('const __swSetupAuthor_trimmed = __swSetupAuthor_modelModifiers.trim === true;');
        expect(result).toContain('modelModifiers: __swSetupAuthor_modelModifiers,');
        expect(result).toContain("modelBindings: ['modelValue', 'modelModifiers'],");
        expectVueCompilerScriptToCompile(result, 'base-model-destructured.vue');
    });

    it('supports several models in one component', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const firstModel = defineModel<string>('first');
            const secondModel = defineModel<string>('second');

            swDefinePublic({});
            </script>
        `;

        const result = transformOrFail(source, 'base-model-multiple.vue').code;

        expect(result).toContain('firstModel: __swSetupAuthor_firstModel,');
        expect(result).toContain('secondModel: __swSetupAuthor_secondModel,');
        expectVueCompilerScriptToCompile(result, 'base-model-multiple.vue');
    });

    it('exposes a public model binding to overrides and to a parent template ref', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const value = defineModel<string>();

            swDefinePublic({
                value,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-model-public.vue').code;

        expect(result).toContain('public: {\n        value: __swSetupAuthor_value,\n    },');
        expect(result).toContain('...Shopware.Component.getExposedProps(),');
        expect(result).toContain('defineExpose({\n    ...Shopware.Component.getExposedProps(),\n    value,\n});');
        expectVueCompilerScriptToCompile(result, 'base-model-public.vue');
    });

    it('carries an options-object model through as an ordinary binding', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const options = { required: true };
            const modelValue = defineModel<string>(options);

            swDefinePublic({});
            </script>
        `;

        const result = transformOrFail(source, 'base-model-options-argument.vue').code;

        expect(result).toContain("modelBindings: ['modelValue'],");
        expectVueCompilerScriptToCompile(result, 'base-model-options-argument.vue');
    });

    it('declares no model binding for a statement-form defineModel()', () => {
        const source = stripIndent`
            <script setup lang="ts">
            defineModel<string>('title');

            swDefinePublic({});
            </script>
        `;

        const result = transformOrFail(source, 'base-model-statement.vue').code;

        // The call declares a prop and an emit, but binds nothing - so no setup state can collide with
        // the prop and the runtime needs no exception for it.
        expect(result).not.toContain('modelBindings:');
        expectVueCompilerScriptToCompile(result, 'base-model-statement.vue');
    });

    it('leaves a nested defineModel() call untouched', () => {
        const source = stripIndent`
            <script setup lang="ts">
            function createModel() {
                return defineModel();
            }

            swDefinePublic({});
            </script>
        `;

        const result = transformOrFail(source, 'base-model-nested.vue').code;

        // Only top-level calls declare props, so a nested call contributes no prop name and is neither
        // collected nor rejected - matching how compiler-sfc treats it.
        expect(result).toContain('return defineModel();');
    });
});
