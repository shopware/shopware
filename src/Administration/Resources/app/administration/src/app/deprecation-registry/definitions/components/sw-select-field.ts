/**
 * @sw-package framework
 */

import {
    componentMigration,
    mapOptionsPropKeys,
    missingProp,
    removeProp,
    renameComponent,
    renameEvent,
    renameProp,
    renameVModelArgument,
    slotToItemsProp,
    slotToProp,
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
        missingProp({
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
        mapOptionsPropKeys({
            prop: 'options',
            from: {
                name: 'label',
                id: 'value',
            },
            unlessProps: [
                'label-property',
                'value-property',
            ],
            message: 'Replace option object keys "name"/"id" with "label"/"value".',
            unsafeMessage:
                'Migrate option object keys "name"/"id" to "label"/"value" manually because this options expression is dynamic.',
        }),
        slotToProp({ slot: 'label', prop: 'label' }),
        slotToItemsProp({
            item: 'option',
            prop: 'options',
            fields: {
                value: {
                    attribute: 'value',
                    required: true,
                    emptyAsNull: true,
                    boundReview:
                        'sw-select-field passed the selected value on as a string, mt-select passes it on as it is: check that the bound value has the type the model expects.',
                },
                label: { content: true },
            },
            keyProps: { value: 'value-property', label: 'label-property' },
        }),
    ],
});
