/**
 * @sw-package framework
 */

import { componentMigration, renameProp, upgradeGuide } from '../helpers';

export default componentMigration({
    id: 'component.sw-entity-listing.items',
    component: 'sw-entity-listing',
    deprecatedIn: '6.8.0',
    removedIn: '6.9.0',
    description:
        'The sw-entity-listing items prop is deprecated. Use data-source instead so listing data follows the current component API.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removal-of-items-prop-in-sw-entity-listing-component'),
    ],
    usage: [
        renameProp({
            from: 'items',
            to: 'data-source',
        }),
    ],
});
