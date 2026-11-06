/**
 * @sw-package framework
 */

import {
    addPropWhenMissing,
    componentMigration,
    renameComponent,
    replaceProp,
    replacePropValue,
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
    onConvert: [
        renameComponent({ from: 'sw-button', to: 'mt-button' }),
        addPropWhenMissing({
            prop: 'variant',
            value: 'secondary',
            fix: 'auto',
            message: 'Add variant="secondary" when no variant is set to preserve the previous default button style.',
        }),
    ],
    onEveryUsage: [
        replacePropValue({
            prop: 'variant',
            fromValue: 'ghost',
            toValue: 'primary',
            alsoAddProp: 'ghost',
        }),
        replacePropValue({
            prop: 'variant',
            fromValue: 'danger',
            toValue: 'critical',
        }),
        replacePropValue({
            prop: 'variant',
            fromValue: 'contrast',
            fix: 'manual',
            message: 'The "variant" prop with value "contrast" is deprecated without replacement.',
        }),
        replacePropValue({
            prop: 'variant',
            fromValue: 'context',
            fix: 'manual',
            message: 'The "variant" prop with value "context" is deprecated without replacement.',
        }),
        replacePropValue({
            prop: 'variant',
            fromValue: 'ghost-danger',
            toValue: 'critical',
            alsoAddProp: 'ghost',
        }),
        replaceProp({
            prop: 'router-link',
            with: routerLinkClickHandler,
            fix: 'unsafe-auto',
            message:
                'Replace router-link with an explicit click handler or router-link wrapper and verify navigation semantics.',
        }),
    ],
});
