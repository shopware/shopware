import template from './sw-promotion-v2-rule-select.html.twig';
import './sw-promotion-v2-rule-select.scss';

const { Component } = Shopware;

Component.register('sw-promotion-v2-rule-select', {
    template,

    inject: [
        'repositoryFactory',
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

        /* @internal (flag:FEATURE_NEXT_18215) */
        restrictedRules: {
            type: Array,
            required: false,
            default() {
                return [];
            },
        },

        /* @internal (flag:FEATURE_NEXT_18215) */
        restrictionSnippet: {
            type: String,
            required: false,
            default: '',
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
        tooltipConfig(itemId) {
            return {
                message: this.$t('sw-restricted-rules.restrictedAssignment.general', {
                    relation: this.restrictionSnippet,
                }),
                disabled: !this.restrictedRules.includes(itemId),
            };
        },
    },
});
