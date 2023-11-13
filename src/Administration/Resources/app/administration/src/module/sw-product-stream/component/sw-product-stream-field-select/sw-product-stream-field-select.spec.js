/*
 * @package inventory
 */

import { shallowMount } from '@vue/test-utils';
import swProductStreamFieldSelect from 'src/module/sw-product-stream/component/sw-product-stream-field-select';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';

Shopware.Component.register('sw-product-stream-field-select', swProductStreamFieldSelect);

async function createWrapper(propsData = {}) {
    return shallowMount(await Shopware.Component.build('sw-product-stream-field-select'), {
        provide: {
            conditionDataProviderService: {
                isPropertyInAllowList: () => true,
                allowedJsonAccessors: {
                    'json.test': {
                        value: 'json.test',
                        type: 'string',
                        trans: 'jsontest',
                    },
                },
            },
            productCustomFields: [],
        },
        stubs: {
            'sw-arrow-field': true,
            'sw-single-select': await Shopware.Component.build('sw-single-select'),
            'sw-select-base': await Shopware.Component.build('sw-select-base'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-field-error': await Shopware.Component.build('sw-field-error'),
            'sw-icon': true,
        },
        propsData: {
            index: 0,
            definition: {
                entity: 'product',
                properties: {},
            },
            ...propsData,
        },
    });
}

describe('src/module/sw-product-stream/component/sw-product-stream-field-select', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have a disabled prop', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.props('disabled')).toBe(false);

        await wrapper.setProps({ disabled: true });

        expect(wrapper.props('disabled')).toBe(true);
    });

    it('should return correct options with json accessor', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.options).toEqual([{
            label: 'jsontest',
            value: 'json.test',
        }]);
    });

    it('should return gray arrow primary color without error', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.arrowPrimaryColor).toBe('#758ca3');
    });

    it('should return red arrow primary color with error', async () => {
        const wrapper = await createWrapper({
            hasError: true,
        });

        expect(wrapper.vm.arrowPrimaryColor).toBe('#de294c');
    });
});
