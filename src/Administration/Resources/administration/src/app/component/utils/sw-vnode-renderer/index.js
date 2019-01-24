/**
 * @private
 */
export default {
    name: 'sw-vnode-renderer',
    functional: true,
    render(h, context) {
        return context.props.node;
    }
};
