import template from './sw-select-rule-create.html.twig';
import './sw-select-rule-create.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 * @package business-ops
 * @status ready
 * @description The <u>sw-select-rule-create</u> component is used to create or select a rule.
 * @example-type code-only
 * @component-example
 * <sw-select-rule-create
 *     ruleId="0fd38734776f41e9a1ba431f1667e677"
 *     ruleFilter="ruleFilter"
 *     \@save-rule="onSaveRule"
 *     \@dismiss-rule="onDismissRule">
 * </sw-select-rule-create>
 */
Component.register('sw-select-rule-create', {
    template,

    inject: [
        'repositoryFactory',
        'feature',
        'ruleConditionDataProviderService',
    ],

    props: {
        ruleId: {
            type: String,
            required: false,
            default: null,
        },

        rules: {
            type: Array,
            required: false,
            default: null,
        },

        ruleFilter: {
            type: Object,
            required: false,
            default() {
                const criteria = new Criteria(1, 25);
                criteria.addSorting(Criteria.sort('name', 'ASC', false))
                    .addAssociation('conditions');

                return criteria;
            },
        },

        ruleAwareGroupKey: {
            type: String,
            required: false,
            default: null,
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

    data() {
        return {
            itemAddNewRule: {
                index: -1,
                id: '',
            },
            showRuleModal: false,
        };
    },

    computed: {
        collection: {
            get() {
                return this.rules;
            },
            set(collection) {
                collection.forEach((item) => {
                    if (!this.rules.has(item.id)) {
                        this.rules.add(item);
                    }
                });
                this.rules.forEach((item) => {
                    if (!collection.has(item.id)) {
                        this.rules.remove(item.id);
                    }
                });
            },
        },
    },

    methods: {
        onSaveRule(ruleId, rule) {
            if (this.rules) {
                this.rules.add(rule);
            }

            this.$emit('save-rule', ruleId, rule);
        },

        onSelectRule(event) {
            if (event !== this.ruleId) {
                this.onSaveRule(event);
            }
        },

        openCreateRuleModal() {
            this.showRuleModal = true;
        },

        onCloseRuleModal() {
            this.showRuleModal = false;
        },

        onRuleSelectInput(event) {
            if (!event) {
                this.$emit('dismiss-rule');
            }
        },

        isRuleRestricted(rule) {
            const insideRestrictedRuleIds = this.restrictedRuleIds.includes(rule.id);

            const isRuleRestricted = this.ruleConditionDataProviderService.isRuleRestricted(
                rule.conditions,
                this.ruleAwareGroupKey,
            );

            return isRuleRestricted || insideRestrictedRuleIds;
        },

        getAdvancedSelectionParameters() {
            return {
                ruleAwareGroupKey: this.ruleAwareGroupKey,
                restrictedRuleIds: this.restrictedRuleIds,
                restrictedRuleIdsTooltipLabel: this.restrictedRuleIdsTooltipLabel,
            };
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
    },
});
