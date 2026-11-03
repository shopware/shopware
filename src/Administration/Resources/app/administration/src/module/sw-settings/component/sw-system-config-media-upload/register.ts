import Component, * as Module from './index';

/**
 * @sw-package framework
 */

Shopware.Component.register(Component);
Shopware._private.publish('components', 'sw-system-config-media-upload', Module);
