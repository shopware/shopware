import template from './sw-rule-modal.html.twig';
import './sw-rule-modal.scss';

const { Component, Mixin, Context } = Shopware;
const { EntityCollection, Criteria } = Shopware.Data;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @private
 * @package business-ops
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
        'ruleConditionsConfigApiService',
        'feature',
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
        ruleAwareGroupKey: {
            type: String,
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

        appScriptConditionRepository() {
            return this.repositoryFactory.create('app_script_condition');
        },

        modalTitle() {
            if (!this.rule || this.rule.isNew()) {
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
            this.isLoading = true;

            this.loadConditionData().then((scripts) => {
                this.ruleConditionDataProviderService.addScriptConditions(scripts);
                this.rule = this.ruleRepository.create(Context.api);
                this.initialConditions = EntityCollection.fromCollection(this.rule.conditions);

                if (this.rule[this.ruleAwareGroupKey]) {
                    this.rule[this.ruleAwareGroupKey].push({});
                }

                this.isLoading = false;
            });
        },

        loadConditionData() {
            const context = { ...Context.api, languageId: Shopware.State.get('session').languageId };
            const criteria = new Criteria(1, 500);

            return Promise.all([
                this.appScriptConditionRepository.search(criteria, context),
                this.ruleConditionsConfigApiService.load(),
            ]).then((results) => {
                return results[0];
            });
        },

        conditionsChanged({ conditions }) {
            this.rule.conditions = conditions;
        },

        getChildrenConditions(condition) {
            const conditions = [];
            condition.children.forEach((child) => {
                conditions.push(child);
                if (child.children) {
                    const children = this.getChildrenConditions(child);
                    conditions.push(...children);
                }
            });

            return conditions;
        },

        validateRuleAwareness() {
            const conditions = [];
            this.rule.conditions.forEach((condition) => {
                conditions.push(condition);

                if (condition.children) {
                    const children = this.getChildrenConditions(condition);
                    conditions.push(...children);
                }
            });

            const tooltip = this.ruleConditionDataProviderService.getRestrictedRuleTooltipConfig(
                conditions,
                this.ruleAwareGroupKey,
            );

            if (!tooltip.disabled) {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: tooltip.message,
                });
                return false;
            }

            return true;
        },

        saveAndClose() {
            if (!this.validateRuleAwareness()) {
                return Promise.resolve();
            }
            if (this.rule[this.ruleAwareGroupKey]) {
                this.rule[this.ruleAwareGroupKey] = [];
            }


            const titleSaveSuccess = this.$tc('global.default.success');
            const messageSaveSuccess = this.$tc(
                'sw-rule-modal.messageSaveSuccess',
                0,
                { name: this.rule.name },
            );

            const titleSaveError = this.$tc('global.default.error');
            const messageSaveError = this.$tc('sw-rule-modal.messageSaveError', 0, { name: this.rule.name });

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
