/**
 * @sw-package framework
 */

import { componentReference, jsApiMigration, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.popover-overrides',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'sw-popover is removed. Use mt-floating-ui.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-sw-popover')],
    usage: [
        componentReference({ component: 'sw-popover', to: 'mt-floating-ui', templates: false }),
    ],
});
