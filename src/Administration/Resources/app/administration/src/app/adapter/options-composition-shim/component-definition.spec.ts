/** @sw-package framework */
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h, ref } from 'vue';
import ComponentFactory, { type ComponentConfig } from 'src/core/factory/async-component.factory';
import TemplateFactory from 'src/core/factory/template.factory';
import { createExtendableSetup, _overridesMap } from '../composition-extension-system';
import { createLegacyComponent } from './component-definition';

describe('legacy definition loading', () => {
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

    it('resolves direct SFC imports before props/emits normalization and forwards refs to the actual component', async () => {
        const created = jest.fn();
        ComponentFactory.override('direct-legacy', () =>
            Promise.resolve({
                props: { label: { type: String, default: 'default' } },
                emits: ['save'],
                inheritAttrs: false,
                created,
                render(this: { label: string; $emit: (event: string) => void }) {
                    return h('button', { onClick: () => this.$emit('save') }, this.label);
                },
            }),
        );
        const base: ComponentConfig = {
            __swExtendable: true,
            setup(props, context) {
                const state = createExtendableSetup({ name: 'direct-legacy', props, context }, () => ({
                    public: { read: () => (props as Record<string, unknown>).label },
                }));
                context.expose({ read: state.read });
                return () => h('i', 'base');
            },
        };
        const direct = createLegacyComponent(base, 'direct-legacy');
        const child = ref<{ read: () => string }>();
        const save = jest.fn();
        const parent = defineComponent({
            setup: () => () => h(direct, { ref: child, title: 'no fallthrough', onSave: save }),
        });
        const wrapper = mount(parent);
        wrappers.push(wrapper);
        await flushPromises();
        expect(wrapper.get('button').text()).toBe('default');
        expect(wrapper.get('button').attributes('title')).toBeUndefined();
        expect(child.value?.read()).toBe('default');
        await wrapper.get('button').trigger('click');
        expect(save).toHaveBeenCalledTimes(1);
        expect(created).toHaveBeenCalledTimes(1);
        wrapper.unmount();
        const second = mount(parent);
        wrappers.push(second);
        await flushPromises();
        expect(second.get('button').text()).toBe('default');
        expect(created).toHaveBeenCalledTimes(2);
    });

    it('retries failed template preparation without publishing a partial config or mutating the module default', async () => {
        const original = Object.freeze({
            template: '{% block retry %}<b>retry</b>{% endblock %}',
            data: () => ({ added: true }),
        });
        const loader = jest.fn(() => Promise.resolve({ default: original }));
        const register = jest.spyOn(TemplateFactory, 'registerTemplateOverride').mockImplementationOnce(() => {
            throw new Error('retry');
        });
        const resolve = ComponentFactory.override('legacy-retry', loader);
        await expect(resolve()).rejects.toThrow('retry');
        expect(ComponentFactory.getOverrideRegistry().get('legacy-retry')?.[0].resolvedConfig).toBeUndefined();
        await expect(resolve()).resolves.toMatchObject({ name: 'legacy-retry' });
        expect(loader).toHaveBeenCalledTimes(2);
        expect(register).toHaveBeenCalledTimes(2);
        expect(original).toHaveProperty('template');
        expect(original).not.toHaveProperty('name');
    });
});
