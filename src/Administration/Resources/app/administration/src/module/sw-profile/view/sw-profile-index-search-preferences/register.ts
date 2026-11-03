import Component, * as Module from './index';

/**
 * @sw-package fundamentals@framework
 */

Shopware.Component.register(Component);
Shopware._private.publish('components', 'sw-profile-index-search-preferences', Module);
