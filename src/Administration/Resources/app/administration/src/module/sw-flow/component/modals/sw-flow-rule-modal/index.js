import template from './sw-flow-rule-modal.html.twig';
import './sw-flow-rule-modal.scss';

const { Component, Mixin, Context } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Component.getComponentHelper();

Component.register('sw-flow-rule-modal', {
    template,

    inject: [
        'repositoryFactory',
        'ruleConditionDataProviderService',
    ],

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
    ],

    props: {
        ruleId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            isLoading: false,
            isSaveLoading: false,
            isSaveSuccessful: false,
            rule: null,
            conditions: null,
            conditionTree: null,
            deletedIds: [],
        };
    },

    computed: {
        modalTitle() {
            return this.ruleId
                ? this.$tc('sw-flow.modals.rule.labelEditRule')
                : this.$tc('sw-flow.modals.rule.labelAddNewRule');
        },

        ruleRepository() {
            return this.repositoryFactory.create('rule');
        },

        conditionRepository() {
            if (!this.rule) {
                return null;
            }

            return this.repositoryFactory.create(
                this.rule?.conditions?.entity,
                this.rule?.conditions?.source,
            );
        },

        availableModuleTypes() {
            return this.ruleConditionDataProviderService.getModuleTypes(moduleType => moduleType);
        },

        moduleTypes: {
            get() {
                if (!this.rule || !this.rule.moduleTypes) {
                    return [];
                }
                return this.rule.moduleTypes.types;
            },

            set(value) {
                if (value === null || value.length === 0) {
                    this.rule.moduleTypes = null;
                    return;
                }

                this.rule.moduleTypes = { types: value };
            },
        },

        ...mapPropertyErrors('rule', ['name', 'priority']),
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.ruleId) {
                this.createRule();
                return;
            }

            this.isLoading = true;
            this.loadRule(this.ruleId).then(() => {
                this.isLoading = false;
            });
        },

        createRule() {
            this.rule = this.ruleRepository.create(Context.api);
            this.conditions = this.rule?.conditions;
        },

        loadRule(ruleId) {
            this.isLoading = true;
            this.conditions = null;

            return this.ruleRepository.get(ruleId, Context.api).then((rule) => {
                this.rule = rule;
                this.loadConditions();
            });
        },

        loadConditions(conditions = null) {
            const context = { ...Context.api, inheritance: true };

            if (conditions === null) {
                return this.conditionRepository.search(new Criteria(), context).then((searchResult) => {
                    return this.loadConditions(searchResult);
                });
            }

            if (conditions.total <= conditions.length) {
                this.conditions = conditions;
                return Promise.resolve();
            }

            const criteria = new Criteria(
                conditions.criteria.page + 1,
                conditions.criteria.limit,
            );

            if (conditions.entity === 'product') {
                criteria.addAssociation('options.group');
            }

            return this.conditionRepository.search(criteria, conditions.context).then((searchResult) => {
                conditions.push(...searchResult);
                conditions.criteria = searchResult.criteria;
                conditions.total = searchResult.total;

                return this.loadConditions(conditions);
            });
        },

        syncConditions() {
            return this.conditionRepository.sync(this.conditionTree, Context.api)
                .then(() => {
                    if (this.deletedIds.length > 0) {
                        return this.conditionRepository.syncDeleted(this.deletedIds, Context.api).then(() => {
                            this.deletedIds = [];
                        });
                    }
                    return Promise.resolve();
                });
        },

        onConditionsChanged({ conditions, deletedIds }) {
            this.conditionTree = conditions;
            this.deletedIds = [...this.deletedIds, ...deletedIds];
        },

        getRuleDetail() {
            if (!this.rule?.id) {
                return null;
            }

            return this.ruleRepository.get(this.rule.id)
                .then((rule) => {
                    this.$emit('process-finish', rule);
                })
                .catch(() => {
                    this.$emit('process-finish', null);
                })
                .finally(() => {
                    this.onClose();
                });
        },

        onSaveRule() {
            this.isSaveSuccessful = false;
            this.isSaveLoading = true;

            if (this.rule.isNew()) {
                this.rule.conditions = this.conditionTree;

                this.saveRule()
                    .then(() => {
                        Shopware.State.dispatch('error/resetApiErrors');
                        this.getRuleDetail();

                        this.isSaveSuccessful = true;
                    }).catch(() => {
                        this.showErrorNotification();
                    }).finally(() => {
                        this.isSaveLoading = false;
                    });

                return;
            }

            this.saveRule()
                .then(this.syncConditions)
                .then(() => {
                    Shopware.State.dispatch('error/resetApiErrors');
                    this.getRuleDetail();

                    this.isSaveSuccessful = true;
                })
                .catch(() => {
                    this.showErrorNotification();
                })
                .finally(() => {
                    this.isSaveLoading = false;
                });
        },

        saveRule() {
            return this.ruleRepository.save(this.rule, Context.api);
        },

        showErrorNotification() {
            this.createNotificationError({
                message: this.$tc('sw-settings-rule.detail.messageSaveError', 0, { name: this.rule.name }),
            });
        },

        onClose() {
            this.$emit('modal-close');
        },
    },
});
