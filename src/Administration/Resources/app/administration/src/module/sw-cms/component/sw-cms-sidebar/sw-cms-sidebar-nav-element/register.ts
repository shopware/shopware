import Component, * as Module from './index';

/**
 * @sw-package discovery
 */

Shopware.Component.register(Component);
Shopware._private.publish('components', 'sw-cms-sidebar-nav-element', Module);
