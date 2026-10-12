import { DOMWrapper, mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */
function createLineItems(count) {
    return Array.from({ length: count }, (_, index) => ({
        id: `line-item-${index + 1}`,
        type: 'custom',
        label: `Item ${index + 1}`,
        quantity: 1,
        payload: [],
        priceDefinition: {
            price: 10,
        },
        price: {
            quantity: 1,
            totalPrice: 10,
            unitPrice: 10,
            calculatedTaxes: [],
            taxRules: [],
        },
        isNew: () => false,
    }));
}

async function createWrapper(lineItems) {
    return mount(await wrapTestComponent('sw-order-line-items-grid', { sync: true }), {
        attachTo: document.body,
        props: {
            order: {
                price: {
                    taxStatus: '',
                },
                currency: {
                    isoCode: 'EUR',
                },
                lineItems,
                taxStatus: '',
                itemRounding: {
                    decimals: 2,
                },
            },
            context: {
                authToken: {
                    access: 'token',
                },
            },
            isLoading: false,
        },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => ({ isNew: () => true, id: Shopware.Utils.createId() }),
                    }),
                },
                orderService: {},
            },
            stubs: {
                'sw-container': await wrapTestComponent('sw-container', { sync: true }),
                'sw-card-filter': await wrapTestComponent('sw-card-filter', { sync: true }),
                'sw-simple-search-field': await wrapTestComponent('sw-simple-search-field', { sync: true }),
                'sw-data-grid': await wrapTestComponent('sw-data-grid', { sync: true }),
                'sw-pagination': await wrapTestComponent('sw-pagination', { sync: true }),
                'sw-button-group': await wrapTestComponent('sw-button-group', { sync: true }),
                'sw-context-button': await wrapTestComponent('sw-context-button', { sync: true }),
                'sw-context-menu': await wrapTestComponent('sw-context-menu', { sync: true }),
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item', { sync: true }),
                'sw-popover': await wrapTestComponent('sw-popover', { sync: true }),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                'sw-context-menu-divider': true,
                'sw-checkbox-field': true,
                'sw-data-grid-settings': true,
                'sw-data-grid-column-boolean': true,
                'sw-data-grid-inline-edit': true,
                'sw-data-grid-skeleton': true,
                'sw-product-variant-info': true,
                'sw-order-product-select': true,
                'sw-order-nested-line-items-modal': true,
                'sw-modal': true,
                'sw-provide': await wrapTestComponent('sw-provide', { sync: true }),
                'router-link': true,
                'mt-number-field': true,
            },
            mocks: {
                $t: (key) => key,
            },
            directives: {
                tooltip: {},
            },
        },
    });
}

function getRowLabels(wrapper) {
    return wrapper
        .findAll('.sw-data-grid__body .sw-data-grid__row')
        .map((row) => row.find('.sw-data-grid__cell--label').text());
}

async function goToPage(wrapper, pageNumber) {
    const pageButton = wrapper.findAll('.sw-pagination__list-button').find((button) => button.text() === String(pageNumber));

    await pageButton.trigger('click');
    await flushPromises();
}

describe('module/sw-order/component/sw-order-line-items-grid/pagination', () => {
    beforeEach(() => {
        global.activeAclRoles = ['order.viewer', 'order.editor'];
    });

    it('does not show the pagination for ten or fewer items', async () => {
        const wrapper = await createWrapper(createLineItems(10));
        await flushPromises();

        expect(getRowLabels(wrapper)).toHaveLength(10);
        expect(wrapper.find('.sw-pagination').exists()).toBe(false);
    });

    it('shows the first ten items and the pagination for more than ten items', async () => {
        const wrapper = await createWrapper(createLineItems(12));
        await flushPromises();

        const labels = getRowLabels(wrapper);

        expect(labels).toHaveLength(10);
        expect(labels[0]).toBe('Item 1');
        expect(wrapper.find('.sw-pagination').exists()).toBe(true);
    });

    it('shows the remaining items on the next page', async () => {
        const wrapper = await createWrapper(createLineItems(12));
        await flushPromises();

        await goToPage(wrapper, 2);

        expect(getRowLabels(wrapper)).toEqual([
            'Item 11',
            'Item 12',
        ]);
    });

    it('shows more items per page when the page size changes', async () => {
        const wrapper = await createWrapper(createLineItems(12));
        await flushPromises();

        await wrapper.find('.sw-pagination__per-page .mt-select__selection').trigger('click');
        await flushPromises();

        document.body.querySelector('.mt-select-option--25').click();
        await flushPromises();

        expect(getRowLabels(wrapper)).toHaveLength(12);
    });

    it('goes back to the first page when searching', async () => {
        jest.useFakeTimers();

        const wrapper = await createWrapper(createLineItems(12));
        await flushPromises();

        await goToPage(wrapper, 2);

        await wrapper.find('.sw-card-filter input').setValue('Item 1');
        jest.runAllTimers();
        await flushPromises();

        expect(getRowLabels(wrapper)).toEqual([
            'Item 1',
            'Item 10',
            'Item 11',
            'Item 12',
        ]);

        jest.useRealTimers();
    });

    it('goes back to the first page when adding an item', async () => {
        const wrapper = await createWrapper(createLineItems(12));
        await flushPromises();

        await goToPage(wrapper, 2);

        await wrapper.find('.sw-order-line-items-grid__actions-container-add-product-btn').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__body .sw-data-grid__row--0').classes()).toContain('is--inline-edit');
        expect(wrapper.find('.sw-pagination__list-button.is-active').text()).toBe('1');
    });

    it('deletes the clicked unsaved item on a later page', async () => {
        const lineItems = createLineItems(12).map((lineItem) => ({ ...lineItem, isNew: () => true }));
        const wrapper = await createWrapper(lineItems);
        await flushPromises();

        await goToPage(wrapper, 2);

        await wrapper.find('.sw-data-grid__body .sw-data-grid__row--0 .sw-context-button').trigger('click');
        await flushPromises();

        await new DOMWrapper(document.body).get('.sw_order_line_items_grid-item__delete-action').trigger('click');
        await flushPromises();

        expect(lineItems.map((lineItem) => lineItem.id)).not.toContain('line-item-11');
        expect(lineItems).toHaveLength(11);
    });
});
