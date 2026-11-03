/**
 * @sw-package framework
 */

import { componentMigration, renameComponent, upgradeGuide } from '../helpers';

export default componentMigration({
    id: 'component.sw-text-editor-toolbar-table-button',
    component: 'sw-text-editor-toolbar-table-button',
    replacement: 'mt-text-editor',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The legacy sw-text-editor-toolbar-table-button component is replaced by mt-text-editor.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#component-replacement-with-meteor-component-library'),
    ],
    usage: [
        renameComponent({ from: 'sw-text-editor-toolbar-table-button', to: 'mt-text-editor', fix: 'manual' }),
    ],
});
