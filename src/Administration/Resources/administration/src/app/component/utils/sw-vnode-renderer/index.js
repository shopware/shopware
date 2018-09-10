import { Component } from 'src/core/shopware';

/**
 * @private
 */
Component.register('sw-vnode-renderer', {
    functional: true,
    render(h, context) {
        return context.props.node;
    }
});
