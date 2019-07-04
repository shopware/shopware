import { Component } from 'src/core/shopware';
import template from './sw-promotion-rule-select.html.twig';
import './sw-promotion-rule-select.scss';

Component.register('sw-promotion-rule-select', {
    template,

    model: {
        prop: 'collection',
        event: 'collection-added-item'
    },

    inject: ['repositoryFactory'],

    props: {
        collection: {
            type: Array,
            required: false,
            default: null
        },
        ruleScope: {
            type: Array,
            required: true
        }
    },

    data() {
        return {
            showRuleModal: false
        };
    },

    methods: {
        onSaveRule(rule) {
            const ruleRepository = this.repositoryFactory.create(
                this.collection.entity,
                this.collection.source
            );
            ruleRepository.assign(rule.id, this.collection.context).then(() => {
                ruleRepository.search(this.collection.criteria, this.collection.context).then((searchResult) => {
                    this.$emit('collection-added-item', searchResult);
                });
            });
        }
    }
});
