import Component, * as Module from './index';

/**
 * @sw-package checkout
 */

Shopware.Component.register(Component);
Shopware._private.publish('components', 'sw-order-line-items-grid', Module);
