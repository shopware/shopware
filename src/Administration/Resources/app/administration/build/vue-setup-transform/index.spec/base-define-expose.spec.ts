/**
 * @sw-package framework
 */

import { stripIndent, transformOrFail, transformShopwareSetupSfc } from './helpers';

describe('build/vue-setup-transform base defineExpose macro', () => {
    it('replaces defineExpose() with setup context expose inside the extendable setup callback', () => {
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

        expect(result).toContain('(__shopwareContext.expose)({');
        expect(result).toContain('focus,');
        expect(result).not.toContain('defineExpose');
        expect(result.indexOf('function focus()')).toBeLessThan(result.indexOf('(__shopwareContext.expose)'));
        expect(result).toContain('private: {\n                focus,\n            }');
    });

    it('supports bare defineExpose() calls', () => {
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

        expect(result).toContain('(__shopwareContext.expose)();');
        expect(result).not.toContain('defineExpose');
    });

    it('supports defineExpose() wrapped in a TypeScript as expression', () => {
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

        expect(result).toContain(`(__shopwareContext.expose)({
            focus,
        }) as void;`);
        expect(result).not.toContain('defineExpose');
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
});
