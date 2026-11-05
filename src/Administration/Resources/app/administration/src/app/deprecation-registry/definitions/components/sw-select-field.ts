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

export default componentMigration({
    id: 'component.sw-select-field',
    component: 'sw-select-field',
    replacement: 'mt-select',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-select-field component is replaced by mt-select. Value binding uses Vue 3 modelValue/update:model-value semantics and options must use label/value objects.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#removal-of-sw-select-field'),
    ],
    onConvert: [
        renameComponent({ from: 'sw-select-field', to: 'mt-select' }),
        addPropWhenMissing({
            prop: 'hide-clearable-button',
            message:
                'mt-select shows a clear button by default, which sw-select-field never had. Add "hide-clearable-button" to keep the field unclearable.',
        }),
    ],
    onEveryUsage: [
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
                    emptyAsNull: true,
                    boundReview:
                        'sw-select-field passed the selected value on as a string, mt-select passes it on as it is: check that the bound value has the type the model expects.',
                }),
                label: fromText(),
            },
            forBoundList: { value: 'value-property', label: 'label-property' },
        }),
    ],
});
