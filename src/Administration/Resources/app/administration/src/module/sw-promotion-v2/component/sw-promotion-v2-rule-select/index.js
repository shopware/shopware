import template from './sw-promotion-v2-rule-select.html.twig';
import './sw-promotion-v2-rule-select.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
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

    computed: {
        advanceSelectionParameters() {
            return {
                ruleAwareGroupKey: this.ruleAwareGroupKey,
            };
        },
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
        tooltipConfig(rule) {
            if (!this.feature.isActive('FEATURE_NEXT_18215')) {
                return { message: '', disabled: true };
            }

            return this.ruleConditionDataProviderService.getRestrictedRuleTooltipConfig(
                rule.conditions,
                this.ruleAwareGroupKey,
            );
        },

        /* @internal (flag:FEATURE_NEXT_18215) */
        isRuleRestricted(rule) {
            if (rule.areas?.includes('flow-condition') && this.ruleAwareGroupKey !== 'flowConditions') {
                return true;
            }

            if (!this.feature.isActive('FEATURE_NEXT_18215')) {
                return false;
            }

            return this.ruleConditionDataProviderService.isRuleRestricted(rule.conditions, this.ruleAwareGroupKey);
        },
    },
};
