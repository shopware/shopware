import Component, * as Module from './index';

/**
 * @sw-package checkout
 */

Shopware.Component.register(Component);
Shopware._private.publish('components', 'sw-settings-tax-rule-type-zip-code-range-cell', Module);
