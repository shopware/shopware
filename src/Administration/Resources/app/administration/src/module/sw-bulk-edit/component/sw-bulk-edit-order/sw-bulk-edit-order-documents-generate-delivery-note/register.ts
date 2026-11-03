import Component, * as Module from './index';

/**
 * @sw-package checkout
 */

Shopware.Component.extend(Component);
Shopware._private.publish('components', 'sw-bulk-edit-order-documents-generate-delivery-note', Module);
