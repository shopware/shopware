/**
 * @sw-package framework
 */

import { expectVueCompilerScriptToCompile, stripIndent, transformOrFail, transformShopwareSetupSfc } from './helpers';

describe('build/vue-setup-transform base defineExpose macro', () => {
    it('re-emits defineExpose() as a real macro at the script-setup footer', () => {
        const source = stripIndent`
            <script setup>
            function focus() {
                return 'focused';
            }

            defineExpose({
                focus,
            });

            const count = 1;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-expose.vue').code;

        // No runtime context.expose hack, and defineExpose is a real macro emitted after the
        // createExtendableSetup destructure - so the exposed binding is in scope and Vue wires expose
        // exactly once (no "expose() called more than once" dev warning).
        expect(result).not.toContain('__swSetupContext.expose');
        expect(result).toContain('defineExpose({');
        expect(result.indexOf('createExtendableSetup(')).toBeLessThan(result.indexOf('defineExpose({'));
        expect(result.indexOf('defineExpose({')).toBeLessThan(result.indexOf('</script>'));
        // `focus` stays private setup state, so the footer defineExpose can reference the destructured binding.
        expect(result).toContain('private: {\n                focus,\n            }');
        expect(result.match(/defineExpose/g)).toHaveLength(1);
        expectVueCompilerScriptToCompile(result, 'base-expose.vue');
    });

    it('re-emits a bare defineExpose() call at the footer', () => {
        const source = stripIndent`
            <script setup>
            defineExpose();

            const count = 1;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-bare-expose.vue').code;

        expect(result).not.toContain('__swSetupContext.expose');
        expect(result).toContain('defineExpose();');
        expect(result.indexOf('createExtendableSetup(')).toBeLessThan(result.indexOf('defineExpose();'));
        expectVueCompilerScriptToCompile(result, 'base-bare-expose.vue');
    });

    it('re-emits defineExpose() written with a TypeScript as-expression', () => {
        const source = stripIndent`
            <script setup lang="ts">
            function focus() {
                return 'focused';
            }

            defineExpose({
                focus,
            }) as void;

            const count = 1;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-expose-as.vue').code;

        // The footer re-emits the defineExpose() call itself; the surrounding `as void` assertion is
        // dropped (defineExpose returns void anyway).
        expect(result).not.toContain('__swSetupContext.expose');
        expect(result).toContain('defineExpose({');
        expect(result.match(/defineExpose/g)).toHaveLength(1);
        expectVueCompilerScriptToCompile(result, 'base-expose-as.vue');
    });

    it('rejects duplicate declarations', () => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            defineExpose({ count });
            defineExpose({});
            swDefinePublic({ count });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'base-duplicate-expose.vue')).toThrow(
            'Only one defineExpose() call is allowed in a base Shopware setup block.',
        );
    });

    it('ignores nested defineExpose() like Vue compiler-sfc does', () => {
        const source = stripIndent`
            <script setup>
            if (true) {
                defineExpose({});
            }
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'nested-expose.vue').code;

        // A nested defineExpose() is not a top-level macro, so it stays untouched inside the callback
        // body and no footer macro is emitted for it.
        expect(result).toContain(`if (true) {
            defineExpose({});
        }`);
        expect(result).not.toContain('__swSetupContext.expose');
    });
});
