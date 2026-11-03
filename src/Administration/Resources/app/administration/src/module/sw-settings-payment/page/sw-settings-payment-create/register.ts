import Component, * as Module from './index';

/**
 * @sw-package checkout
 */

Shopware.Component.extend(Component);
Shopware._private.publish('components', 'sw-settings-payment-create', Module);
