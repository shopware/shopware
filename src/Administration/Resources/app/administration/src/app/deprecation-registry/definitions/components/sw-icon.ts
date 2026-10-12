/**
 * @sw-package framework
 */

import {
    componentMigration,
    missingProp,
    onlyAfterConversion,
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
        onlyAfterConversion(
            missingProp({
                prop: 'size',
                value: '24px',
                unlessProps: [
                    'size',
                    'small',
                    'large',
                ],
                fix: 'unsafe-auto',
                message: 'Add size="24px" when no size is set to preserve the previous default icon size.',
                reviewHint:
                    'size="24px" keeps the sw-icon default size. Keep it, or remove it to use the mt-icon default size.',
            }),
        ),
    ],
});
