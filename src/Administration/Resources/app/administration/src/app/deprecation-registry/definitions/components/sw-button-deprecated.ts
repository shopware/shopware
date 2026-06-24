import { componentMigration, reference, renameComponent } from '../helpers';

export default componentMigration({
    id: 'component.sw-button-deprecated',
    component: 'sw-button-deprecated',
    replacement: 'mt-button',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The legacy sw-button-deprecated component is replaced by mt-button.',
    handler: 'mt-button',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#removal-of-sw-button' }),
    ],
    usage: [
        renameComponent({ from: 'sw-button-deprecated', to: 'mt-button', fix: 'manual' }),
    ],
});
