import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/component/sw-sales-channel-products-assignment-dynamic-product-groups';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/select/entity/sw-entity-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/base/sw-highlight-text';
import 'src/app/component/entity/sw-product-stream-grid-preview';
import 'src/app/component/base/sw-empty-state';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/form/sw-checkbox-field';

function createWrapper() {
    const productStreamMock = {
        id: 1,
        name: 'Very cheap pc parts',
        apiFilter: ['foo', 'bar'],
        invalid: false
    };

    const productsMock = [
        { id: 1, name: 'Product 1', price: [{ currencyId: 'uuid1337', gross: 444 }] },
        { id: 2, name: 'Product 2', price: [{ currencyId: 'uuid1337', gross: 444 }] }
    ];
    productsMock.total = 4;
    productsMock.criteria = {
        page: 1,
        limit: 25
    };

    const currencyMock = {
        id: 'uuid1337',
        name: 'Euro',
        isoCode: 'EUR',
        isSystemCurrency: true,
        symbol: '€'
    };

    return shallowMount(Shopware.Component.build('sw-sales-channel-products-assignment-dynamic-product-groups'), {
        stubs: {
            'sw-container': true,
            'sw-card': {
                template: '<div><slot></slot><slot name="grid"></slot></div>'
            },
            'sw-alert': true,
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-field-error': true,
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-field': Shopware.Component.build('sw-field'),
            'sw-entity-single-select': Shopware.Component.build('sw-entity-single-select'),
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-empty-state': true,
            'sw-icon': true,
            'sw-product-stream-grid-preview': Shopware.Component.build('sw-product-stream-grid-preview'),
            'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
            'sw-select-result': Shopware.Component.build('sw-select-result'),
            'sw-loader': true,
            'sw-popover': true,
            'sw-highlight-text': Shopware.Component.build('sw-highlight-text'),
            'sw-simple-search-field': true,
            'sw-data-grid': Shopware.Component.build('sw-data-grid'),
            'sw-data-grid-skeleton': true,
            'sw-pagination': true,
            'sw-data-grid-column-boolean': true,
            'router-link': true,
            'sw-product-variant-info': true,
            'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field')
        },
        provide: {
            repositoryFactory: {
                create: (entity) => {
                    return {
                        search: () => Promise.resolve(entity === 'product' ? productsMock : [productStreamMock]),
                        get: () => Promise.resolve(entity === 'currency' ? currencyMock : productStreamMock)
                    };
                }
            }
        },
        propsData: {
            salesChannel: {
                id: 1,
                name: 'Headless'
            }
        }
    });
}

describe('src/module/sw-sales-channel/component/sw-sales-channel-products-assignment-single-products', () => {
    it('should load product stream preview when changing product group', async () => {
        const wrapper = createWrapper();

        const loadProductStreamPreviewMock = jest.spyOn(wrapper.vm, 'loadProductStreamPreview');

        await wrapper.find('.sw-sales-channel-products-assignment-dynamic-product-groups__product-stream-select .sw-select__selection').trigger('click');

        await wrapper.vm.$nextTick();

        const results = wrapper.findAll('.sw-select-result').at(0);
        await results.trigger('click');

        await wrapper.vm.$nextTick();

        expect(loadProductStreamPreviewMock).toHaveBeenCalled();
    });

    it('should emit selected products', async () => {
        const wrapper = createWrapper();

        await wrapper.find('.sw-sales-channel-products-assignment-dynamic-product-groups__product-stream-select .sw-select__selection').trigger('click');

        await wrapper.vm.$nextTick();

        const results = wrapper.findAll('.sw-select-result').at(0);
        await results.trigger('click');

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        await wrapper.find('.sw-data-grid__select-all .sw-field__checkbox input').trigger('click');
        expect(wrapper.emitted()['selection-change'][0]).toEqual([
            {
                1: { id: 1, name: 'Product 1', price: [{ currencyId: 'uuid1337', gross: 444 }] },
                2: { id: 2, name: 'Product 2', price: [{ currencyId: 'uuid1337', gross: 444 }] }
            },
            'groupProducts'
        ]);
    });
});
