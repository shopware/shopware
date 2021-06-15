import template from './sw-settings-rule-detail.html.twig';
import './sw-settings-rule-detail.scss';

const { Component, Mixin, Context } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-rule-detail', {
    template,

    inject: [
        'ruleConditionDataProviderService',
        'repositoryFactory',
        'acl',
        'feature',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('rule'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel',
    },

    props: {
        ruleId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            rule: null,
            conditions: null,
            conditionTree: null,
            deletedIds: [],
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.rule ? this.rule.name : '';
        },

        ruleRepository() {
            return this.repositoryFactory.create('rule');
        },

        conditionRepository() {
            if (!this.rule) {
                return null;
            }

            return this.repositoryFactory.create(
                this.rule.conditions.entity,
                this.rule.conditions.source,
            );
        },

        tooltipSave() {
            if (!this.acl.can('rule.editor')) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('rule.editor'),
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        tabItems() {
            return [
                {
                    title: this.$tc('sw-settings-rule.detail.tabGeneral'),
                    route: { name: 'sw.settings.rule.detail.base', params: { id: this.$route.params.id } },
                    cssClassSuffix: 'general',
                },
                {
                    title: this.$tc('sw-settings-rule.detail.tabAssignments'),
                    route: { name: 'sw.settings.rule.detail.assignments', params: { id: this.$route.params.id } },
                    cssClassSuffix: 'assignments',
                },
            ];
        },
    },

    watch: {
        ruleId: {
            immediate: true,
            handler() {
                if (!this.ruleId) {
                    this.createRule();
                    return;
                }

                this.isLoading = true;
                this.loadEntityData(this.ruleId).then(() => {
                    this.isLoading = false;
                });
            },
        },
    },

    methods: {
        createRule() {
            this.rule = this.ruleRepository.create(Context.api);
            this.conditions = this.rule.conditions;
        },

        loadEntityData(ruleId) {
            this.isLoading = true;
            this.conditions = null;

            return this.ruleRepository.get(ruleId, Context.api).then((rule) => {
                this.rule = rule;
                return this.loadConditions();
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

        conditionsChanged({ conditions, deletedIds }) {
            this.conditionTree = conditions;
            this.deletedIds = [...this.deletedIds, ...deletedIds];
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            if (this.rule.isNew()) {
                this.rule.conditions = this.conditionTree;
                this.saveRule().then(() => {
                    this.$router.push({ name: 'sw.settings.rule.detail', params: { id: this.rule.id } });
                    this.isSaveSuccessful = true;
                }).catch(() => {
                    this.showErrorNotification();
                });

                return;
            }

            this.saveRule()
                .then(this.syncConditions)
                .then(() => {
                    this.isSaveSuccessful = true;
                    this.loadEntityData(this.rule.id);
                })
                .then(() => {
                    this.isLoading = false;
                })
                .catch(() => {
                    this.isLoading = false;
                    this.showErrorNotification();
                });
        },

        saveRule() {
            return this.ruleRepository.save(this.rule, Context.api);
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

        showErrorNotification() {
            this.createNotificationError({
                message: this.$tc('sw-settings-rule.detail.messageSaveError', 0, { name: this.rule.name }),
            });
            this.isLoading = false;
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.rule.index' });
        },
    },
});
