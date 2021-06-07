import template from './sw-flow-sequence-selector.html.twig';
import './sw-flow-sequence-selector.scss';

const { Component } = Shopware;

Component.register('sw-flow-sequence-selector', {
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
            this.$emit('add-if-condition', this.sequence);
        },

        addThenAction() {
            this.$emit('add-then-action', this.sequence);
        }
    }
});
