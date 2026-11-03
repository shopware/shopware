import Component, * as Module from './index';

/**
 * @sw-package checkout
 */

Shopware.Component.register(Component);
Shopware._private.publish('components', 'sw-order-save-changes-beforehand-modal', Module);
