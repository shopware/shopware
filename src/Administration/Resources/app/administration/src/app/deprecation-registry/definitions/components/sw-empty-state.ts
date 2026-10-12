/**
 * @sw-package framework
 */

import { componentMigration, reference, renameComponent, renameProp } from '../helpers';

export default componentMigration({
    id: 'component.sw-empty-state',
    component: 'sw-empty-state',
    replacement: 'mt-empty-state',
    deprecatedIn: '6.8.0',
    removedIn: '6.9.0',
    description:
        'The sw-empty-state component is deprecated. Use mt-empty-state and migrate the title prop to headline and the subline prop to description.',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.8.md#removal-of-sw-empty-state' }),
    ],
    usage: [
        renameComponent({ from: 'sw-empty-state', to: 'mt-empty-state' }),
        renameProp({
            from: 'subline',
            to: 'description',
        }),
        renameProp({
            from: 'title',
            to: 'headline',
        }),
    ],
});
