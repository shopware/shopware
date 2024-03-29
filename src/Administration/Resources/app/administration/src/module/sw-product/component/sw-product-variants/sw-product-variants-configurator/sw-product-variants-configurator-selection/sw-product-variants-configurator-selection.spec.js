/*
 * @package inventory
 */

import { mount } from '@vue/test-utils';
// eslint-disable-next-line max-len
import swProductVariantsConfiguratorSelection from 'src/module/sw-product/component/sw-product-variants/sw-product-variants-configurator/sw-product-variants-configurator-selection';
import EntityCollection from 'src/core/data/entity-collection.data';

Shopware.Component.extend('sw-product-variants-configurator-selection', 'sw-property-search', swProductVariantsConfiguratorSelection);

async function createWrapper() {
    return mount(await wrapTestComponent('sw-product-variants-configurator-selection', { sync: true }), {
        props: {
            options: [],
            product: {},
        },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve(),
                        create: () => Promise.resolve(),
                    }),
                },
                validationService: {},
            },
            stubs: {
                'sw-simple-search-field': await wrapTestComponent('sw-simple-search-field'),
                'sw-field': true,
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-icon': {
                    template: '<div></div>',
                },
            },
        },
    });
}

function getPropertyCollection() {
    return new EntityCollection(
        '/test-entity',
        'testEntity',
        Shopware.Context.api,
        null,
        [
            {
                id: '1',
                optionId: '1',
            },
        ],
    );
}

describe('components/base/sw-product-variants-configurator-selection', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should keep the text content when search list opens', async () => {
        const inputField = wrapper.find('.sw-field input');

        // verify that input field is empty
        expect(inputField.element.value).toBe('');

        await inputField.setValue('15');

        expect(inputField.element.value).toBe('15');
    });

    it('should prevent selection', async () => {
        await wrapper.setData({
            preventSelection: true,
        });
        jest.spyOn(wrapper.vm, 'addOptionCount');

        wrapper.vm.onOptionSelect();

        expect(wrapper.vm.addOptionCount).not.toHaveBeenCalled();
    });

    it('should remove an existing option', async () => {
        const entityCollection = getPropertyCollection();
        await wrapper.setProps({
            options: entityCollection,
        });

        wrapper.vm.onOptionSelect([], {
            id: '1',
        });

        expect(wrapper.vm.options).toHaveLength(0);
    });

    it('should add an option item', async () => {
        const entityCollection = getPropertyCollection();
        await wrapper.setProps({
            product: {
                configuratorSettings: {
                    entity: 'product-configurator-settings',
                    source: '',
                },
            },
            options: entityCollection,
        });

        wrapper.vm.onOptionSelect([], {
            id: '2',
        });

        expect(wrapper.vm.options).toHaveLength(2);
    });
});
