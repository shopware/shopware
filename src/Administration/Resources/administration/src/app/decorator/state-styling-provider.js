import { Application } from 'src/core/shopware';

Application.addServiceProviderDecorator('stateStyleDataProviderService', (stateStyleService) => {
    // Order State Styles
    stateStyleService.addStyle('order.state', 'open', {
        icon: 'neutral',
        color: 'neutral'
    });

    stateStyleService.addStyle('order.state', 'in_progress', {
        icon: 'progress',
        color: 'progress'
    });

    stateStyleService.addStyle('order.state', 'cancelled', {
        icon: 'danger',
        color: 'danger'
    });

    stateStyleService.addStyle('order.state', 'completed', {
        icon: 'done',
        color: 'done'
    });

    // Order Transaction State Styles
    stateStyleService.addStyle('order_transaction.state', 'open', {
        icon: 'neutral',
        color: 'neutral'
    });

    stateStyleService.addStyle('order_transaction.state', 'paid', {
        icon: 'done',
        color: 'done'
    });

    stateStyleService.addStyle('order_transaction.state', 'paid_partially', {
        icon: 'progress',
        color: 'progress'
    });

    stateStyleService.addStyle('order_transaction.state', 'refunded', {
        icon: 'progress',
        color: 'progress'
    });

    stateStyleService.addStyle('order_transaction.state', 'refunded_partially', {
        icon: 'progress',
        color: 'progress'
    });

    stateStyleService.addStyle('order_transaction.state', 'reminded', {
        icon: 'progress',
        color: 'progress'
    });

    stateStyleService.addStyle('order_transaction.state', 'cancelled', {
        icon: 'danger',
        color: 'danger'
    });


    return stateStyleService;
});
