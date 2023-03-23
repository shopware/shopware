import template from './sw-settings-rule-detail.html.twig';
import './sw-settings-rule-detail.scss';

const { Component, Mixin, Context } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { Criteria, EntityCollection } = Shopware.Data;

/**
 * @private
 * @package business-ops
 */
export default {
    template,

    inject: [
        'ruleConditionDataProviderService',
        'ruleConditionsConfigApiService',
        'repositoryFactory',
        'acl',
        'feature',
    ],

    mixins: [
        Mixin.getByName('notification'),
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
            conditionTreeFinishedLoading: false,
            conditionsTreeContainsUserChanges: false,
            nextRoute: null,
            isDisplayingSaveChangesWarning: false,
            forceDiscardChanges: false,
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

        ruleCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('tags');

            criteria.addAssociation('personaPromotions');
            criteria.addAssociation('orderPromotions');
            criteria.addAssociation('cartPromotions');
            criteria.addAssociation('promotionDiscounts');
            criteria.addAssociation('promotionSetGroups');
            criteria.addAssociation('flowSequences.flow');
            criteria.addAssociation('shippingMethodPriceCalculations');
            criteria.addAssociation('shippingMethodPrices');
            criteria.addAssociation('productPrices');
            criteria.addAssociation('shippingMethods');
            criteria.addAssociation('paymentMethods');

            return criteria;
        },

        appScriptConditionRepository() {
            return this.repositoryFactory.create('app_script_condition');
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

        ...mapPropertyErrors('rule', ['name', 'priority']),
    },

    watch: {
        ruleId: {
            immediate: true,
            handler() {
                this.isLoading = true;

                this.loadConditionData().then((scripts) => {
                    this.ruleConditionDataProviderService.addScriptConditions(scripts);

                    if (!this.ruleId) {
                        this.isLoading = false;
                        this.createRule();
                        return;
                    }

                    this.loadEntityData(this.ruleId).then(() => {
                        this.isLoading = false;
                    });
                    this.setTreeFinishedLoading();
                });
            },
        },
        conditionTree: {
            deep: true,
            handler() {
                if (!this.conditionTreeFinishedLoading) {
                    return;
                }
                this.conditionsTreeContainsUserChanges = true;
            },
        },

        $route(newRoute, oldRoute) {
            // Reload the rule data when switching from assignments to base tab because changes to the assignments
            // can affect the conditions that are selectable - rule awareness
            if (newRoute.name === 'sw.settings.rule.detail.base' &&
                oldRoute.name === 'sw.settings.rule.detail.assignments') {
                this.isLoading = true;
                this.loadEntityData(this.ruleId).then(() => {
                    this.isLoading = false;
                    this.setTreeFinishedLoading();
                });
            }
        },
    },

    beforeRouteUpdate(to, from, next) {
        this.unsavedDataLeaveHandler(to, from, next);
    },

    beforeRouteLeave(to, from, next) {
        this.unsavedDataLeaveHandler(to, from, next);
    },

    methods: {
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

        createRule() {
            this.rule = this.ruleRepository.create(Context.api);
            this.conditions = this.rule.conditions;
        },

        loadEntityData(ruleId) {
            this.isLoading = true;
            this.conditions = null;

            return this.ruleRepository.get(ruleId, Context.api, this.ruleCriteria).then((rule) => {
                this.rule = rule;
                return this.loadConditions();
            });
        },

        unsavedDataLeaveHandler(to, from, next) {
            if (this.forceDiscardChanges) {
                this.forceDiscardChanges = false;
                next();
                return;
            }

            if (to.name === 'sw.settings.rule.detail.assignments' && from.name === 'sw.settings.rule.detail.base') {
                this.checkUnsavedData({ to, from, next });
            } else if (to.name === 'sw.settings.rule.detail.base' || to.name === 'sw.settings.rule.create.base') {
                this.conditionsTreeContainsUserChanges = false;
                this.conditionTreeFinishedLoading = false;
                next();
                return;
            }

            this.checkUnsavedData({ to, from, next });
        },

        checkUnsavedData({ to, next }) {
            if (this.conditionsTreeContainsUserChanges || this.ruleRepository.hasChanges(this.rule)) {
                this.isDisplayingSaveChangesWarning = true;
                this.nextRoute = to;
                next(false);
            } else {
                next();
            }
        },

        setTreeFinishedLoading() {
            this.$nextTick(() => {
                this.conditionsTreeContainsUserChanges = false;
                this.conditionTreeFinishedLoading = true;
            });
        },

        onLeaveModalClose() {
            this.nextRoute = null;
            this.isDisplayingSaveChangesWarning = false;
        },

        async onLeaveModalConfirm(destination) {
            this.forceDiscardChanges = true;
            this.isDisplayingSaveChangesWarning = false;

            if (destination.name === 'sw.settings.rule.detail.assignments') {
                await this.loadEntityData(this.ruleId).then(() => {
                    this.isLoading = false;
                });
            }

            this.$nextTick(() => {
                this.$router.push({ name: destination.name, params: destination.params });
            });
        },

        loadConditions(conditions = null) {
            const context = { ...Context.api, inheritance: true };

            if (conditions === null) {
                return this.conditionRepository.search(new Criteria(1, 25), context).then((searchResult) => {
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

        validateRuleAwareness() {
            const equalsAnyConfigurations = this.ruleConditionDataProviderService.getAwarenessKeysWithEqualsAnyConfig();
            if (equalsAnyConfigurations.length <= 0) {
                return true;
            }

            let isValid = true;
            equalsAnyConfigurations.forEach((key) => {
                if (this.rule[key].length > 0) {
                    const conditions = [];
                    this.conditionTree.forEach((condition) => {
                        conditions.push(condition);

                        if (condition.children) {
                            const children = this.getChildrenConditions(condition);
                            conditions.push(...children);
                        }
                    });

                    const restrictions = this.ruleConditionDataProviderService.getRestrictionsByAssociation(
                        new EntityCollection(
                            this.conditionRepository.route,
                            this.conditionRepository.entityName,
                            Context.api,
                            null,
                            conditions,
                        ),
                        key,
                    );

                    if (restrictions.isRestricted) {
                        const message = this.$tc(
                            'sw-restricted-rules.restrictedAssignment.equalsAnyViolationTooltip',
                            0,
                            {
                                conditions: this.ruleConditionDataProviderService.getTranslatedConditionViolationList(
                                    restrictions.equalsAnyNotMatched,
                                    'sw-restricted-rules.or',
                                ),
                                entityLabel: this.$tc(restrictions.assignmentSnippet, 2),
                            },
                        );

                        this.createNotificationError({ message });
                        isValid = false;
                    }
                }
            });

            return isValid;
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

        onSave() {
            if (!this.validateRuleAwareness()) {
                return Promise.resolve();
            }

            this.isSaveSuccessful = false;
            this.isLoading = true;

            if (this.rule.isNew()) {
                this.rule.conditions = this.conditionTree;
                return this.saveRule().then(() => {
                    this.$router.push({ name: 'sw.settings.rule.detail', params: { id: this.rule.id } });
                    this.isSaveSuccessful = true;
                    this.conditionsTreeContainsUserChanges = false;
                }).catch(() => {
                    this.showErrorNotification();
                });
            }

            return this.saveRule()
                .then(this.syncConditions)
                .then(() => {
                    this.isSaveSuccessful = true;
                    this.loadEntityData(this.rule.id).then(() => {
                        this.setTreeFinishedLoading();
                    });
                })
                .then(() => {
                    this.isLoading = false;
                })
                .catch(() => {
                    this.isLoading = false;
                    this.showErrorNotification();
                });
        },

        abortOnLanguageChange() {
            return this.ruleRepository.hasChanges(this.rule);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage(languageId) {
            Shopware.State.commit('context/setApiLanguageId', languageId);

            this.isLoading = true;
            this.loadEntityData(this.ruleId).then(() => {
                this.isLoading = false;
                this.setTreeFinishedLoading();
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

        tabHasError(tab) {
            if (tab.route.name !== 'sw.settings.rule.detail.base') {
                return false;
            }

            return !!this.ruleNameError || !!this.rulePriorityError;
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.rule.index' });
        },

        onDuplicate() {
            return this.onSave().then(() => {
                const behaviour = {
                    overwrites: {
                        name: `${this.rule.name} ${this.$tc('global.default.copy')}`,
                        // setting the createdAt to null, so that api does set a new date
                        createdAt: null,
                    },
                };

                return this.ruleRepository.clone(this.rule.id, Shopware.Context.api, behaviour).then((duplicatedData) => {
                    this.$router.push(
                        {
                            name: 'sw.settings.rule.detail',
                            params: { id: duplicatedData.id },
                        },
                    );
                });
            });
        },
    },
};
