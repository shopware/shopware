import Component, * as Module from './index';

/**
 * @sw-package checkout
 */

Shopware.Component.register(Component);
Shopware._private.publish('components', 'sw-bulk-edit-save-modal-success', Module);
