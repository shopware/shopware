import Component, * as Module from './index';

/**
 * @sw-package fundamentals@after-sales
 */

Shopware.Component.register(Component);
Shopware._private.publish('components', 'sw-settings-rule-add-assignment-listing', Module);
