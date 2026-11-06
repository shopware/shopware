/**
 * @sw-package framework
 */

import { componentMigration, renameComponent, upgradeGuide } from '../helpers';

export default componentMigration({
    id: 'component.sw-text-field-deprecated',
    component: 'sw-text-field-deprecated',
    replacement: 'mt-text-field',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The legacy sw-text-field-deprecated component is replaced by mt-text-field.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#removal-of-sw-text-field'),
    ],
    onConvert: [
        renameComponent({ from: 'sw-text-field-deprecated', to: 'mt-text-field', fix: 'manual' }),
    ],
});
