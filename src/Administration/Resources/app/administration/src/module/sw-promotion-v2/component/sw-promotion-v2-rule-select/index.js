import template from './sw-promotion-v2-rule-select.html.twig';
import './sw-promotion-v2-rule-select.scss';

/**
 * @private
 * @package business-ops
 */
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

        tooltipConfig(rule) {
            return this.ruleConditionDataProviderService.getRestrictedRuleTooltipConfig(
                rule.conditions,
                this.ruleAwareGroupKey,
            );
        },

        isRuleRestricted(rule) {
            return this.ruleConditionDataProviderService.isRuleRestricted(rule.conditions, this.ruleAwareGroupKey);
        },
    },
};
