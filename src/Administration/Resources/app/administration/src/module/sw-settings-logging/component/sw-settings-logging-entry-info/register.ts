import Component, * as Module from './index';

/**
 * @sw-package framework
 */

Shopware.Component.register(Component);
Shopware._private.publish('components', 'sw-settings-logging-entry-info', Module);
