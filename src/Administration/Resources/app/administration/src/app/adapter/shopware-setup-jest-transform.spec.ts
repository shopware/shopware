/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { _overridesMap } from 'src/app/adapter/composition-extension-system';
import ShopwareSetupJestTransformOverride from './_mocks_/shopware-setup-jest-transform-override.vue';
import ShopwareSetupJestTransformBase from './_mocks_/shopware-setup-jest-transform-base.vue';

describe('test/transformer/shopwareSetupVueTransformer', () => {
    afterAll(() => {
        delete _overridesMap['sw-jest-transform-fixture'];
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
        expect(wrapper.emitted('save')).toEqual([
            [
                2,
            ],
        ]);
    });
});
