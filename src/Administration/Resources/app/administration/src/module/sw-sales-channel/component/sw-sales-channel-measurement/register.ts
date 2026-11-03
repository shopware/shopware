import Component, * as Module from './index';

/**
 * @sw-package inventory
 */

Shopware.Component.register(Component);
Shopware._private.publish('components', 'sw-sales-channel-measurement', Module);
