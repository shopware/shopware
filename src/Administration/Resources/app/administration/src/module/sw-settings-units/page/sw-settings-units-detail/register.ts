import Component, * as Module from './index';

/**
 * @sw-package inventory
 */

Shopware.Component.register(Component);
Shopware._private.publish('components', 'sw-settings-units-detail', Module);
