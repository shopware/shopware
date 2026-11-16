/**
 * @sw-package framework
 */

import { componentMigration, flagUsage, upgradeGuide } from '../helpers';

export default componentMigration({
    id: 'component.sw-help-sidebar',
    component: 'sw-help-sidebar',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    onlyWhenSelected: true,
    description: 'sw-help-sidebar is removed without replacement. The help center content is part of sw-help-center-v2.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#remove-sw-help-sidebar'),
    ],
    overrideHint:
        'sw-help-sidebar is removed. Move this override to sw-help-center-v2, which has the same Twig block names.',
    onEveryUsage: [
        flagUsage({
            message: 'sw-help-sidebar is removed without replacement. The help center content is part of sw-help-center-v2.',
        }),
    ],
});
