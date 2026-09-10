import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */
async function createWrapper() {
    return mount(await wrapTestComponent('sw-order-line-items-grid', { sync: true }), {
        props: {
            order: {
                price: {
                    taxStatus: '',
                },
                currency: {
                    isoCode: 'EUR',
                },
                lineItems: [],
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
                        delete: jest.fn(() => Promise.resolve()),
                    }),
                },
                orderService: {},
                feature: {
                    isActive: () => true,
                },
            },
            stubs: {
                'sw-container': await wrapTestComponent('sw-container', { sync: true }),
                'sw-button-group': {
                    template: '<div class="sw-button-group"><slot></slot></div>',
                },
                'sw-context-button': {
                    template: '<div class="sw-context-button"><slot></slot></div>',
                },
                'sw-context-menu-divider': true,
                'sw-context-menu-item': {
                    emits: ['click'],
                    template: '<div class="sw-context-menu-item" @click="$emit(\'click\')"><slot></slot></div>',
                },
                'sw-card-filter': true,
                'sw-checkbox-field': true,
                'sw-data-grid': await wrapTestComponent('sw-data-grid', { sync: true }),
                'sw-data-grid-settings': true,
                'sw-product-variant-info': await wrapTestComponent('sw-product-variant-info', { sync: true }),
                'router-link': {
                    template: '<a class="router-link" href="#"><slot></slot></a>',
                    props: ['to'],
                },
                'mt-number-field': true,
                'sw-order-product-select': true,
                'sw-modal': true,
                'sw-order-nested-line-items-modal': true,
                'sw-data-grid-column-boolean': true,
                'sw-data-grid-inline-edit': true,
                'sw-data-grid-skeleton': true,
                'sw-base-field': true,
                'sw-field-error': true,
                'sw-highlight-text': true,
                'sw-provide': { template: '<slot/>', inheritAttrs: false },
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

describe('module/sw-order/component/sw-order-line-items-grid/add-line-item', () => {
    beforeEach(() => {
        global.activeAclRoles = [
            'order.viewer',
            'order.editor',
            'orders.create_discounts',
        ];
    });

    it.each([
        [
            'product',
            '.sw-order-line-items-grid__actions-container-add-product-btn',
        ],
        [
            'custom',
            '.sw-order-line-items-grid__create-custom-item',
        ],
        [
            'credit',
            '.sw-order-line-items-grid__can-create-discounts-button',
        ],
    ])('opens the inline edit of a newly added %s item', async (type, selector) => {
        const wrapper = await createWrapper();

        await wrapper.find(selector).trigger('click');
        await flushPromises();

        const firstRow = wrapper.find('.sw-data-grid__row--0');

        expect(firstRow.classes()).toContain('is--inline-edit');
        expect(firstRow.find('.sw-data-grid__inline-edit-save').exists()).toBe(true);
    });

    it('discards a new item without asking the order to recalculate when the inline edit is cancelled', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-order-line-items-grid__actions-container-add-product-btn').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-data-grid__row--0 .sw-data-grid__inline-edit-cancel').trigger('click');
        await flushPromises();

        expect(wrapper.vm.order.lineItems).toHaveLength(0);
        expect(wrapper.emitted('item-cancel')).toBeUndefined();
    });

    it('keeps the row that is already being edited when another item is added', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-order-line-items-grid__actions-container-add-product-btn').trigger('click');
        await flushPromises();

        const firstItemId = wrapper.vm.order.lineItems[0].id;

        await wrapper.find('.sw-order-line-items-grid__create-custom-item').trigger('click');
        await flushPromises();

        expect(wrapper.vm.$refs.dataGrid.currentInlineEditId).toBe(firstItemId);
        expect(wrapper.find('.sw-data-grid__row--0').classes()).not.toContain('is--inline-edit');
        expect(wrapper.find('.sw-data-grid__row--1').classes()).toContain('is--inline-edit');
    });
});
