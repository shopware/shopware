import template from './sw-flow-sequence-condition.html.twig';
import './sw-flow-sequence-condition.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-flow-sequence-condition', {
    template,

    inject: ['repositoryFactory'],

    props: {
        sequence: {
            type: Object,
            required: true,
            default() {
                return {};
            },
        },
    },

    data() {
        return {
            showRuleModal: false,
            showRuleSelection: false,
        };
    },

    computed: {
        ruleCriteria() {
            return new Criteria();
        },
    },

    methods: {
        onRuleChange(rule) {
            if (!rule) {
                return;
            }

            const { id, name, description } = rule;

            this.sequence.ruleId = id;
            this.sequence.rule = {
                name,
                description,
            };

            this.showRuleSelection = false;
        },

        deleteRule() {
            this.sequence.ruleId = '';
            this.sequence.rule = {};
        },
    },
});
