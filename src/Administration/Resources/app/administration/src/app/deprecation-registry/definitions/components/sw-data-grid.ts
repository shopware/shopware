import { componentMigration, manualComponentReplacement, reference } from '../helpers';

export default componentMigration({
    id: 'component.sw-data-grid',
    component: 'sw-data-grid',
    replacement: 'mt-data-table',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The sw-data-grid component is deprecated. Migrate to mt-data-table and review the table configuration manually because the APIs are not equivalent.',
    handler: 'mt-data-table',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#component-replacement-with-meteor-component-library' }),
    ],
    usage: [
        manualComponentReplacement({
            from: 'sw-data-grid',
            to: 'mt-data-table',
            fix: 'manual',
            message: 'Rebuild the table configuration for mt-data-table and verify columns, selection, sorting and slots.',
        }),
    ],
});
