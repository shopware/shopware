const { Application } = Shopware;

Application.addServiceProviderDecorator('stateStyleDataProviderService', (stateStyleService) => {
    // Order State Styles
    stateStyleService.addStyle('order.state', 'open', {
        icon: 'neutral',
        color: 'neutral',
        variant: 'neutral',
    });

    stateStyleService.addStyle('order.state', 'in_progress', {
        icon: 'progress',
        color: 'progress',
        variant: 'progress',
    });

    stateStyleService.addStyle('order.state', 'cancelled', {
        icon: 'danger',
        color: 'danger',
        variant: 'danger',
    });

    stateStyleService.addStyle('order.state', 'completed', {
        icon: 'done',
        color: 'done',
        variant: 'done',
    });

    // Order Transaction State Styles
    stateStyleService.addStyle('order_transaction.state', 'open', {
        icon: 'neutral',
        color: 'neutral',
        variant: 'neutral',
    });

    stateStyleService.addStyle('order_transaction.state', 'paid', {
        icon: 'done',
        color: 'done',
        variant: 'done',
    });

    stateStyleService.addStyle('order_transaction.state', 'paid_partially', {
        icon: 'progress',
        color: 'progress',
        variant: 'progress',
    });

    stateStyleService.addStyle('order_transaction.state', 'refunded', {
        icon: 'progress',
        color: 'progress',
        variant: 'progress',
    });

    stateStyleService.addStyle('order_transaction.state', 'refunded_partially', {
        icon: 'progress',
        color: 'progress',
        variant: 'progress',
    });

    stateStyleService.addStyle('order_transaction.state', 'reminded', {
        icon: 'progress',
        color: 'progress',
        variant: 'progress',
    });

    stateStyleService.addStyle('order_transaction.state', 'cancelled', {
        icon: 'danger',
        color: 'danger',
        variant: 'danger',
    });

    // Order Delivery State Styles
    stateStyleService.addStyle('order_delivery.state', 'open', {
        icon: 'neutral',
        color: 'neutral',
        variant: 'neutral',
    });

    stateStyleService.addStyle('order_delivery.state', 'shipped', {
        icon: 'done',
        color: 'done',
        variant: 'done',
    });

    stateStyleService.addStyle('order_delivery.state', 'shipped-partially', {
        icon: 'progress',
        color: 'progress',
        variant: 'progress',
    });

    stateStyleService.addStyle('order_delivery.state', 'returned', {
        icon: 'progress',
        color: 'progress',
        variant: 'progress',
    });

    stateStyleService.addStyle('order_delivery.state', 'returned_partially', {
        icon: 'progress',
        color: 'progress',
        variant: 'progress',
    });

    stateStyleService.addStyle('order_delivery.state', 'cancelled', {
        icon: 'danger',
        color: 'danger',
        variant: 'danger',
    });

    return stateStyleService;
});
