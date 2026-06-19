/**
 * @sw-package framework
 */

import { stripIndent, transformOrFail, transformShopwareSetupSfc } from './helpers';

describe('build/vue-setup-transform base defineEmits macro', () => {
    it('keeps defineEmits() outside the extendable setup callback and replaces it with context.emit', () => {
        const source = stripIndent`
            <script setup lang="ts" sw-component="sw-my-component">
            const emit = defineEmits<{
                save: [id: string];
            }>();
            
            function save() {
                emit('save', '123');
            }
            
            const count = 1;
            
            swDefinePublic({
                count,
            });
            </script>
        `;
        const result = transformOrFail(source, 'base-emits.vue').code;

        expect(result).toContain(`const emit = defineEmits<{
    save: [id: string];
}>();`);
        expect(result).toContain('const emit = (__shopwareContext.emit);');
        expect(result).toContain("emit('save', '123');");
        expect(result).toContain('private: {\n                save,\n            }');
        expect(result.match(/defineEmits/g)).toHaveLength(1);
    });

    it('keeps bare defineEmits() outside the callback when the generated emit binding name is taken', () => {
        const source = stripIndent`
            <script setup sw-component="sw-my-component">
            defineEmits(['save']);
            
            function emit() {
                return 'local binding';
            }
            
            const count = emit().length;
            
            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-bare-emits-collision.vue').code;

        expect(result).toContain("const emit2 = defineEmits(['save']);");
        expect(result).toContain('(__shopwareContext.emit);');
        expect(result).toContain("return 'local binding';");
        expect(result).toContain('private: {\n                emit,\n            }');
    });

    it('supports runtime array and object declarations', () => {
        const arraySource = stripIndent`
            <script setup sw-component="sw-my-component">
            const emit = defineEmits(['save']);
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;
        const objectSource = stripIndent`
            <script setup sw-component="sw-my-component">
            const emit = defineEmits({
                save: (id) => Boolean(id),
            });
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        expect(transformOrFail(arraySource, 'base-emits-array.vue').code).toContain("const emit = defineEmits(['save']);");
        expect(transformOrFail(arraySource, 'base-emits-array.vue').code).toContain(
            'const emit = (__shopwareContext.emit);',
        );
        expect(transformOrFail(objectSource, 'base-emits-object.vue').code).toContain(`const emit = defineEmits({
    save: (id) => Boolean(id),
});`);
        expect(transformOrFail(objectSource, 'base-emits-object.vue').code).toContain(
            'const emit = (__shopwareContext.emit);',
        );
    });

    it('supports defineEmits() wrapped in a TypeScript as expression', () => {
        const source = stripIndent`
            <script setup lang="ts" sw-component="sw-my-component">
            const emit = defineEmits<{ save: [] }>() as ((event: 'save') => void);
            const count = 1;
            
            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-emits-as.vue').code;

        expect(result).toContain('const emit = defineEmits<{ save: [] }>();');
        expect(result).toContain("const emit = (__shopwareContext.emit) as ((event: 'save') => void);");
        expect(result.match(/defineEmits/g)).toHaveLength(1);
    });

    it('rejects duplicate declarations', () => {
        const source = stripIndent`
            <script setup sw-component="sw-my-component">
            const emit = defineEmits(['save']);
            const otherEmit = defineEmits(['cancel']);
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'base-duplicate-emits.vue')).toThrow(
            'Only one defineEmits() call is allowed in a base Shopware setup block.',
        );
    });

    it('ignores nested defineEmits() like Vue compiler-sfc does', () => {
        const source = stripIndent`
            <script setup lang="ts" sw-component="sw-my-component">
            function save() {
                const emit = defineEmits<{ save: [] }>();
                emit('save');
            }

            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'base-nested-emits.vue').code;

        expect(result).toContain(`function save() {
            const emit = defineEmits<{ save: [] }>();
            emit('save');
        }`);
        expect(result.indexOf('const emit = defineEmits')).toBeGreaterThan(
            result.indexOf('Shopware.Component.createExtendableSetup('),
        );
        expect(result).not.toContain('(__shopwareContext.emit)');
    });
});
