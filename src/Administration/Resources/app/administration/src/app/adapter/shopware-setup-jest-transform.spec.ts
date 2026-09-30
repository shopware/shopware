/**
 * @sw-package framework
 */

import { defineComponent, ref } from 'vue';
import { mount } from '@vue/test-utils';
import { _overridesMap } from 'src/app/adapter/composition-extension-system';
// Registers the `blockOverride` store as a side effect.
import 'src/app/store/block-override.store';
import createDataScopeFixture from 'src/app/component/structure/sw-block-override/sw-block-override.spec/test-utils/create-data-scope-fixture';
import ShopwareSetupJestTransformOverride from './_mocks_/sw-jest-transform-fixture.override.vue';
import ShopwareSetupJestTransformBase from './_mocks_/sw-jest-transform-fixture.vue';
import ShopwareSetupJestTransformBlockOverride from './_mocks_/sw-jest-transform-block-fixture.override.vue';
import ShopwareSetupJestTransformBlockBase from './_mocks_/sw-jest-transform-block-fixture.vue';

describe('test/transformer/shopwareSetupVueTransformer', () => {
    beforeEach(() => {
        delete _overridesMap['sw-jest-transform-fixture'];
        delete _overridesMap['sw-jest-transform-block-fixture'];
    });

    afterAll(() => {
        delete _overridesMap['sw-jest-transform-fixture'];
        delete _overridesMap['sw-jest-transform-block-fixture'];
    });

    it('transforms and mounts Shopware setup Vue files through the real Jest Vue transformer', async () => {
        mount(ShopwareSetupJestTransformOverride);

        const wrapper = mount(ShopwareSetupJestTransformBase, {
            props: {
                label: 'Transformed',
            },
        });

        await flushPromises();
        await wrapper.get('button').trigger('click');

        expect(wrapper.text()).toBe('Transformed: 2');
        expect(wrapper.emitted('save')).toEqual([[2]]);
    });

    it('gives a parent holding a template ref the swDefinePublic bindings and the props', async () => {
        const parent = defineComponent({
            components: {
                ShopwareSetupJestTransformBase,
            },
            setup() {
                return {
                    label: ref('Counter'),
                };
            },
            template: '<shopware-setup-jest-transform-base ref="child" :label="label" />',
        });

        const wrapper = mount(parent);
        const child = wrapper.vm.$refs.child as Record<string, unknown>;

        // `count` is the fixture's only swDefinePublic() entry, so it is the only setup binding a
        // parent sees - reads are ref-unwrapped and writes reach the component's own state.
        expect(child.count).toBe(1);

        child.count = 5;
        await flushPromises();

        expect(wrapper.text()).toBe('Counter: 5');

        // Props ride along with the public bindings, so lowering a component does not take away the
        // prop reads a parent already had, and a later prop value is the one that reads back.
        expect(child.label).toBe('Counter');

        wrapper.vm.label = 'Renamed';
        await flushPromises();

        expect(child.label).toBe('Renamed');

        expect(child.displayedLabel).toBeUndefined();
    });

    it('keeps an exposed prop read-only for the parent', () => {
        const parent = defineComponent({
            components: {
                ShopwareSetupJestTransformBase,
            },
            template: '<shopware-setup-jest-transform-base ref="child" label="Counter" />',
        });

        const wrapper = mount(parent);
        const child = wrapper.vm.$refs.child as Record<string, unknown>;
        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

        child.label = 'Rewritten';

        expect(child.label).toBe('Counter');
        expect(warn).toHaveBeenCalledWith(expect.stringContaining('computed value is readonly'));

        warn.mockRestore();
    });

    it('resolves override-local state inside dynamic directive arguments of <sw-block extends> content', async () => {
        const global = {
            components: {
                'sw-block': await wrapTestComponent('sw-block', { sync: true }),
            },
            plugins: [createDataScopeFixture()],
        };

        mount(ShopwareSetupJestTransformBlockOverride, { global });

        const wrapper = mount(ShopwareSetupJestTransformBlockBase, { global });

        await flushPromises();

        // `@[eventName]` and `:[labelAttribute]` read override-local bindings through the rewritten
        // slot-scope path, which Vue would cut short at the first `]` if the path contained one.
        const button = wrapper.get('button');

        expect(button.attributes('aria-label')).toBe('Increment');
        expect(wrapper.find('.default-content').exists()).toBe(false);

        await button.trigger('click');

        expect(button.text()).toBe('1');
    });
});
