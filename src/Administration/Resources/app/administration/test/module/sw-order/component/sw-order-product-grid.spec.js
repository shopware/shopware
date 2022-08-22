import { shallowMount, createLocalVue, enableAutoDestroy } from '@vue/test-utils';
import flushPromises from 'flush-promises';

import 'src/module/sw-order/component/sw-order-product-grid';
import 'src/app/component/entity/sw-entity-listing';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/grid/sw-pagination';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/sw-number-field';
import 'src/app/component/form/sw-text-field';

let productData = [];

function setProductData(products) {
    productData = [...products];
    productData.total = products.length;
    productData.criteria = {
        page: 1,
        limit: 5
    };
}

const products = generateProducts();

function generateProducts() {
    const items = [];

    for (let i = 1; i <= 10; i += 1) {
        items.push({
            id: i,
            name: `Product ${i}`,
            productNumber: `SW ${i}`,
            price: [{
                gross: i * 10,
                net: i * 8,
            }]
        });
    }

    return items;
}

function createWrapper() {
    const localVue = createLocalVue();
    localVue.filter('currency', v => v);

    return shallowMount(Shopware.Component.build('sw-order-product-grid'), {
        localVue,
        propsData: {
            taxStatus: 'gross',
            currency: {
                shortName: 'EUR',
                symbol: '€'
            },
            salesChannelId: '1'
        },
        stubs: {
            'sw-card': {
                template: `
                    <div class="sw-card__content">
                         <slot name="toolbar"></slot>
                        <slot name="grid"></slot>
                    </div>
                `
            },
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-field-error': true,
            'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
            'sw-number-field': Shopware.Component.build('sw-number-field'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-entity-listing': Shopware.Component.build('sw-entity-listing'),
            'sw-data-grid': Shopware.Component.build('sw-data-grid'),
            'sw-pagination': Shopware.Component.build('sw-pagination'),
            'sw-product-variant-info': true,
            'sw-data-grid-settings': true,
            'sw-data-grid-skeleton': true,
            'sw-context-button': {
                template: '<div class="sw-context-button"><slot></slot></div>'
            },
            'sw-context-menu-item': true,
            'sw-empty-state': true,
            'sw-card-filter': true,
            'sw-icon': true,
            'sw-field': true,
        },
        provide: {
            searchRankingService: () => {},
            validationService: () => {},
            repositoryFactory: {
                create: () => {
                    return {
                        search: () => Promise.resolve(productData)
                    };
                }
            }
        }
    });
}

enableAutoDestroy(afterEach);

describe('src/module/sw-order/view/sw-order-product-grid', () => {
    it('should show empty state view when there is no product', async () => {
        setProductData([]);

        const wrapper = createWrapper();
        await flushPromises();

        const emptyState = wrapper.find('sw-empty-state-stub');
        expect(emptyState.exists()).toBeTruthy();
    });

    it('should show product grid', async () => {
        setProductData(products);

        const wrapper = createWrapper();
        await flushPromises();

        const emptyState = wrapper.find('sw-empty-state-stub');
        expect(emptyState.exists()).toBeFalsy();

        const gridBody = wrapper.find('.sw-data-grid__body');
        expect(gridBody.findAll('.sw-data-grid__row').length).toEqual(products.length);
    });

    it('should show price correctly', async () => {
        setProductData(products);

        const wrapper = createWrapper();
        await flushPromises();

        const priceColumn = wrapper.find('.sw-data-grid__header').find('.sw-data-grid__cell--3');
        const firstProductRow = wrapper.find('.sw-data-grid__cell--price');

        expect(priceColumn.text()).toContain('sw-order.createBase.columnPriceGross');
        expect(firstProductRow.text()).toContain(10);

        await wrapper.setProps({
            taxStatus: 'net'
        });

        expect(priceColumn.text()).toContain('sw-order.createBase.columnPriceNet');
        expect(firstProductRow.text()).toContain(8);
    });

    it('should emit selection-change event', async () => {
        setProductData(products);

        const wrapper = createWrapper();
        await flushPromises();

        const gridBody = wrapper.find('.sw-data-grid__body');

        const amountFields = gridBody.findAll('input[name="sw-field--item-amount"]');

        await amountFields.wrappers[1].setValue(2);
        await amountFields.wrappers[1].trigger('change');

        await amountFields.wrappers[5].setValue(4);
        await amountFields.wrappers[5].trigger('change');

        expect(wrapper.emitted()['selection-change'][0][0]).toEqual([
            {
                id: 2,
                amount: 2,
                name: 'Product 2',
                productNumber: 'SW 2',
                price: [{
                    gross: 20,
                    net: 16,
                }]
            }
        ]);

        expect(wrapper.emitted()['selection-change'][1][0]).toEqual([
            {
                id: 2,
                amount: 2,
                name: 'Product 2',
                productNumber: 'SW 2',
                price: [{
                    gross: 20,
                    net: 16,
                }]
            },
            {
                id: 6,
                amount: 4,
                name: 'Product 6',
                productNumber: 'SW 6',
                price: [{
                    gross: 60,
                    net: 48,
                }]
            }
        ]);
    });

    it('should set value zero when checked in checkbox', async () => {
        setProductData(products);

        const wrapper = createWrapper();
        await flushPromises();

        const gridBody = wrapper.find('.sw-data-grid__body');

        const productSelection = gridBody.findAll('input[type="checkbox"]');
        const amountFields = gridBody.findAll('input[name="sw-field--item-amount"]');

        await productSelection.wrappers[1].setChecked();
        await productSelection.wrappers[1].trigger('change');

        expect(amountFields.wrappers[1].element.value).toEqual('0');

        await productSelection.wrappers[1].setChecked(false);
        await productSelection.wrappers[1].trigger('change');

        expect(amountFields.wrappers[1].element.value).toBe('');
    });
});
