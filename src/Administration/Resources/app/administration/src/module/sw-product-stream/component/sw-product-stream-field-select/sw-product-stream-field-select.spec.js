import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-product-stream/component/sw-product-stream-field-select';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';

function createWrapper(propsData = {}) {
    return shallowMount(Shopware.Component.build('sw-product-stream-field-select'), {
        provide: {
            conditionDataProviderService: {
                isPropertyInAllowList: () => true,
                allowedJsonAccessors: {
                    'json.test': {
                        value: 'json.test',
                        type: 'string',
                        trans: 'jsontest'
                    }
                }
            },
            productCustomFields: []
        },
        stubs: {
            'sw-arrow-field': true,
            'sw-single-select': Shopware.Component.build('sw-single-select'),
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-field-error': Shopware.Component.build('sw-field-error'),
            'sw-icon': true
        },
        propsData: {
            index: 0,
            definition: {
                entity: 'product',
                properties: {}
            },
            ...propsData,
        }
    });
}

describe('src/module/sw-product-stream/component/sw-product-stream-field-select', () => {
    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have a disabled prop', async () => {
        const wrapper = createWrapper();
        expect(wrapper.props('disabled')).toBe(false);

        await wrapper.setProps({ disabled: true });

        expect(wrapper.props('disabled')).toBe(true);
    });

    it('should return correct options with json accessor', () => {
        const wrapper = createWrapper();
        wrapper.vm.$nextTick();

        expect(wrapper.vm.options).toEqual([{
            label: 'jsontest',
            value: 'json.test'
        }]);
    });

    it('should return gray arrow primary color without error', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.arrowPrimaryColor).toBe('#758ca3');
    });

    it('should return red arrow primary color with error', () => {
        const wrapper = createWrapper({
            hasError: true,
        });

        expect(wrapper.vm.arrowPrimaryColor).toBe('#de294c');
    });
});
