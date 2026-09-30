import { createWrapper, orderProp } from './fixtures';

/**
 * @sw-package checkout
 */

describe('src/module/sw-order/component/sw-order-state-history-modal: state change author', () => {
    it('should display username or fallback to email in user column', async () => {
        const stateHistoryWithEmailFallback = [
            {
                entityName: 'order_delivery',
                fromStateMachineState: {
                    technicalName: 'open',
                    translated: { name: 'Open' },
                },
                toStateMachineState: {
                    technicalName: 'shipped',
                    translated: { name: 'Shipped' },
                },
                user: {
                    username: 'admin',
                },
                createdAt: '2022-10-12T10:01:28.535+00:00',
            },
            {
                entityName: 'order_transaction',
                fromStateMachineState: {
                    technicalName: 'open',
                    translated: { name: 'Open' },
                },
                toStateMachineState: {
                    technicalName: 'in_progress',
                    translated: { name: 'In progress' },
                },
                user: {
                    email: 'user@example.com',
                },
                createdAt: '2022-10-12T10:01:33.815+00:00',
                referencedId: '2',
            },
        ];

        const wrapper = await createWrapper({}, orderProp, stateHistoryWithEmailFallback);
        await flushPromises();

        const stateHistoryRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');

        // First row should show username
        const firstRow = stateHistoryRows.at(0);
        expect(firstRow.find('.sw-data-grid__cell--user').text()).toBe('sw-order.stateHistoryModal.labelSystemUser');

        // Second row should show username
        const secondRow = stateHistoryRows.at(1);
        expect(secondRow.find('.sw-data-grid__cell--user').text()).toBe('admin');

        // Third row should show email (fallback)
        const thirdRow = stateHistoryRows.at(2);
        expect(thirdRow.find('.sw-data-grid__cell--user').text()).toBe('user@example.com');
    });

    it('should display the customer label for state changes coming from the storefront', async () => {
        const stateHistoryFromStorefront = [
            {
                entityName: 'order',
                fromStateMachineState: {
                    technicalName: 'open',
                    translated: { name: 'Open' },
                },
                toStateMachineState: {
                    technicalName: 'cancelled',
                    translated: { name: 'Cancelled' },
                },
                sourceType: 'sales-channel',
                createdAt: '2022-10-12T10:01:28.535+00:00',
            },
        ];

        const wrapper = await createWrapper({}, orderProp, stateHistoryFromStorefront);
        await flushPromises();

        const stateHistoryRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');

        // The prepended initial state has no source
        expect(stateHistoryRows.at(0).find('.sw-data-grid__cell--user').text()).toBe(
            'sw-order.stateHistoryModal.labelSystemUser',
        );
        expect(stateHistoryRows.at(1).find('.sw-data-grid__cell--user').text()).toBe(
            'sw-order.stateHistoryModal.labelCustomer',
        );
    });
});
