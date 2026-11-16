/**
 * @sw-package framework
 */

import { componentMigration, renameComponent, upgradeGuide } from '../helpers';

export default componentMigration({
    id: 'component.sw-text-editor-table-toolbar',
    component: 'sw-text-editor-table-toolbar',
    replacement: 'mt-text-editor',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The legacy sw-text-editor-table-toolbar component is replaced by mt-text-editor.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#component-replacement-with-meteor-component-library'),
    ],
    overrideHint: 'sw-text-editor-table-toolbar is removed with sw-text-editor. mt-text-editor has built-in table support.',
    onConvert: [
        renameComponent({
            from: 'sw-text-editor-table-toolbar',
            to: 'mt-text-editor',
            fix: 'manual',
            message: 'mt-text-editor has built-in table support.',
        }),
    ],
});
