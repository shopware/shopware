/**
 * @sw-package framework
 */
import { mount, type VueWrapper } from '@vue/test-utils';

type FailedItem = {
    orderId: string;
    orderNumber: string;
    field: string;
    fieldLabel: string;
    code: string;
    reason?: string;
};

async function createWrapper(failedItems: FailedItem[] = []) {
    return mount(await wrapTestComponent('sw-bulk-edit-save-modal-error', { sync: true }), {
        global: {
            stubs: {
                'sw-label': true,
                'mt-icon': true,
            },
        },
        props: {
            failedItems,
        },
    });
}

describe('src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-error', () => {
    let wrapper: VueWrapper;

    afterEach(() => {
        wrapper?.unmount();
    });

    it('keeps the generic error state when no exact failures are available', async () => {
        wrapper = await createWrapper();

        expect(wrapper.find('.sw-bulk-edit-save-modal__help-text').text()).toBe('sw-bulk-edit.modal.error.description');
        expect(wrapper.find('.sw-bulk-edit-save-modal-error__failed-status-list').exists()).toBe(false);
    });

    it('renders the exact order number and status field for every failure', async () => {
        wrapper = await createWrapper([
            {
                orderId: 'order-id-1',
                orderNumber: '10001',
                field: 'orders',
                fieldLabel: 'Order status',
                code: '1020',
            },
            {
                orderId: 'order-id-2',
                orderNumber: '10002',
                field: 'orderTransactions',
                fieldLabel: 'Payment status',
                code: '1213',
            },
        ]);
        const failures = wrapper.findAll('.sw-bulk-edit-save-modal-error__failed-status-list li');

        expect(failures).toHaveLength(2);
        expect(failures[0].text()).toBe('10001 — Order status: sw-bulk-edit.modal.error.transitionFailed');
        expect(failures[1].text()).toBe('10002 — Payment status: sw-bulk-edit.modal.error.transitionFailed');
        expect(wrapper.text()).not.toContain('1020');
        expect(wrapper.text()).not.toContain('1213');
    });

    it.each([
        [
            'not-found',
            'orderNotFound',
        ],
        [
            'load',
            'orderLoadFailed',
        ],
        [
            'transition',
            'transitionFailed',
        ],
    ])('explains a %s failure while keeping the affected order identifiable', async (reason, snippet) => {
        wrapper = await createWrapper([
            {
                orderId: '018f9d0ead92798481a06a03a78e5f40',
                orderNumber: '018f9d0ead92798481a06a03a78e5f40',
                field: 'orders',
                fieldLabel: 'Order status',
                code: '',
                reason,
            },
        ]);

        expect(wrapper.find('.sw-bulk-edit-save-modal-error__failed-status-list li').text()).toBe(
            `018f9d0ead92798481a06a03a78e5f40 — Order status: sw-bulk-edit.modal.error.${snippet}`,
        );
    });
});
