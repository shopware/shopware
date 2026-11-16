/**
 * @sw-package framework
 */

import { componentMigration, renameComponent, upgradeGuide } from '../helpers';

export default componentMigration({
    id: 'component.sw-text-editor-toolbar-button',
    component: 'sw-text-editor-toolbar-button',
    replacement: 'mt-text-editor',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The legacy sw-text-editor-toolbar-button component is replaced by mt-text-editor.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#component-replacement-with-meteor-component-library'),
    ],
    overrideHint:
        'sw-text-editor-toolbar-button is removed with sw-text-editor. Add toolbar buttons to mt-text-editor with custom-buttons or a #button_<name> slot.',
    onConvert: [
        renameComponent({
            from: 'sw-text-editor-toolbar-button',
            to: 'mt-text-editor',
            fix: 'manual',
            message: 'Add toolbar buttons to mt-text-editor with custom-buttons or a #button_<name> slot.',
        }),
    ],
});
