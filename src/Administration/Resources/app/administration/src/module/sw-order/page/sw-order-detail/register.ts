import Component, * as Module from './index';

// The detail page and its views share the order through this store, so it exists before any of them loads.
import '../../store/order-detail.store';

/**
 * @sw-package checkout
 */

Shopware.Component.register(Component);
Shopware._private.publish('components', 'sw-order-detail', Module);
