import template from './sw-select-rule-create.html.twig';

const { Component, StateDeprecated } = Shopware;

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
        ruleStore() {
            return StateDeprecated.getStore('rule');
        }
    },

    props: {
        ruleId: {
            type: String,
            required: false,
            default: null
        },
        ruleFilter: {
            type: Object,
            required: false
        },
        required: {
            type: Boolean,
            required: false,
            default: false
        },
        placeholder: {
            type: String,
            required: false,
            default: null
        },
        size: {
            type: String,
            required: false,
            default: null
        }
    },

    methods: {
        onSaveRule(rule) {
            this.$emit('save-rule', rule.id);
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
