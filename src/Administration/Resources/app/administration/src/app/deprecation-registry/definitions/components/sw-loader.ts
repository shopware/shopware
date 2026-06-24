import { componentMigration, reference, renameComponent } from '../helpers';

export default componentMigration({
    id: 'component.sw-loader',
    component: 'sw-loader',
    replacement: 'mt-loader',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The legacy sw-loader component is replaced by mt-loader.',
    handler: 'mt-loader',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#removal-of-sw-loader' }),
    ],
    usage: [
        renameComponent({ from: 'sw-loader', to: 'mt-loader' }),
    ],
});
