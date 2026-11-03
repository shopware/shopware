import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@after-sales
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module and what it inherits from sw-condition-base-line-item,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-condition-line-item-property',
    extends: 'sw-condition-base-line-item',
    load: () => import('./src/sw-condition-line-item-property'),
    public: {
        props: [
            'parentCondition',
            'condition',
            'disabled',
        ],
        emits: [
            'create-before',
            'create-after',
            'condition-delete',
        ],
        slots: [
            'selection-label-property',
            'result-label-property',
            'result-description-property',
        ],
        blocks: [
            'sw_condition_value_content',
            'sw_condition_line_item_properties_field_operator',
            'sw_condition_line_item_properties_field_identifiers',
            'sw_condition_line_item_properties_field_description',
            'sw_condition_line_item_properties_field_description_default',
            'sw_condition_value_before_content',
            'sw_condition_base',
            'sw_condition_base_content',
            'sw_condition_base_field_condition',
            'sw_condition_base_field_actions',
            'sw_condition_base_field_create_before_action',
            'sw_condition_base_field_create_after_action',
            'sw_condition_base_field_delete_action',
            'sw_condition_base_error',
        ],
        computed: [
            'operators',
            'optionRepository',
            'identifiers',
            'optionCriteria',
            'ruleConditionRepository',
            'allowMatchesAll',
            'matchesAllOptions',
            'matchesAll',
            'conditionTypeIdentifier',
            'conditionClasses',
            'errorTree',
            'fieldErrors',
            'typeError',
            'errorCount',
            'listedErrors',
            'currentError',
            'hasError',
            'valueErrorPath',
            'value',
            'isDisabled',
            'hasNoComponent',
            'operator',
            'isEmpty',
        ],
        methods: [
            'createdComponent',
            'setOptions',
            'setSearchTerm',
            'onSelectCollapsed',
            'wrapCondition',
            'createEntity',
            'onCreateBefore',
            'onCreateAfter',
            'onDeleteCondition',
            'ensureValueExist',
        ],
    },
});
