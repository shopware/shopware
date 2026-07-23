/**
 * @sw-package framework
 */

import { expectVueCompilerScriptToCompile, stripIndent, transformOrFail, transformShopwareSetupSfc } from './helpers';

describe('build/vue-setup-transform base defineEmits macro', () => {
    it('keeps defineEmits() outside the extendable setup callback and replaces it with context.emit', () => {
        const source = stripIndent`
            <script setup lang="ts">
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

        expect(result).toContain(`defineEmits<{
    save: [id: string];
}>();`);
        expect(result).not.toContain('const emit = defineEmits');
        expect(result).toContain('const emit = (__swSetupContext.emit);');
        expect(result).toContain("emit('save', '123');");
        expect(result).toContain('private: {\n                emit,\n                save,\n            }');
        expect(result.match(/defineEmits/g)).toHaveLength(1);
    });

    it('keeps local emit type declarations available for hoisted defineEmits()', () => {
        const source = stripIndent`
            <script setup lang="ts">
            type Emits = {
                save: [id: string];
            };

            const emit = defineEmits<Emits>();
            const count = 1;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-emits-local-type.vue').code;

        expect(result.indexOf('type Emits')).toBeLessThan(result.indexOf('defineEmits<Emits>()'));
        expect(result.indexOf('type Emits')).toBeLessThan(result.indexOf('Shopware.Component.createExtendableSetup('));
        expectVueCompilerScriptToCompile(result, 'base-emits-local-type.vue');
    });

    it('rejects local setup bindings in hoisted defineEmits() arguments', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const events = ['save'];
            const emit = defineEmits(events);
            const count = 1;

            swDefinePublic({
                count,
            });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'base-emits-local-runtime-value.vue')).toThrow(
            'defineEmits() arguments are hoisted outside the Shopware setup callback and must not reference local setup bindings.',
        );
    });

    it('hoists bare defineEmits() statements', () => {
        const source = stripIndent`
            <script setup>
            defineEmits(['save']);

            const count = 1;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-bare-emits.vue').code;

        expect(result).toContain("defineEmits(['save']);");
        expect(result).not.toContain('const emit = defineEmits');
        expect(result).toContain('(__swSetupContext.emit);');
    });

    it('prefixes a semicolon before a statement-initial macro replacement to defeat ASI', () => {
        const source = stripIndent`
            <script setup>
            const width = window.outerWidth
            defineEmits(['save'])
            const count = width
            swDefinePublic({ count })
            </script>
        `;

        const result = transformOrFail(source, 'base-emits-asi.vue').code;

        // Without the leading `;`, ASI would parse `window.outerWidth\n(__swSetupContext.emit)` as a call
        // on the previous line. Declaration initializers (`const emit = defineEmits(...)`) stay unprefixed.
        expect(result).toContain(';(__swSetupContext.emit)');
    });

    it('supports runtime array emit declarations', () => {
        const source = stripIndent`
            <script setup>
            const emit = defineEmits(['save']);
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'base-emits-array.vue').code;

        expect(result).toContain("defineEmits(['save']);");
        expect(result).toContain('const emit = (__swSetupContext.emit);');
    });

    it('supports runtime object emit declarations', () => {
        const source = stripIndent`
            <script setup>
            const emit = defineEmits({
                save: (id) => Boolean(id),
            });
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'base-emits-object.vue').code;

        expect(result).toContain(`defineEmits({
    save: (id) => Boolean(id),
});`);
        expect(result).toContain('const emit = (__swSetupContext.emit);');
    });

    it('supports defineEmits() wrapped in a TypeScript as expression', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const emit = defineEmits<{ save: [] }>() as ((event: 'save') => void);
            const count = 1;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-emits-as.vue').code;

        expect(result).toContain('defineEmits<{ save: [] }>();');
        expect(result).toContain("const emit = (__swSetupContext.emit) as ((event: 'save') => void);");
        expect(result.match(/defineEmits/g)).toHaveLength(1);
    });

    it('rejects hoisted arguments that reference a runtime input alias', () => {
        const source = stripIndent`
            <script setup>
            const ctx = useSwContext();
            const emit = defineEmits(ctx.emits);
            const count = 1;
            swDefinePublic({ count });
            </script>
        `;

        // `ctx` aliases useSwContext() and lives inside the setup callback, so a hoisted defineEmits()
        // argument reading it would resolve an unreachable name at the script root.
        expect(() => transformShopwareSetupSfc(source, 'base-emits-alias-arg.vue')).toThrow(
            'defineEmits() arguments are hoisted outside the Shopware setup callback and must not reference local setup bindings.',
        );
    });

    it('rejects duplicate declarations', () => {
        const source = stripIndent`
            <script setup>
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
            <script setup lang="ts">
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
        expect(result).not.toContain('(__swSetupContext.emit)');
    });
});
