import template from './sw-flow-sequence.html.twig';
import './sw-flow-sequence.scss';

const { Component } = Shopware;

Component.register('sw-flow-sequence', {
    template,

    props: {
        sequence: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        }
    },

    methods: {
        addIfCondition() {
            this.sequence.ruleId = '';
        },

        addThenAction() {
            this.sequence.actionName = '';
        }
    }
});
