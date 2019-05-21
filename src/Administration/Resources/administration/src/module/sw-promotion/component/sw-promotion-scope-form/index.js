import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-promotion-scope-form.html.twig';
import './sw-promotion-scope-form.scss';

Component.register('sw-promotion-scope-form', {
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

        cartRulesAssociationStore() {
            return this.promotion.getAssociation('cartRules');
        }

    },
    methods: {
        onSaveRule(rule) {
            this.$refs.cartRuleSelect.addSelection({ item: rule });
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
