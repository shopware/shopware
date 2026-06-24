import { componentMigration, reference, renameComponent } from '../helpers';

export default componentMigration({
    id: 'component.sw-alert-deprecated',
    component: 'sw-alert-deprecated',
    replacement: 'mt-banner',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The legacy sw-alert-deprecated component is replaced by mt-banner.',
    handler: 'mt-banner',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#removal-of-sw-alert' }),
    ],
    usage: [
        renameComponent({ from: 'sw-alert-deprecated', to: 'mt-banner', fix: 'manual' }),
    ],
});
