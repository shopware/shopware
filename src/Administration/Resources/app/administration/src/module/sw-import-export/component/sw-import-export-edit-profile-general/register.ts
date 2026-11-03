import Component, * as Module from './index';

/**
 * @sw-package fundamentals@after-sales
 */

Shopware.Component.register(Component);
Shopware._private.publish('components', 'sw-import-export-edit-profile-general', Module);
