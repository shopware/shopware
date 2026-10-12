import { createWrapper } from './fixtures';

/**
 * @sw-package checkout
 */

describe('src/module/sw-order/component/sw-order-state-history-modal: rendering', () => {
    it('should show state history grid correctly', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const stateHistoryRows = wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
        expect(stateHistoryRows).toHaveLength(4);

        const firstRow = stateHistoryRows.at(0);
        expect(firstRow.find('.sw-data-grid__cell--entity').text()).toBe('global.entities.order');
        expect(firstRow.find('.sw-data-grid__cell--user').text()).toBe('sw-order.stateHistoryModal.labelSystemUser');
        expect(firstRow.find('.sw-data-grid__cell--delivery').text()).toBe('Open');
        expect(firstRow.find('.sw-data-grid__cell--transaction').text()).toBe('Open');
        expect(firstRow.find('.sw-data-grid__cell--order').text()).toBe('Open');
        expect(firstRow.find('.sw-data-grid__cell--internalComment').find('mt-icon-stub').exists()).toBe(false);

        const secondRow = stateHistoryRows.at(1);
        expect(secondRow.find('.sw-data-grid__cell--entity').text()).toBe('global.entities.order_delivery');
        expect(secondRow.find('.sw-data-grid__cell--user').text()).toBe('admin');
        expect(secondRow.find('.sw-data-grid__cell--delivery').text()).toBe('Shipped');
        expect(secondRow.find('.sw-data-grid__cell--transaction').text()).toBe('Open');
        expect(secondRow.find('.sw-data-grid__cell--order').text()).toBe('Open');
        expect(secondRow.find('.sw-data-grid__cell--internalComment').find('mt-icon-stub').exists()).toBe(true);

        const thirdRow = stateHistoryRows.at(2);
        expect(thirdRow.find('.sw-data-grid__cell--entity').text()).toBe('global.entities.order_transaction 1');
        expect(thirdRow.find('.sw-data-grid__cell--user').text()).toBe('admin');
        expect(thirdRow.find('.sw-data-grid__cell--delivery').text()).toBe('Shipped');
        expect(thirdRow.find('.sw-data-grid__cell--transaction').text()).toBe('In progress');
        expect(thirdRow.find('.sw-data-grid__cell--order').text()).toBe('Open');
        expect(thirdRow.find('.sw-data-grid__cell--internalComment').find('mt-icon-stub').exists()).toBe(true);

        const fourthRow = stateHistoryRows.at(3);
        expect(fourthRow.find('.sw-data-grid__cell--entity').text()).toBe('global.entities.order_transaction 2');
        expect(fourthRow.find('.sw-data-grid__cell--user').text()).toBe('sw-order.stateHistoryModal.labelSystemUser');
        expect(fourthRow.find('.sw-data-grid__cell--delivery').text()).toBe('Shipped');
        expect(fourthRow.find('.sw-data-grid__cell--transaction').text()).toBe('Open');
        expect(fourthRow.find('.sw-data-grid__cell--order').text()).toBe('Open');
        expect(fourthRow.find('.sw-data-grid__cell--internalComment').find('mt-icon-stub').exists()).toBe(false);
    });

    it('should error notification if loading state history failed', async () => {
        const wrapper = await createWrapper({
            error: true,
        });

        wrapper.vm.createNotificationError = jest.fn();
        const notificationMock = wrapper.vm.createNotificationError;

        await flushPromises();

        expect(notificationMock).toHaveBeenCalled();
        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should emit modal-close event when clicking on Close button', async () => {
        const wrapper = await createWrapper();
        const closeButton = wrapper.findByText('button', 'global.default.close');

        await closeButton.trigger('click');
        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });
});
