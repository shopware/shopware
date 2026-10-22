/**
 * @sw-package inventory
 */
import { mount, type VueWrapper } from '@vue/test-utils';
import type { Entity } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/Entity';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';

const manufacturer = {
    id: '018f5f4e8c1a70c58f3b2d6e9a4c7b10',
    name: 'Manufacturer',
    description: 'Location A',
    translated: { description: 'Translated location' },
} as unknown as Entity<'product_manufacturer'>;

const customer = {
    id: '018f5f4e8c1a70c58f3b2d6e9a4c7b11',
    firstName: 'Ada',
    lastName: 'Lovelace',
} as unknown as Entity<'customer'>;

type WrapperOptions = {
    labelProperty?: string | string[];
    entity?: 'product_manufacturer' | 'customer';
};

type ProductStreamValueVm = {
    searchTerm: string;
    customFieldCriteria: Criteria;
    getCustomFieldLabel: (item: Entity<'product_manufacturer'> | Entity<'customer'>) => string;
};

async function createWrapper({ labelProperty, entity = 'product_manufacturer' }: WrapperOptions = {}) {
    const item = entity === 'customer' ? customer : manufacturer;
    const collection =
        entity === 'customer'
            ? new EntityCollection('/test', 'customer', Shopware.Context.api, new Criteria(), [customer])
            : new EntityCollection('/test', 'product_manufacturer', Shopware.Context.api, new Criteria(), [manufacturer]);
    const repository = {
        entityName: entity,
        route: '/test',
        get: () => Promise.resolve(item),
        search: () => Promise.resolve(collection),
    };

    return mount(await wrapTestComponent('sw-product-stream-value', { sync: true }), {
        props: {
            fieldName: 'customFields.location',
            condition: { type: 'equals', value: item.id },
            definition: { entity: 'product', getField: () => undefined, isJsonField: () => false },
        },
        global: {
            provide: {
                repositoryFactory: { create: () => repository },
                conditionDataProviderService: { getOperatorSet: () => [], allowedJsonAccessors: {} },
                productCustomFields: {
                    location: {
                        type: 'select',
                        config: {
                            customFieldType: 'entity',
                            entity,
                            labelProperty,
                        },
                    },
                },
                productTypes: [],
            },
            stubs: {
                'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                'sw-label': await wrapTestComponent('sw-label'),
                'sw-product-variant-info': await wrapTestComponent('sw-product-variant-info'),
                'sw-popover': { template: '<div><slot /></div>' },
                'sw-popover-deprecated': { template: '<div><slot /></div>' },
                'sw-loader': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
            },
        },
    });
}

describe('src/module/sw-product-stream/component/sw-product-stream-value/sw-product-stream-value.custom-field-label.spec.ts', () => {
    let wrapper: VueWrapper;

    afterEach(() => {
        wrapper?.unmount();
    });

    it('uses the configured labelProperty', async () => {
        wrapper = await createWrapper({ labelProperty: 'description' });
        await flushPromises();

        // The selected value uses the translated configured property first.
        expect(wrapper.text()).toContain('Translated location');
        expect(wrapper.text()).not.toContain('Location A');

        // The selector's default result renderer uses the direct property.
        await wrapper.get('.sw-select__selection').trigger('click');
        await flushPromises();
        const option = wrapper.get('.sw-select-result');
        expect(option.text()).toContain('Location A');
    });

    it('uses name when labelProperty is not configured', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        expect(wrapper.text()).toContain('Manufacturer');
    });

    it('creates a query for each configured label property', async () => {
        wrapper = await createWrapper({
            entity: 'customer',
            labelProperty: ['firstName', 'lastName'],
        });
        const viewModel = wrapper.vm as unknown as ProductStreamValueVm;
        viewModel.searchTerm = 'Ada';

        expect(viewModel.customFieldCriteria.queries).toEqual([
            {
                score: 500,
                query: { type: 'contains', field: 'firstName', value: 'Ada' },
            },
            {
                score: 500,
                query: { type: 'contains', field: 'lastName', value: 'Ada' },
            },
        ]);
        expect(viewModel.getCustomFieldLabel(customer)).toBe('Ada Lovelace');
    });
});
