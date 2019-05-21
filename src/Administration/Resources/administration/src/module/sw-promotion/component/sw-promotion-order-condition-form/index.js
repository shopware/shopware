import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-promotion-order-condition-form.html.twig';
import './sw-promotion-order-condition-form.scss';

Component.register('sw-promotion-order-condition-form', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    props: {
        promotion: {
            type: Object,
            required: true,
            default: {}
        }
    },
    data() {
        return {
            itemAddNewRule: {
                index: -1,
                id: ''
            },
            showRuleModal: false
        };
    },
    computed: {

        rulesStore() {
            return State.getStore('rule');
        },

        orderRulesAssociationStore() {
            return this.promotion.getAssociation('orderRules');
        }

    },
    methods: {
        onSaveRule(rule) {
            this.$refs.orderRuleSelect.addSelection({ item: rule });
        },
        onSelectRule(event) {
            if (event.item.index === -1) {
                this.openCreateRuleModal();
            }
        },
        openCreateRuleModal() {
            this.showRuleModal = true;
        },
        onCloseRuleModal() {
            this.showRuleModal = false;
        }
    }

});
