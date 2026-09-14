/**
 * @sw-package framework
 */

import { mount, type VueWrapper } from '@vue/test-utils';
import { compileScript, parse } from '@vue/compiler-sfc';
import { transpileModule, ModuleKind, ScriptTarget } from 'typescript';
import { transformOrFail } from './helpers';
import { prepareLegacyComponent } from 'src/app/adapter/options-composition-shim/component-definition';
import type { ComponentConfig, IndexedAwaitedComponentConfig } from 'src/core/factory/async-component.factory';

function compileComponent(script: string, options: ComponentConfig = {}, template = '<div>{{ read() }}</div>') {
    const source = `<script setup lang="ts">${script}</script><template>${template}</template>`;
    const { code } = transformOrFail(source, 'sw-generated-metadata.vue');
    const compiled = compileScript(parse(code).descriptor, { id: 'metadata', inlineTemplate: true });
    const javascript = transpileModule(compiled.content, {
        compilerOptions: { module: ModuleKind.CommonJS, target: ScriptTarget.ES2022 },
    }).outputText;
    const exports: { default?: ComponentConfig } = {};
    // Execute only Vue compiler output from these trusted inline fixtures.
    // eslint-disable-next-line @typescript-eslint/no-implied-eval
    const execute = new Function('require', 'exports', javascript) as (load: NodeRequire, output: object) => void;
    execute(require, exports);
    return prepareLegacyComponent('sw-generated-metadata', exports.default!, [
        { resolvedConfig: options } as IndexedAwaitedComponentConfig,
    ]);
}

describe('compiled Options metadata at runtime', () => {
    const wrappers: VueWrapper[] = [];
    afterEach(() => wrappers.splice(0).forEach((wrapper) => wrapper.unmount()));

    it('recreates native data, methods and computed categories without authored metadata', () => {
        const callback = jest.fn();
        const component = compileComponent(
            `
            import { ref, computed } from 'vue';
            const count = ref(2);
            const doubled = computed(() => count.value * 2);
            const writable = computed({ get: () => count.value, set: value => count.value = value });
            function read() { return doubled.value; }
            const hidden = ref('private');
            swDefinePublic({ count, doubled, writable, read });
        `,
            {
                created() {
                    callback(this.$data, this.$options);
                },
                methods: {
                    read(this: { $super: (name: string) => number }) {
                        return this.$super('read') + 1;
                    },
                },
            },
        );
        const wrapper = mount(component);
        wrappers.push(wrapper);
        const [
            data,
            options,
        ] = callback.mock.calls[0] as [
            Record<string, unknown>,
            { methods: Record<string, unknown>; computed: Record<string, unknown> },
        ];
        expect(data).toMatchObject({ count: 2 });
        expect(data).not.toHaveProperty('hidden');
        expect(data).not.toHaveProperty('doubled');
        expect(data).not.toHaveProperty('writable');
        expect(data).not.toHaveProperty('read');
        expect(options.methods?.read).toEqual(expect.any(Function));
        expect(options.computed?.doubled).toEqual(expect.any(Function));
        expect(options.computed?.writable).toHaveProperty('get', expect.any(Function));
        expect(options.computed?.writable).toHaveProperty('set', expect.any(Function));
        expect(wrapper.text()).toBe('5');
    });

    it('routes a public ref alias back to internal base reads and writes', async () => {
        const component = compileComponent(
            `
            import { ref } from 'vue';
            const count = ref(2);
            const oldCount = count;
            function read() { return count.value; }
            function increment() { count.value++; }
            swDefinePublic({ oldCount, read, increment });
        `,
            {
                data() {
                    return { oldCount: 7 };
                },
            },
            '<button @click="increment">{{ read() }}</button>',
        );
        const wrapper = mount(component);
        wrappers.push(wrapper);
        expect(wrapper.text()).toBe('7');
        await wrapper.trigger('click');
        expect(wrapper.text()).toBe('8');
    });

    it('infers the actual codemod default-import composable shape as native methods', () => {
        const callback = jest.fn();
        const component = compileComponent(
            `
            import usePlaceholder from 'src/app/composables/use-placeholder';
            const { placeholder } = usePlaceholder();
            function read() { return placeholder(null, 'name', 'base'); }
            swDefinePublic({ placeholder, read });
        `,
            {
                created(this: { $options: { methods?: Record<string, unknown> } }) {
                    callback(this.$options.methods?.placeholder);
                },
            },
        );
        const wrapper = mount(component);
        wrappers.push(wrapper);
        expect(callback).toHaveBeenCalledWith(expect.any(Function));
        expect(wrapper.text()).toBe('base');
    });

    it('retains a function-valued ref as data', () => {
        const callback = jest.fn();
        const component = compileComponent(
            `
            import { ref } from 'vue';
            const handler = ref(() => 'data');
            function read() { return handler.value(); }
            swDefinePublic({ handler, read });
        `,
            {
                created(this: { $data: Record<string, unknown>; $options: { methods?: Record<string, unknown> } }) {
                    callback(this.$data.handler, this.$options.methods?.handler);
                },
            },
        );
        const wrapper = mount(component);
        wrappers.push(wrapper);
        expect(callback).toHaveBeenCalledWith(expect.any(Function), undefined);
        expect(wrapper.text()).toBe('data');
    });
});
