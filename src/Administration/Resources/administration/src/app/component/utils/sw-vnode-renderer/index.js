import { Component } from 'src/core/shopware';

Component.register('sw-vnode-renderer', {
    functional: true,
    render(h, context) {
        return context.props.node;
    }
});
