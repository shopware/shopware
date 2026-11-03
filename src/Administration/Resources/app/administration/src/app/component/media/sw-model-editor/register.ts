import Component, * as Module from './index';

/**
 * @sw-package innovation
 */

Shopware.Component.register(Component);
Shopware._private.publish('components', 'sw-model-editor', Module);
