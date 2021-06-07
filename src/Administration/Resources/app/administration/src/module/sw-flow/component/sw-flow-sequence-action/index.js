import template from './sw-flow-sequence-action.html.twig';
import './sw-flow-sequence-action.scss';

const { Component } = Shopware;

Component.register('sw-flow-sequence-action', {
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

    data() {
        return {
            showModal: false
        };
    },

    computed: {
        actionOptions() {
            return [{
                value: 'addTag',
                icon: 'default-action-tags',
                label: this.$tc('sw-flow.actions.addTag')
            }, {
                value: 'callURL',
                icon: 'default-web-link',
                label: this.$tc('sw-flow.actions.callURL')
            }, {
                value: 'generateDocument',
                icon: 'default-documentation-file',
                label: this.$tc('sw-flow.actions.generateDocument')
            }, {
                value: 'removeTag',
                icon: 'default-action-tags',
                label: this.$tc('sw-flow.actions.removeTag')
            }, {
                value: 'sendEmail',
                icon: 'default-communication-envelope',
                label: this.$tc('sw-flow.actions.sendEmail')
            }, {
                value: 'setStatus',
                icon: 'default-shopping-plastic-bag',
                label: this.$tc('sw-flow.actions.setStatus')
            }, {
                value: 'stopFlow',
                icon: 'default-basic-x-circle',
                label: this.$tc('sw-flow.actions.stopFlow')
            }];
        }
    },

    methods: {
        openModalDynamic() {
            this.showModal = true;
        }
    }
});
