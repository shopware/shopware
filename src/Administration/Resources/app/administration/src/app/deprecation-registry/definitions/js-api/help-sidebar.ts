/**
 * @sw-package framework
 */

import { componentReference, jsApiMigration, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.help-sidebar',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'sw-help-sidebar is removed. The help center lives in sw-help-center-v2, which has the same block names but renders mt-action-menu-item elements.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-sw-select-number-field-sw-entity-many-to-many-select-and-sw-help-sidebar'),
    ],
    usage: [
        componentReference({ component: 'sw-help-sidebar', to: 'sw-help-center-v2' }),
    ],
});
