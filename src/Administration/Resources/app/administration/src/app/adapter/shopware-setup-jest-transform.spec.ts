/**
 * @sw-package framework
 */

import { defineComponent, ref } from 'vue';
import { mount } from '@vue/test-utils';
import { _overridesMap } from 'src/app/adapter/composition-extension-system';
import './_mocks_/sw-jest-transform-fixture.override.vue';
import './_mocks_/sw-jest-late-binding-fixture.override.vue';
import ShopwareSetupJestTransformBase from './_mocks_/sw-jest-transform-fixture.vue';
import ShopwareSetupLateBindingBase from './_mocks_/sw-jest-late-binding-fixture.vue';

const registeredOverrides = new Map(_overridesMap);

function withoutOverrides(componentName: string): void {
    _overridesMap.delete(componentName);
}

describe('test/transformer/shopwareSetupVueTransformer', () => {
    afterEach(() => {
        registeredOverrides.forEach((overrides, componentName) => {
            _overridesMap.set(componentName, overrides);
        });
    });

    it('registers an override when its module is imported', () => {
        expect(registeredOverrides.get('sw-jest-transform-fixture')?.size).toBe(1);
        expect(registeredOverrides.get('sw-jest-late-binding-fixture')?.size).toBe(1);
    });

    it('transforms and mounts Shopware setup Vue files through the real Jest Vue transformer', async () => {
        const wrapper = mount(ShopwareSetupJestTransformBase, {
            props: {
                label: 'Transformed',
            },
        });

        await wrapper.get('button').trigger('click');

        expect(wrapper.text()).toBe('Transformed: 2');
        expect(wrapper.emitted('save')).toEqual([[2]]);
    });

    it('gives a parent holding a template ref the swDefinePublic bindings and the props', async () => {
        withoutOverrides('sw-jest-transform-fixture');

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

        expect(child.count).toBe(1);

        child.count = 5;
        await flushPromises();

        expect(wrapper.text()).toBe('Counter: 5');
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
        expect(warn).toHaveBeenCalledWith(expect.stringContaining('The prop "label" is exposed read-only'));

        warn.mockRestore();
    });

    describe('late binding of the base body', () => {
        it('lets the base call an overridden function', async () => {
            const wrapper = mount(ShopwareSetupLateBindingBase);

            await wrapper.get('button').trigger('click');

            expect(wrapper.get('.persisted').text()).toBe('override:overridden');
        });

        it('lets a base computed derive from an overridden computed', () => {
            const wrapper = mount(ShopwareSetupLateBindingBase);

            expect(wrapper.get('.label').text()).toBe('overridden');
            expect(wrapper.get('.internal').text()).toBe('internal sees overridden');
        });

        it('keeps the base bindings when nothing overrides them', async () => {
            withoutOverrides('sw-jest-late-binding-fixture');

            const wrapper = mount(ShopwareSetupLateBindingBase);

            await wrapper.get('button').trigger('click');

            expect(wrapper.get('.internal').text()).toBe('internal sees base');
            expect(wrapper.get('.persisted').text()).toBe('base:base');
        });
    });
});
