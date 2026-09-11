/** @sw-package framework */
/* eslint-disable @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-return, @typescript-eslint/no-unsafe-argument */
import { mount, type VueWrapper } from '@vue/test-utils';
import { h, KeepAlive, nextTick, ref, type SetupContext } from 'vue';
import { compileScript, parse } from '@vue/compiler-sfc';
import { transpileModule, ModuleKind, ScriptTarget } from 'typescript';
import { transformOrFail } from '../../../../build/vue-setup-transform/index.spec/helpers';
import { createExtendableSetup } from '../composition-extension-system';
import { prepareLegacyComponent } from './component-definition';

function shim(setup: () => object, mixins: any[], options: any = {}) {
    return prepareLegacyComponent(
        'native-options-contract',
        {
            __swExtendable: true,
            render(this: any) {
                return h('div', String(this.count));
            },
            setup(props: any, context: SetupContext) {
                return createExtendableSetup(
                    { name: 'native-options-contract', props, context } as any,
                    () => ({ public: setup() }) as any,
                );
            },
            ...options,
        },
        mixins.map((resolvedConfig) => ({ resolvedConfig })) as any,
    );
}
function control(mixins: any[], options: any = {}) {
    return {
        render(this: any) {
            return h('div', String(this.count));
        },
        data: () => ({ count: 1 }),
        mixins,
        ...options,
    };
}

