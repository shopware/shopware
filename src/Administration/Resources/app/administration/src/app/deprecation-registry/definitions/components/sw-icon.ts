/**
 * @sw-package framework
 */

import { booleanPropToValue, componentMigration, missingProp, renameComponent, upgradeGuide } from '../helpers';

export default componentMigration({
    id: 'component.sw-icon',
    component: 'sw-icon',
    replacement: 'mt-icon',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-icon component is replaced by mt-icon. Small and large boolean sizing props are replaced by explicit size values.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#removal-of-sw-icon'),
    ],
    onConvert: [
        renameComponent({ from: 'sw-icon', to: 'mt-icon' }),
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
                'size="24px" keeps the sw-icon default size. Keep it, or remove it to use the mt-icon default size, ' +
                'and remove the "Converted from sw-icon" comment so the size is not added again.',
        }),
    ],
    onEveryUsage: [
        booleanPropToValue({
            from: 'small',
            to: 'size',
            value: '16px',
        }),
        booleanPropToValue({
            from: 'large',
            to: 'size',
            value: '32px',
        }),
    ],
});
