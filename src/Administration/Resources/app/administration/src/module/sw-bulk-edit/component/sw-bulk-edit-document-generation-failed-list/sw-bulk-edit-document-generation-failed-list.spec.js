/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

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
                stubs: {
                    'sw-data-grid': {
                        props: [
                            'dataSource',
                        ],
                        template: `
                            <div class="sw-data-grid">
                                <slot name="pagination"></slot>
                            </div>
                        `,
                    },
                    'sw-pagination': {
                        template: '<button class="sw-pagination" @click="$emit(\'page-change\', { page: 2 })"></button>',
                    },
                },
                mocks: {
                    $t: (key) => key,
                },
            },
        },
    );
}

describe('sw-bulk-edit-document-generation-failed-list', () => {
    it('should paginate rows client side', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.showPagination).toBe(true);
        expect(wrapper.vm.paginatedRows).toHaveLength(5);
        expect(wrapper.vm.paginatedRows[0].orderNumber).toBe('10089');

        wrapper.vm.onPageChange({ page: 2 });

        expect(wrapper.vm.paginatedRows).toHaveLength(1);
        expect(wrapper.vm.paginatedRows[0].orderNumber).toBe('10094');
    });

    it('should reset to page one when rows change', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onPageChange({ page: 2 });
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
