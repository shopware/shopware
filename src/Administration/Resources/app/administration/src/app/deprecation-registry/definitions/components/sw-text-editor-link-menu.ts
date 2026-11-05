/**
 * @sw-package framework
 */

import { componentMigration, renameComponent, upgradeGuide } from '../helpers';

export default componentMigration({
    id: 'component.sw-text-editor-link-menu',
    component: 'sw-text-editor-link-menu',
    replacement: 'mt-text-editor',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The legacy sw-text-editor-link-menu component is replaced by mt-text-editor.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#component-replacement-with-meteor-component-library'),
    ],
    onConvert: [
        renameComponent({ from: 'sw-text-editor-link-menu', to: 'mt-text-editor', fix: 'manual' }),
    ],
});
