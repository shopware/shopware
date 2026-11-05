/**
 * @sw-package framework
 */

import { componentMigration, renameComponent, upgradeGuide } from '../helpers';

export default componentMigration({
    id: 'component.sw-select-number-field',
    component: 'sw-select-number-field',
    replacement: 'mt-select',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The legacy sw-select-number-field component is replaced by mt-select.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#removal-of-sw-select-field'),
    ],
    onConvert: [
        renameComponent({ from: 'sw-select-number-field', to: 'mt-select', fix: 'manual' }),
    ],
});
