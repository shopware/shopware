import {
    componentMigration,
    customUsage,
    reference,
    renameComponent,
    renameProp,
    replaceWithStaticValueTransform,
} from '../helpers';

export default componentMigration({
    id: 'component.sw-icon',
    component: 'sw-icon',
    replacement: 'mt-icon',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-icon component is replaced by mt-icon. Small and large boolean sizing props are replaced by explicit size values.',
    handler: 'mt-icon',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#removal-of-sw-icon' }),
    ],
    usage: [
        renameComponent({ from: 'sw-icon', to: 'mt-icon' }),
        renameProp({
            from: 'small',
            to: 'size',
            transform: replaceWithStaticValueTransform({ value: '16px' }),
        }),
        renameProp({
            from: 'large',
            to: 'size',
            transform: replaceWithStaticValueTransform({ value: '32px' }),
        }),
        customUsage({ name: 'icon-default-size-24px', fix: 'unsafe-auto' }),
    ],
});
