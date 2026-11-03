import Component, * as Module from './index';

/**
 * @sw-package inventory
 */

Shopware.Component.extend(Component);
Shopware._private.publish('components', 'sw-property-create', Module);
