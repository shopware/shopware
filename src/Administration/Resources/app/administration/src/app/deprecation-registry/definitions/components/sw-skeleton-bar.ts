import { componentMigration, reference, renameComponent } from '../helpers';

export default componentMigration({
    id: 'component.sw-skeleton-bar',
    component: 'sw-skeleton-bar',
    replacement: 'mt-skeleton-bar',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The legacy sw-skeleton-bar component is replaced by mt-skeleton-bar.',
    handler: 'mt-skeleton-bar',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#removal-of-sw-skeleton-bar' }),
    ],
    usage: [
        renameComponent({ from: 'sw-skeleton-bar', to: 'mt-skeleton-bar' }),
    ],
});
