import template from './sw-rule-modal.html.twig';
import './sw-rule-modal.scss';

const { Component, Mixin, Context } = Shopware;
const { EntityCollection } = Shopware.Data;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @status ready
 * @description The <u>sw-rule-modal</u> component is used to create or modify a rule.
 * @example-type code-only
 * @component-example
 * <sw-rule-modal ruleId="0fd38734776f41e9a1ba431f1667e677" @save="onSave" @modal-close="onCloseModal">
 * </sw-rule-modal>
 */
Component.register('sw-rule-modal', {
    template,

    inject: [
        'repositoryFactory',
        'ruleConditionDataProviderService',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
    ],

    props: {
        allowedRuleScopes: {
            type: Array,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            rule: null,
            initialConditions: null,
            isLoading: false,
        };
    },

    computed: {
        ruleRepository() {
            return this.repositoryFactory.create('rule');
        },

        ruleConditionRepository() {
            if (!this.rule) {
                return null;
            }

            return this.repositoryFactory.create(
                this.rule.conditions.entity,
                this.rule.conditions.source,
            );
        },

        modalTitle() {
            if (this.rule.isNew()) {
                return this.$tc('sw-rule-modal.modalTitleNew');
            }
            return this.placeholder(this.rule, 'name', this.$tc('sw-rule-modal.modalTitleModify'));
        },

        ...mapPropertyErrors('rule', ['name', 'priority']),
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.rule = this.ruleRepository.create(Context.api);
            this.initialConditions = EntityCollection.fromCollection(this.rule.conditions);
        },

        conditionsChanged({ conditions }) {
            this.rule.conditions = conditions;
        },

        saveAndClose() {
            const titleSaveSuccess = this.$tc('global.default.success');
            const messageSaveSuccess = this.$tc(
                'sw-rule-modal.messageSaveSuccess',
                0,
                { name: this.rule.name },
            );

            const titleSaveError = this.$tc('global.default.error');
            const messageSaveError = this.$tc(
                'sw-rule-modal.messageSaveError', 0, { name: this.rule.name },
            );

            this.isLoading = true;
            return this.ruleRepository.save(this.rule, Context.api).then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess,
                });

                this.loading = false;
                this.$emit('save', this.rule.id, this.rule);
                this.$emit('modal-close');
            }).catch(() => {
                this.isLoading = false;
                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError,
                });
            });
        },
    },
});
