/**
 * @sw-package framework
 */

import { componentMigration, renameComponent, upgradeGuide } from '../helpers';

export default componentMigration({
    id: 'component.sw-text-editor-toolbar',
    component: 'sw-text-editor-toolbar',
    replacement: 'mt-text-editor',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The legacy sw-text-editor-toolbar component is replaced by mt-text-editor.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#component-replacement-with-meteor-component-library'),
    ],
    overrideHint:
        'sw-text-editor-toolbar is removed with sw-text-editor. Configure the toolbar of mt-text-editor with custom-buttons, excluded-buttons and the #button_<name> slots.',
    onConvert: [
        renameComponent({
            from: 'sw-text-editor-toolbar',
            to: 'mt-text-editor',
            fix: 'manual',
            message:
                'Configure the toolbar of mt-text-editor with custom-buttons, excluded-buttons and the #button_<name> slots.',
        }),
    ],
});
