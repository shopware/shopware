import template from './sw-promotion-v2-rule-select.html.twig';
import './sw-promotion-v2-rule-select.scss';

const { Component } = Shopware;

Component.register('sw-promotion-v2-rule-select', {
    template,

    inject: [
        'repositoryFactory',
        'ruleConditionDataProviderService',
        'feature',
    ],

    model: {
        prop: 'collection',
        event: 'change',
    },

    props: {
        collection: {
            type: Array,
            required: false,
            default: null,
        },

        ruleScope: {
            type: Array,
            required: false,
            default() {
                return [];
            },
        },

        localMode: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default() {
                return false;
            },
        },

        ruleAwareGroupKey: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            showRuleModal: false,
        };
    },

    methods: {
        onChange(collection) {
            this.$emit('change', collection);
        },

        onSaveRule(ruleId) {
            const ruleRepository = this.repositoryFactory.create(
                this.collection.entity,
                this.collection.source,
            );

            ruleRepository.assign(ruleId, this.collection.context).then(() => {
                ruleRepository.search(this.collection.criteria, this.collection.context).then((searchResult) => {
                    this.$emit('change', searchResult);
                    this.$refs.ruleSelect.sendSearchRequest();
                });
            });
        },

        /* @internal (flag:FEATURE_NEXT_18215) */
        tooltipConfig(item) {
            if (!this.ruleAwareGroupKey) {
                return { message: '', disabled: true };
            }

            const restrictionConfig = this.ruleConditionDataProviderService.getRestrictionsByAssociation(
                item.conditions,
                this.ruleAwareGroupKey,
            );


            if (!restrictionConfig.isRestricted) {
                return { message: '', disabled: true };
            }
            if (restrictionConfig.notEqualsViolations?.length > 0) {
                return {
                    showOnDisabledElements: true,
                    disabled: false,
                    message: this.$tc(
                        'sw-restricted-rules.restrictedAssignment.notEqualsViolationTooltip',
                        {},
                        {
                            conditions: this.ruleConditionDataProviderService.getTranslatedConditionViolationList(
                                restrictionConfig.notEqualsViolations,
                                'sw-restricted-rules.and',
                            ),
                            entityLabel: this.$tc(restrictionConfig.assignmentSnippet, 2),
                        },
                    ),
                };
            }

            return {
                showOnDisabledElements: true,
                disabled: false,
                width: 400,
                message: this.$tc(
                    'sw-restricted-rules.restrictedAssignment.equalsAnyViolationTooltip',
                    0,
                    {
                        conditions: this.ruleConditionDataProviderService.getTranslatedConditionViolationList(
                            restrictionConfig.equalsAnyNotMatched,
                            'sw-restricted-rules.or',
                        ),
                        entityLabel: this.$tc(restrictionConfig.assignmentSnippet, 2),
                    },
                ),
            };
        },

        /* @internal (flag:FEATURE_NEXT_18215) */
        isRuleRestricted(rule) {
            if (!this.ruleAwareGroupKey || !this.feature.isActive('FEATURE_NEXT_18215')) {
                return false;
            }

            const restrictionConfig = this.ruleConditionDataProviderService.getRestrictionsByAssociation(
                rule.conditions,
                this.ruleAwareGroupKey,
            );

            return restrictionConfig.isRestricted;
        },
    },
});
