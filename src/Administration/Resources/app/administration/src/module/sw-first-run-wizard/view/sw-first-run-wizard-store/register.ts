import Component, * as Module from './index';

/**
 * @sw-package fundamentals@after-sales
 */

Shopware.Component.register(Component);
Shopware._private.publish('components', 'sw-first-run-wizard-store', Module);
