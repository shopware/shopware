/**
 * @sw-package framework
 */

import {
    addPropWhenMissing,
    componentMigration,
    renameComponent,
    renameEvent,
    renameProp,
    renameVModelArgument,
    upgradeGuide,
} from '../helpers';

export default componentMigration({
    id: 'component.sw-progress-bar',
    component: 'sw-progress-bar',
    replacement: 'mt-progress-bar',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The legacy sw-progress-bar component is replaced by mt-progress-bar. Legacy value APIs need migration.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#removal-of-sw-progress-bar'),
        upgradeGuide('UPGRADE-6.8.md#replace-sw-loader-sw-skeleton-bar-and-sw-progress-bar'),
    ],
    onConvert: [
        renameComponent({ from: 'sw-progress-bar', to: 'mt-progress-bar' }),
        addPropWhenMissing({
            prop: 'max-value',
            value: '100',
            bind: true,
            message: 'mt-progress-bar requires "max-value", which sw-progress-bar defaulted to 100. Add :max-value="100".',
        }),
    ],
    onEveryUsage: [
        renameProp({
            from: 'value',
            to: 'model-value',
        }),
        renameEvent({ from: 'update:value', to: 'update:model-value' }),
        renameVModelArgument({ from: 'value', to: null }),
    ],
});
