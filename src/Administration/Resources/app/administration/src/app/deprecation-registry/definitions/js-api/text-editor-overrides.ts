/**
 * @sw-package framework
 */

import { componentReference, jsApiMigration, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.text-editor-overrides',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'sw-text-editor and its toolbar components are removed. Use mt-text-editor.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-sw-text-editor')],
    usage: [
        componentReference({ component: 'sw-text-editor', to: 'mt-text-editor', templates: false }),
        componentReference({ component: 'sw-text-editor-toolbar', to: 'mt-text-editor', templates: false }),
        componentReference({ component: 'sw-text-editor-toolbar-button', to: 'mt-text-editor', templates: false }),
        componentReference({ component: 'sw-text-editor-toolbar-table-button', to: 'mt-text-editor', templates: false }),
        componentReference({ component: 'sw-text-editor-table-toolbar', to: 'mt-text-editor', templates: false }),
        componentReference({ component: 'sw-text-editor-link-menu', to: 'mt-text-editor', templates: false }),
    ],
});
