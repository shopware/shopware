import template from './sw-promotion-v2-rule-select.html.twig';
import './sw-promotion-v2-rule-select.scss';

/**
 * @private
 * @package buyers-experience
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'repositoryFactory',
        'ruleConditionDataProviderService',
        'feature',
    ],

    emits: ['update:collection'],

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

        listeners() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (this.isCompatEnabled('INSTANCE_LISTENERS')) {
                return this.$listeners;
            }

            return {};
        },
    },

    methods: {
        onChange(collection) {
            this.$emit('update:collection', collection);
        },

        onSaveRule(ruleId) {
            const ruleRepository = this.repositoryFactory.create(this.collection.entity, this.collection.source);

            ruleRepository.assign(ruleId, this.collection.context).then(() => {
                ruleRepository.search(this.collection.criteria, this.collection.context).then((searchResult) => {
                    this.$emit('update:collection', searchResult);
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
