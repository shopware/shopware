import Component, * as Module from './index';

/**
 * @sw-package after-sales
 */

Shopware.Component.register(Component);
Shopware._private.publish('components', 'sw-mail-header-footer-list', Module);