describe('native Options contracts across the SFC bridge', () => {
    const wrappers = new Set<VueWrapper>();
    function render(component: any, options = {}) {
        const wrapper = mount(component, options);
        wrappers.add(wrapper);
        return wrapper;
    }
    function cleanup(wrapper: VueWrapper) {
        wrapper.unmount();
        wrappers.delete(wrapper);
    }
    afterEach(() => {
        wrappers.forEach(cleanup);
    });

    it('keeps deleting a declared root $data property observable through this and the render', async () => {
        const observations = [];
        for (const component of [
            control([]),
            shim(() => ({ count: ref(1) }), [], { legacyOptionsMembers: { count: 'data' } }),
        ]) {
            const wrapper = render(component);
            const vm = wrapper.vm as any;
            delete vm.$data.count;
            await nextTick();
            observations.push({ value: vm.count, text: wrapper.text(), inData: 'count' in vm.$data });
            cleanup(wrapper);
        }
        expect(observations[1]).toEqual(observations[0]);
    });

    it('keeps Object.assign reset through $data shared with rendered and computed state', async () => {
        const observations = [];
        const mixins = [
            {
                methods: {
                    reset(this: any) {
                        Object.assign(this.$data, { count: 9 });
                    },
                },
            },
        ];
        for (const component of [
            control(mixins),
            shim(() => ({ count: ref(1) }), mixins, { legacyOptionsMembers: { count: 'data' } }),
        ]) {
            const wrapper = render(component);
            const vm = wrapper.vm as any;
            vm.reset();
            await nextTick();
            observations.push({ value: vm.count, text: wrapper.text(), data: vm.$data.count });
            cleanup(wrapper);
        }
        expect(observations[1]).toEqual(observations[0]);
    });

    it('deduplicates shared mixin hooks and watches in a diamond', async () => {
        const observations = [];
        for (const native of [
            true,
            false,
        ]) {
            const calls: string[] = [];
            const shared = {
                created() {
                    calls.push('created');
                },
                watch: {
                    count() {
                        calls.push('watch');
                    },
                },
            };
            const mixins = [
                { mixins: [shared] },
                { mixins: [shared] },
            ];
            const wrapper = render(native ? control(mixins) : shim(() => ({ count: ref(1) }), mixins));
            (wrapper.vm as any).count++;
            await nextTick();
            observations.push(calls);
            cleanup(wrapper);
        }
        expect(observations[1]).toEqual(observations[0]);
    });

    it('updates added props and excludes declared listeners from attrs', async () => {
        const observations = [];
        const mixins = [{ props: { label: { type: String, default: 'default' } }, emits: ['changed'] }];
        for (const component of [
            control(mixins),
            shim(() => ({ count: ref(1) }), mixins),
        ]) {
            const wrapper = render(component, { attrs: { onChanged() {}, title: 'title' } });
            await wrapper.setProps({ label: 'updated' });
            const vm = wrapper.vm as any;
            observations.push({ label: vm.label, attrs: Object.keys(vm.$attrs), props: vm.$props });
            cleanup(wrapper);
        }
        expect(observations[1]).toEqual(observations[0]);
    });

    it('preserves KeepAlive hooks and resumes updates after activation', async () => {
        const observations = [];
        for (const native of [
            true,
            false,
        ]) {
            const calls: string[] = [];
            const mixins = [
                {
                    activated() {
                        calls.push('activated');
                    },
                    deactivated() {
                        calls.push('deactivated');
                    },
                },
            ];
            const child = native ? control(mixins) : shim(() => ({ count: ref(1) }), mixins);
            const visible = ref(true);
            const wrapper = render({
                render() {
                    return h(KeepAlive, () => (visible.value ? h(child) : null));
                },
            });
            visible.value = false;
            await nextTick();
            visible.value = true;
            await nextTick();
            cleanup(wrapper);
            observations.push(calls);
        }
        expect(observations[1]).toEqual(observations[0]);
    });

    it('captures descendant errors and retains false propagation suppression', () => {
        const observations = [];
        for (const native of [
            true,
            false,
        ]) {
            const calls: string[] = [];
            const mixins = [
                {
                    errorCaptured(this: any, error: Error) {
                        calls.push(`${error.message}:${this.count}`);
                        return false;
                    },
                },
            ];
            const child = {
                render() {
                    throw new Error('child');
                },
            };
            const options = { render: () => h(child) };
            const wrapper = render(native ? control(mixins, options) : shim(() => ({ count: ref(1) }), mixins, options));
            cleanup(wrapper);
            observations.push(calls);
        }
        expect(observations[1]).toEqual(observations[0]);
    });

    function compileFixture(mixins: any[] = []) {
        const source = `<script data-sfc-migration-module lang="ts">export default { legacyOptionsMembers: { count: 'data', doubled: 'computed' } };</script><script setup lang="ts">
    import { ref, computed } from 'vue';
    const count = ref(1);
    const doubled = computed(() => count.value * 2);
    function read() { return count.value; }
    swDefinePublic({ count, doubled, read });
    </script><template><div>{{ count }}|{{ doubled }}</div></template>`;
        const { code } = transformOrFail(source, 'native-options-contract.vue');
        const compiled = compileScript(parse(code).descriptor, { id: 'native-options-contract', inlineTemplate: true });
        const javascript = transpileModule(compiled.content, {
            compilerOptions: { module: ModuleKind.CommonJS, target: ScriptTarget.ES2022 },
        }).outputText;
        const exports: any = {};
        // Execute only the Vue compiler output of this trusted inline fixture.
        // eslint-disable-next-line @typescript-eslint/no-implied-eval
        new Function('require', 'exports', javascript)(require, exports);
        return prepareLegacyComponent(
            'native-options-contract',
            exports.default,
            mixins.map((resolvedConfig) => ({ resolvedConfig })) as any,
        );
    }

    it('keeps root $data deletion visible in compiled SFC render and base computed reads', async () => {
        const values = [];
        for (const component of [
            control([], {
                computed: {
                    doubled(this: any) {
                        return this.count * 2;
                    },
                },
                render(this: any) {
                    return h('div', `${this.count ?? ''}|${this.doubled}`);
                },
            }),
            compileFixture(),
        ]) {
            const wrapper = render(component);
            delete (wrapper.vm as any).$data.count;
            await nextTick();
            values.push({ text: wrapper.text(), value: (wrapper.vm as any).count, doubled: (wrapper.vm as any).doubled });
            cleanup(wrapper);
        }
        expect(values[1]).toEqual(values[0]);
    });

    it('keeps replacing $data root value visible in compiled SFC reads', async () => {
        const wrapper = render(compileFixture() as any);
        Object.assign((wrapper.vm as any).$data, { count: 9 });
        await nextTick();
        expect(wrapper.text()).toBe('9|18');
        expect((wrapper.vm as any).read()).toBe(9);
        cleanup(wrapper);
    });
});
