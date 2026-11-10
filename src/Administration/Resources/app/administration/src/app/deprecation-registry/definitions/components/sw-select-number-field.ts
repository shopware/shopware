/**
 * @sw-package framework
 */

import {
    addPropWhenMissing,
    childElementsToArrayProp,
    componentMigration,
    fromAttribute,
    fromText,
    removeProp,
    renameArrayPropKeys,
    renameComponent,
    renameEvent,
    renameProp,
    renameVModelArgument,
    slotTextToProp,
    upgradeGuide,
} from '../helpers';

/**
 * sw-select-number-field extended sw-select-field-deprecated and turned the selected value into a number.
 * The conversion carries the mt-select steps of sw-select-field over, so the migration works on its own
 * with `deprecation:migrate --id`, and reads static option values as numbers.
 */
export default componentMigration({
    id: 'component.sw-select-number-field',
    component: 'sw-select-number-field',
    replacement: 'mt-select',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-select-number-field component is replaced by mt-select. Its <option> elements become the options prop with number values.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#removal-of-sw-select-field'),
        upgradeGuide('UPGRADE-6.8.md#removed-sw-select-number-field-sw-entity-many-to-many-select-and-sw-help-sidebar'),
    ],
    onConvert: [
        renameComponent({ from: 'sw-select-number-field', to: 'mt-select' }),
        addPropWhenMissing({
            prop: 'hide-clearable-button',
            message:
                'mt-select shows a clear button by default, which sw-select-number-field never had. Add "hide-clearable-button" to keep the field unclearable.',
        }),
        renameProp({
            from: 'value',
            to: 'model-value',
        }),
        renameEvent({ from: 'update:value', to: 'update:model-value' }),
        renameVModelArgument({ from: 'value', to: null }),
        removeProp({
            prop: 'aside',
        }),
        renameArrayPropKeys({
            prop: 'options',
            renameKeys: {
                name: 'label',
                id: 'value',
            },
            unlessElementHas: [
                'label-property',
                'value-property',
            ],
            message: 'Replace option object keys "name"/"id" with "label"/"value".',
            unsafeMessage:
                'Migrate option object keys "name"/"id" to "label"/"value" manually because this options expression is dynamic.',
        }),
        slotTextToProp({ fromSlot: 'label', toProp: 'label' }),
        childElementsToArrayProp({
            childElement: 'option',
            arrayProp: 'options',
            eachItem: {
                value: fromAttribute('value', {
                    required: true,
                    asNumber: true,
                    boundReview:
                        'sw-select-number-field turned the selected value into a number, mt-select passes it on as it is: check that the bound value is a number.',
                }),
                label: fromText(),
            },
            forBoundList: { value: 'value-property', label: 'label-property' },
        }),
    ],
});
