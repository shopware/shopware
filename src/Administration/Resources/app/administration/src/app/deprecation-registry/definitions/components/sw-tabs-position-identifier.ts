/**
 * @sw-package framework
 */

import { addPropWhenMissing, componentMigration, upgradeGuide } from '../helpers';

export default componentMigration({
    id: 'component.sw-tabs-position-identifier',
    component: 'sw-tabs',
    replacement: 'mt-tabs',
    deprecatedIn: '6.7.0',
    removedIn: '6.9.0',
    onlyWhenSelected: true,
    description: 'mt-tabs requires a position-identifier, so extensions can add tabs.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#set-small-and-position-identifier'),
    ],
    onEveryUsage: [
        addPropWhenMissing({
            prop: 'position-identifier',
            fix: 'manual',
            message: 'mt-tabs requires a position-identifier. Add a unique one, so extensions can add tabs.',
        }),
    ],
});
