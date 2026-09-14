/** @sw-package framework */
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils';
import { h, ref, type RenderFunction } from 'vue';
import ComponentFactory, { type ComponentConfig } from 'src/core/factory/async-component.factory';
import { createExtendableSetup, _overridesMap } from '../composition-extension-system';
import { createLegacyComponent, resolveLegacyHotUpdate } from './component-definition';

type HotRuntime = {
    createRecord: (id: string, component: ComponentConfig) => void;
    reload: (id: string, component: ComponentConfig) => void;
    rerender: (id: string, render: RenderFunction) => void;
};
const hot = (globalThis as unknown as { __VUE_HMR_RUNTIME__: HotRuntime }).__VUE_HMR_RUNTIME__;

describe('legacy SFC hot updates through the Vue runtime', () => {
    const wrappers: VueWrapper[] = [];
    beforeEach(() => {
        ComponentFactory.getOverrideRegistry().clear();
        Object.keys(_overridesMap).forEach((key) => {
            delete _overridesMap[key];
        });
        jest.spyOn(console, 'warn').mockImplementation(() => {});
    });
    afterEach(() => {
        wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
        jest.restoreAllMocks();
    });

    function base(id: string, generation: string): ComponentConfig {
        return {
            __hmrId: id,
            __swExtendable: true,
            setup(props, context) {
                return createExtendableSetup({ name: id, props, context }, () => ({ public: { value: ref('base') } }));
            },
            render(this: { extra: string; added: string }) {
                return h('b', `${generation}:${this.extra}:${this.added}`);
            },
        };
    }

    it('retains props and stateful overrides after a script reload without duplicating callbacks', async () => {
        const id = 'legacy-script-hot-update';
        const created = jest.fn();
        ComponentFactory.override(id, {
            props: { extra: { default: 'extra' } },
            data: () => ({ added: 'plugin' }),
            created,
        });
        const original = base(id, 'first');
        hot.createRecord(id, original);
        const direct = createLegacyComponent(original, id);
        const wrapper = mount({ render: () => h(direct) });
        wrappers.push(wrapper);
        await flushPromises();
        expect(wrapper.text()).toBe('first:extra:plugin');
        const updated = await resolveLegacyHotUpdate({ default: createLegacyComponent(base(id, 'second'), id) });
        hot.reload(id, updated.default);
        await flushPromises();
        expect(wrapper.text()).toBe('second:extra:plugin');
        expect(created).toHaveBeenCalledTimes(2);
    });

    it('preserves a custom legacy render during a template-only hot update', async () => {
        const id = 'legacy-template-hot-update';
        const created = jest.fn();
        ComponentFactory.override(id, {
            data: () => ({ added: 'plugin' }),
            created,
            render(this: { added: string }) {
                return h('strong', this.added);
            },
        });
        const original = base(id, 'first');
        hot.createRecord(id, original);
        const direct = createLegacyComponent(original, id);
        const wrapper = mount({ render: () => h(direct) });
        wrappers.push(wrapper);
        await flushPromises();
        const updated = await resolveLegacyHotUpdate({ default: createLegacyComponent(base(id, 'second'), id) });
        hot.rerender(id, updated.default.render as RenderFunction);
        await flushPromises();
        expect(wrapper.get('strong').text()).toBe('plugin');
        expect(created).toHaveBeenCalledTimes(1);
    });
});
