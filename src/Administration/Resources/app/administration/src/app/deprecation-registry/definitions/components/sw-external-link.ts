import { componentMigration, reference, removeProp, renameComponent } from '../helpers';

export default componentMigration({
    id: 'component.sw-external-link',
    component: 'sw-external-link',
    replacement: 'mt-external-link',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The legacy sw-external-link component is replaced by mt-external-link. The icon prop has no replacement.',
    handler: 'mt-external-link',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#removal-of-sw-external-link' }),
    ],
    usage: [
        renameComponent({ from: 'sw-external-link', to: 'mt-external-link' }),
        removeProp({
            prop: 'icon',
        }),
    ],
});
