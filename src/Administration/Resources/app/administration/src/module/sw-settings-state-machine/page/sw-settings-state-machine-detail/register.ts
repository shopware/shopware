import Component, * as Module from './index';

/**
 * @sw-package checkout
 */

Shopware.Component.register(Component);
Shopware._private.publish('components', 'sw-settings-state-machine-detail', Module);
