import template from './sw-promotion-rule-select.html.twig';
import './sw-promotion-rule-select.scss';

const { Component } = Shopware;

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 */
Component.register('sw-promotion-rule-select', {
    template,

    inject: ['repositoryFactory'],

    model: {
        prop: 'collection',
        event: 'collection-added-item',
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
            this.$emit('collection-added-item', collection);
        },
        onSaveRule(ruleId) {
            const ruleRepository = this.repositoryFactory.create(
                this.collection.entity,
                this.collection.source,
            );

            ruleRepository.assign(ruleId, this.collection.context).then(() => {
                ruleRepository.search(this.collection.criteria, this.collection.context).then((searchResult) => {
                    this.$emit('collection-added-item', searchResult);
                    this.$refs.ruleSelect.sendSearchRequest();
                });
            });
        },
    },
});
