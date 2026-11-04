/**
 * @sw-package framework
 */

import {
    componentMigration,
    mapPropValue,
    missingProp,
    onlyAfterConversion,
    renameComponent,
    replaceProp,
    routerLinkClickHandler,
    upgradeGuide,
} from '../helpers';

export default componentMigration({
    id: 'component.sw-button',
    component: 'sw-button',
    replacement: 'mt-button',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-button component is replaced by mt-button. Several legacy variant values and router-link usage need migration.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#removal-of-sw-button'),
    ],
    usage: [
        renameComponent({ from: 'sw-button', to: 'mt-button' }),
        mapPropValue({
            prop: 'variant',
            from: 'ghost',
            to: 'primary',
            addBooleanProp: 'ghost',
        }),
        mapPropValue({
            prop: 'variant',
            from: 'danger',
            to: 'critical',
        }),
        mapPropValue({
            prop: 'variant',
            from: 'contrast',
            fix: 'manual',
            message: 'The "variant" prop with value "contrast" is deprecated without replacement.',
        }),
        mapPropValue({
            prop: 'variant',
            from: 'context',
            fix: 'manual',
            message: 'The "variant" prop with value "context" is deprecated without replacement.',
        }),
        mapPropValue({
            prop: 'variant',
            from: 'ghost-danger',
            to: 'critical',
            addBooleanProp: 'ghost',
        }),
        replaceProp({
            prop: 'router-link',
            with: routerLinkClickHandler,
            fix: 'unsafe-auto',
            message:
                'Replace router-link with an explicit click handler or router-link wrapper and verify navigation semantics.',
        }),
        onlyAfterConversion(
            missingProp({
                prop: 'variant',
                value: 'secondary',
                fix: 'auto',
                message: 'Add variant="secondary" when no variant is set to preserve the previous default button style.',
            }),
        ),
    ],
});
