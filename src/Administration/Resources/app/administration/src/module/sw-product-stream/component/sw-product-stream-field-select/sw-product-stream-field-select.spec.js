/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';

async function createWrapper(propsData = {}) {
    return mount(
        await wrapTestComponent('sw-product-stream-field-select', {
            sync: true,
        }),
        {
            props: {
                index: 0,
                definition: {
                    entity: 'product',
                    properties: {},
                },
                ...propsData,
            },
            global: {
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
                    'sw-single-select': await wrapTestComponent('sw-single-select'),
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': await wrapTestComponent('sw-field-error'),
                },
            },
        },
    );
}

describe('src/module/sw-product-stream/component/sw-product-stream-field-select', () => {
    it('should have a disabled prop', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.props('disabled')).toBe(false);

        await wrapper.setProps({ disabled: true });

        expect(wrapper.props('disabled')).toBe(true);
    });

    it('should return correct options with json accessor', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.options).toEqual([
            {
                label: 'jsontest',
                value: 'json.test',
            },
        ]);
    });
});
