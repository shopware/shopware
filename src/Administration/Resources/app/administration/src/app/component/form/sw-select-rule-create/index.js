import template from './sw-select-rule-create.html.twig';
import './sw-select-rule-create.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @status ready
 * @description The <u>sw-select-rule-create</u> component is used to create or select a rule.
 * @example-type code-only
 * @component-example
 * <sw-select-rule-create ruleId="0fd38734776f41e9a1ba431f1667e677"
 * ruleFilter="ruleFilter"
 * @save-rule="onSaveRule"
 * @dismiss-rule="onDismissRule">
 * </sw-select-rule-create>
 */
Component.register('sw-select-rule-create', {
    template,

    inject: [
        'repositoryFactory'
    ],

    data() {
        return {
            itemAddNewRule: {
                index: -1,
                id: ''
            },
            showRuleModal: false
        };
    },

    props: {
        ruleId: {
            type: String,
            required: false,
            default: null
        },
        ruleFilter: {
            type: Object,
            required: false,
            default() {
                return new Criteria(1, Shopware.Context.api);
            }
        }
    },

    methods: {
        onSaveRule(ruleId) {
            this.$emit('save-rule', ruleId);
        },

        onSelectRule(event) {
            if (event.item.index !== -1) {
                this.onSaveRule(event.item);
                return;
            }

            this.openCreateRuleModal();
        },

        openCreateRuleModal() {
            this.showRuleModal = true;
        },
        onCloseRuleModal() {
            this.showRuleModal = false;
        },

        onRuleSelectInput(event) {
            if (!event) {
                this.$emit('dismiss-rule');
            }
        }
    }
});
