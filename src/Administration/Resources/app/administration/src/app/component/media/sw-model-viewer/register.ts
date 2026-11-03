import Component, * as Module from './index';

/**
 * @sw-package discovery
 */

Shopware.Component.register(Component);
Shopware._private.publish('components', 'sw-model-viewer', Module);
