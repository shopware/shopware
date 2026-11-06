/**
 * @sw-package framework
 */

import { componentMigration, renameComponent, upgradeGuide } from '../helpers';

export default componentMigration({
    id: 'component.sw-button-deprecated',
    component: 'sw-button-deprecated',
    replacement: 'mt-button',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The legacy sw-button-deprecated component is replaced by mt-button.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#removal-of-sw-button'),
    ],
    onConvert: [
        renameComponent({ from: 'sw-button-deprecated', to: 'mt-button', fix: 'manual' }),
    ],
});
