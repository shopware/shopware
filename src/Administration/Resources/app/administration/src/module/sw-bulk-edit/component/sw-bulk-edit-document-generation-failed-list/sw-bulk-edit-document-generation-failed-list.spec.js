/**
 * @sw-package framework
 */
import { flushPromises, mount } from '@vue/test-utils';

const rows = [
    {
        id: 'orderId',
        orderNumber: '10089',
        documentTypesLabel: 'Invoice',
    },
    {
        id: 'orderId2',
        orderNumber: '10090',
        documentTypesLabel: 'Delivery note',
    },
    {
        id: 'orderId3',
        orderNumber: '10091',
        documentTypesLabel: 'Credit note',
    },
    {
        id: 'orderId4',
        orderNumber: '10092',
        documentTypesLabel: 'Invoice',
    },
    {
        id: 'orderId5',
        orderNumber: '10093',
        documentTypesLabel: 'Delivery note',
    },
    {
        id: 'orderId6',
        orderNumber: '10094',
        documentTypesLabel: 'Invoice',
    },
];

async function createWrapper(props = {}) {
    return mount(
        await wrapTestComponent('sw-bulk-edit-document-generation-failed-list', {
            sync: true,
        }),
        {
            props: {
                rows,
                ...props,
            },
            global: {
                provide: {
                    acl: {
                        can: () => true,
                    },
                    repositoryFactory: {
                        create: () => ({
                            search: () => Promise.resolve([]),
                        }),
                    },
                    feature: {
                        isActive: () => false,
                    },
                },
                stubs: {
                    'sw-data-grid': await wrapTestComponent('sw-data-grid', { sync: true }),
                    'sw-pagination': await wrapTestComponent('sw-pagination', { sync: true }),
                    'sw-data-grid-settings': true,
                    'sw-data-grid-skeleton': true,
                    'sw-provide': true,
                    'router-link': true,
                },
            },
        },
    );
}

describe('sw-bulk-edit-document-generation-failed-list', () => {
    async function goToNextPage(wrapper) {
        await wrapper.find('.sw-pagination__page-button-next').trigger('click');
        await flushPromises();
    }

    it('should paginate rows client side', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.showPagination).toBe(true);
        expect(wrapper.vm.paginatedRows).toHaveLength(5);
        expect(wrapper.vm.paginatedRows[0].orderNumber).toBe('10089');

        await goToNextPage(wrapper);

        expect(wrapper.vm.paginatedRows).toHaveLength(1);
        expect(wrapper.vm.paginatedRows[0].orderNumber).toBe('10094');
    });

    it('should reset to page one when rows change', async () => {
        const wrapper = await createWrapper();

        await goToNextPage(wrapper);

        await wrapper.setProps({
            rows: rows.slice(0, 2),
        });

        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.showPagination).toBe(false);
    });

    it('should only render order and document type columns', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.columns).toEqual([
            expect.objectContaining({
                property: 'orderNumber',
            }),
            expect.objectContaining({
                property: 'documentTypesLabel',
            }),
        ]);
    });
});
