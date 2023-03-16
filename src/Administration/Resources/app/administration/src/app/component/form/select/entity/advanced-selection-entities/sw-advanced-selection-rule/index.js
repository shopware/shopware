import template from './sw-advanced-selection-rule.html.twig';
import './sw-advanced-selection-rule.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 * @package business-ops
 * @description Configures the advanced selection in entity selects.
 * Should only be used as a parameter `advanced-selection-component="sw-advanced-selection-rule"`
 * to `sw-entity-...-select` components.
 * @status prototype
 */
Component.register('sw-advanced-selection-rule', {
    template,

    inject: [
        'ruleConditionDataProviderService',
        'feature',
    ],

    props: {
        ruleAwareGroupKey: {
            type: String,
            required: true,
        },

        /**
         * Contains an array of rule ids which should not be selectable,
         * for example because they are already used in a different place
         */
        restrictedRuleIds: {
            type: Array,
            required: false,
            default() {
                return [];
            },
        },

        /**
         * Tooltip label to show for any rule in the restrictedRuleIds array
         */
        restrictedRuleIdsTooltipLabel: {
            type: String,
            required: false,
            default() {
                return '';
            },
        },
    },

    computed: {
        getRuleDefinition() {
            return Shopware.EntityDefinition.get('rule');
        },

        assignmentProperties() {
            const properties = [];

            Object.keys(this.getRuleDefinition.properties).forEach((propertyName) => {
                if (propertyName === 'conditions' || propertyName === 'tags') {
                    return;
                }

                const property = this.getRuleDefinition.properties[propertyName];
                if (property.relation === 'many_to_many' || property.relation === 'one_to_many') {
                    properties.push(propertyName);
                }
            });

            return properties;
        },

        context() {
            return Shopware.Context.api;
        },

        columns() {
            const columns = [{
                property: 'name',
                dataIndex: 'name',
                inlineEdit: 'string',
                label: 'sw-settings-rule.list.columnName',
                routerLink: 'sw.settings.rule.detail',
                width: '250px',
                allowResize: true,
                primary: true,
            }, {
                property: 'priority',
                label: 'sw-settings-rule.list.columnPriority',
                inlineEdit: 'number',
                allowResize: true,
            }, {
                property: 'description',
                label: 'sw-settings-rule.list.columnDescription',
                width: '250px',
                allowResize: true,
            }, {
                property: 'updatedAt',
                label: 'sw-settings-rule.list.columnDateCreated',
                align: 'right',
                allowResize: true,
            }, {
                property: 'invalid',
                label: 'sw-product-stream.list.columnStatus',
                allowResize: true,
            }, {
                property: 'tags',
                label: 'sw-settings-rule.list.columnTags',
                width: '250px',
                allowResize: true,
                sortable: false,
                visible: false,
            }];

            this.assignmentProperties.forEach((propertyName) => {
                const labelPostfix = propertyName.charAt(0).toUpperCase() + propertyName.slice(1);
                columns.push({
                    property: `${propertyName}`,
                    label: `sw-settings-rule.list.column${labelPostfix}`,
                    width: '250px',
                    allowResize: true,
                    sortable: true,
                    visible: false,
                });
            });

            return columns;
        },

        filters() {
            const filters = {
                conditionGroups: {
                    property: 'conditions.type',
                    label: this.$tc('sw-settings-rule.filter.groupFilter.label'),
                    placeholder: this.$tc('sw-settings-rule.filter.groupFilter.placeholder'),
                    type: 'multi-select-filter',
                    options: this.groupFilterOptions,
                },
                conditions: {
                    property: 'conditions.type',
                    label: this.$tc('sw-settings-rule.filter.conditionFilter.label'),
                    placeholder: this.$tc('sw-settings-rule.filter.conditionFilter.placeholder'),
                    type: 'multi-select-filter',
                    options: this.conditionFilterOptions,
                },
                assignments: {
                    existingType: true,
                    property: 'conditions',
                    label: this.$tc('sw-settings-rule.filter.assignmentFilter.label'),
                    placeholder: this.$tc('sw-settings-rule.filter.assignmentFilter.placeholder'),
                    type: 'multi-select-filter',
                    options: this.associationFilterOptions,
                },
                tags: {
                    property: 'tags',
                    label: this.$tc('sw-settings-rule.filter.tagFilter.label'),
                    placeholder: this.$tc('sw-settings-rule.filter.tagFilter.placeholder'),
                    criteria: (new Criteria(1, 25)).addSorting(Criteria.sort('name')),
                },
            };

            return filters;
        },

        conditionFilterOptions() {
            const conditions = this.ruleConditionDataProviderService.getConditions().map((condition) => {
                return { value: condition.type, label: this.$tc(condition.label) };
            });
            conditions.sort((a, b) => a.label.localeCompare(b.label));

            return conditions;
        },

        groupFilterOptions() {
            const groupFilter = [];
            Object.values(this.ruleConditionDataProviderService.getGroups()).forEach((group) => {
                const conditionFilterString = this.ruleConditionDataProviderService.getByGroup(group.id).map((condition) => {
                    return condition.type;
                }).join('|');

                groupFilter.push({
                    value: conditionFilterString,
                    label: this.$tc(group.name),
                });
            });
            groupFilter.sort((a, b) => a.label.localeCompare(b.label));

            return groupFilter;
        },

        associationFilterOptions() {
            const associations = [];
            Object.entries(this.getRuleDefinition.properties).forEach(([key, value]) => {
                if (value.type === 'association' && key !== 'conditions' && key !== 'tags') {
                    associations.push({
                        value: key,
                        label: this.$tc(`sw-settings-rule.filter.assignmentFilter.values.${key}`),
                    });
                }
            });
            associations.sort((a, b) => a.label.localeCompare(b.label));

            return associations;
        },

        associations() {
            const associations = [
                'tags',
            ];

            associations.push('conditions');

            return associations;
        },

        aggregations() {
            const aggregations = [];
            Object.keys(this.getRuleDefinition.properties).forEach((propertyName) => {
                if (propertyName === 'conditions' || propertyName === 'tags') {
                    return;
                }

                const property = this.getRuleDefinition.properties[propertyName];

                if (property.relation === 'many_to_many' || property.relation === 'one_to_many') {
                    aggregations.push(Criteria.terms(
                        propertyName,
                        'id',
                        null,
                        null,
                        Criteria.count(propertyName, `rule.${propertyName}.id`),
                    ));
                }
            });

            return aggregations;
        },
    },

    methods: {
        getColumnClass(item) {
            return (this.isRestricted(item)) ? 'sw-advanced-selection-rule-disabled' : '';
        },

        tooltipConfig(rule) {
            if (this.restrictedRuleIds.includes(rule.id)) {
                return {
                    message: this.restrictedRuleIdsTooltipLabel,
                    disabled: false,
                };
            }

            return this.ruleConditionDataProviderService.getRestrictedRuleTooltipConfig(
                rule.conditions,
                this.ruleAwareGroupKey,
            );
        },

        isRestricted(item) {
            const insideRestrictedRuleIds = this.restrictedRuleIds.includes(item.id);

            const isRuleRestricted = this.ruleConditionDataProviderService.isRuleRestricted(
                item.conditions,
                this.ruleAwareGroupKey,
            );

            return isRuleRestricted || insideRestrictedRuleIds;
        },

        isRecordSelectable(item) {
            const isRestricted = this.isRestricted(item);

            if (isRestricted) {
                return {
                    isSelectable: !isRestricted,
                    tooltip: this.tooltipConfig(item),
                };
            }

            return {};
        },

        getCounts(id, aggregations) {
            const counts = {};

            if (aggregations === undefined) {
                return counts;
            }

            Object.keys(this.getRuleDefinition.properties).forEach((propertyName) => {
                if (propertyName === 'conditions' || propertyName === 'tags') {
                    return;
                }

                const property = this.getRuleDefinition.properties[propertyName];
                if (property.relation === 'many_to_many' || property.relation === 'one_to_many') {
                    const countBucket = aggregations[propertyName]?.buckets.filter((bucket) => {
                        return bucket.key === id;
                    })[0];

                    if (!countBucket || !countBucket[propertyName] || !countBucket[propertyName].count) {
                        counts[propertyName] = 0;

                        return;
                    }

                    counts[propertyName] = countBucket[propertyName].count;
                }
            });

            return counts;
        },
    },
});
