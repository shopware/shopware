import { componentMigration, reference, renameComponent, renameEvent, renameProp, renameVModelArgument } from '../helpers';

export default componentMigration({
    id: 'component.sw-progress-bar',
    component: 'sw-progress-bar',
    replacement: 'mt-progress-bar',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The legacy sw-progress-bar component is replaced by mt-progress-bar. Legacy value APIs need migration.',
    handler: 'mt-progress-bar',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#removal-of-sw-progress-bar' }),
    ],
    usage: [
        renameComponent({ from: 'sw-progress-bar', to: 'mt-progress-bar' }),
        renameProp({
            from: 'value',
            to: 'model-value',
        }),
        renameEvent({ from: 'update:value', to: 'update:model-value' }),
        renameVModelArgument({ from: 'value', to: null }),
    ],
});
