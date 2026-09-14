/** @sw-package framework */
import { mount } from '@vue/test-utils';
import { convertComponent } from './convert-component';
import { templateImportRange, makeRoot, writeFile, manifest } from './spec-helpers';
import { compileGeneratedComponent } from './runtime-equivalence-harness';
import { transformShopwareSetupSfc } from '../../../build/vue-setup-transform';
import { prepareLegacyComponent } from '../../../src/app/adapter/options-composition-shim/component-definition';
import type { ComponentConfig, IndexedAwaitedComponentConfig } from '../../../src/core/factory/async-component.factory';
import { runMigration } from './run-sfc-migration';
import * as fs from 'fs';

const name = 'sw-compatible-base';
function convert(options: string) {
    const jsSource = `import template from './${name}.html.twig'; export default { template, ${options} };`;
    return convertComponent({
        jsSource,
        twigSource: '<div>{{ count }}</div>',
        componentName: name,
        vuePath: `${name}.vue`,
        lang: 'js',
        templateImportRange: templateImportRange(jsSource),
    });
}

describe('migration with existing legacy overrides', () => {
    it('retains unused mixin members and their original Options categories', async () => {
        const result = await convert("mixins: ['placeholder'], data() { return { count: 1 }; }");
        expect(result.outcome).toBe('full');
        expect(result.sfc).toContain('const { placeholder } = usePlaceholder();');
        expect(result.sfc).not.toContain('legacyOptions');
        expect(result.sfc).toMatch(/swDefinePublic\(\{\s*placeholder,/);
        const compiled = transformShopwareSetupSfc(result.sfc!, `${name}.vue`);
        expect(compiled?.code).toContain('"placeholder":"method"');
    });

    it.each([
        'notification',
        'position',
        'listing',
        'salutation',
    ])('leaves the incomplete %s mapping on Options', async (mixin) => {
        const result = await convert(`mixins: ['${mixin}'], data() { return { count: 1 }; }`);
        expect(result.outcome).toBe('skipped');
        expect(result.sfc).toBeNull();
        expect(result.reasons.some((reason) => reason.includes(mixin))).toBe(true);
    });

    it.each([
        'created() { this.count++; }',
        'watch: { count() {} }',
    ])('does not move initialization-sensitive base effects: %s', async (option) => {
        expect((await convert(`data() { return { count: 1 }; }, ${option}`)).outcome).toBe('skipped');
    });

    it('preserves data, computed, methods, $super, and immediate plugin watchers after actual migration and compilation', async () => {
        const result = await convert(`data() { return { count: 1, callback: () => 'data callback' }; },
            computed: {
                doubled() { return this.count * 2; },
                amount: {
                    get() { return this.count; },
                    set(value) { this.count = value; }
                }
            },
            methods: { read() { return this.doubled; } }`);
        expect(result.outcome).toBe('full');
        expect(result.sfc).not.toContain('legacyOptions');
        expect(result.sfc).toContain('const count = ref(1);');
        const seen = jest.fn();
        const override: ComponentConfig = {
            data: () => ({ count: 4 }),
            methods: {
                read(this: { $super: (name: string) => number }) {
                    return this.$super('read') + 1;
                },
            },
            watch: {
                count: {
                    immediate: true,
                    handler(this: { read: () => number }) {
                        seen(this.read());
                    },
                },
            },
        };
        const base = compileGeneratedComponent(result.sfc!, `${name}.vue`, '<div>{{ count }}:{{ read() }}</div>');
        const definition = prepareLegacyComponent(name, base as ComponentConfig, [
            { resolvedConfig: override } as IndexedAwaitedComponentConfig,
        ]);
        const wrapper = mount(definition);
        expect(wrapper.text()).toBe('4:9');
        expect(seen).toHaveBeenCalledWith(9);
        expect(wrapper.vm.$data).toHaveProperty('count', 4);
        expect(wrapper.vm.$data).not.toHaveProperty('doubled');
        expect(wrapper.vm.$data).not.toHaveProperty('read');
        expect(wrapper.vm.$options.methods).toHaveProperty('read');
        expect(wrapper.vm.$options.computed).toHaveProperty('doubled');
        expect(wrapper.vm.$data).toHaveProperty('callback', expect.any(Function));
        expect(wrapper.vm.$options.methods).not.toHaveProperty('callback');
        expect(wrapper.vm.$options.computed).toHaveProperty('amount.set', expect.any(Function));
        expect(wrapper.vm.$data).not.toHaveProperty('amount');
        wrapper.unmount();
    });

    it('does not write or replace an incompatible component even with replacement enabled', async () => {
        const root = makeRoot('sfc-legacy-gate-');
        try {
            writeFile(root, 'index.js', `Shopware.Component.register('${name}', () => import('./${name}'));`);
            writeFile(
                root,
                `${name}/index.js`,
                `import template from './${name}.html.twig'; export default { template, mixins: ['notification'] };`,
            );
            writeFile(root, `${name}/${name}.html.twig`, '<div>base</div>');
            const before = manifest(root);
            const result = await runMigration(root, { write: true, replaceOriginals: true });
            expect(result.reports[0].outcome).toBe('skipped');
            expect(manifest(root)).toEqual(before);
        } finally {
            fs.rmSync(root, { recursive: true, force: true });
        }
    });
});
