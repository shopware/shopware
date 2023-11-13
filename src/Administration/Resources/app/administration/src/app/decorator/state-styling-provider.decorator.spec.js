import 'src/app/decorator/state-styling-provider.decorator';

describe('src/app/decorator/state-styling-provider.decorator.ts', () => {
    it('should register state styles', async () => {
        const addStyleMock = jest.fn();
        const styleMatcher = expect.objectContaining({ color: expect.any(String), icon: expect.any(String), variant: expect.any(String) });

        Shopware.Service().register('stateStyleDataProviderService', () => { return { addStyle: addStyleMock, bind: () => {} }; });
        Shopware.Service('stateStyleDataProviderService');

        expect(addStyleMock).toHaveBeenCalledWith('order.state', 'open', styleMatcher);
        expect(addStyleMock).toHaveBeenCalledWith('order.state', 'in_progress', styleMatcher);
        expect(addStyleMock).toHaveBeenCalledWith('order.state', 'cancelled', styleMatcher);
        expect(addStyleMock).toHaveBeenCalledWith('order.state', 'completed', styleMatcher);

        expect(addStyleMock).toHaveBeenCalledWith('order_transaction.state', 'open', styleMatcher);
        expect(addStyleMock).toHaveBeenCalledWith('order_transaction.state', 'authorized', styleMatcher);
        expect(addStyleMock).toHaveBeenCalledWith('order_transaction.state', 'unconfirmed', styleMatcher);
        expect(addStyleMock).toHaveBeenCalledWith('order_transaction.state', 'in_progress', styleMatcher);
        expect(addStyleMock).toHaveBeenCalledWith('order_transaction.state', 'paid', styleMatcher);
        expect(addStyleMock).toHaveBeenCalledWith('order_transaction.state', 'paid_partially', styleMatcher);
        expect(addStyleMock).toHaveBeenCalledWith('order_transaction.state', 'refunded', styleMatcher);
        expect(addStyleMock).toHaveBeenCalledWith('order_transaction.state', 'refunded_partially', styleMatcher);
        expect(addStyleMock).toHaveBeenCalledWith('order_transaction.state', 'reminded', styleMatcher);
        expect(addStyleMock).toHaveBeenCalledWith('order_transaction.state', 'cancelled', styleMatcher);
        expect(addStyleMock).toHaveBeenCalledWith('order_transaction.state', 'failed', styleMatcher);
        expect(addStyleMock).toHaveBeenCalledWith('order_transaction.state', 'chargeback', styleMatcher);

        expect(addStyleMock).toHaveBeenCalledWith('order_delivery.state', 'open', styleMatcher);
        expect(addStyleMock).toHaveBeenCalledWith('order_delivery.state', 'shipped', styleMatcher);
        expect(addStyleMock).toHaveBeenCalledWith('order_delivery.state', 'shipped_partially', styleMatcher);
        expect(addStyleMock).toHaveBeenCalledWith('order_delivery.state', 'returned', styleMatcher);
        expect(addStyleMock).toHaveBeenCalledWith('order_delivery.state', 'returned_partially', styleMatcher);
        expect(addStyleMock).toHaveBeenCalledWith('order_delivery.state', 'cancelled', styleMatcher);
    });
});
