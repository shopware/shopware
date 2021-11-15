import RuleAssignmentConfigurationService from 'src/module/sw-settings-rule/service/rule-assignment-configuration.service';
/** @feature-deprecated (flag:FEATURE_NEXT_16902) Replaced with "templateFeatureNext16902"  */
import template from './sw-settings-rule-detail-assignments.html.twig';
import templateFeatureNext16902 from './sw-settings-rule-detail-assignments-feature-next-16902.html.twig';
import './sw-settings-rule-detail-assignments.scss';

const { Component, Mixin, Context, Feature, Utils } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-rule-detail-assignments', {
    /** @feature-deprecated (flag:FEATURE_NEXT_16902) Rename "templateFeatureNext16902" to "template" and delete old template  */
    template: Feature.isActive('FEATURE_NEXT_16902') ? templateFeatureNext16902 : template,

    inject: [
        'repositoryFactory',
        'feature',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        rule: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            associationLimit: 5,
            isLoading: false,
            ruleAssociationsLoaded: false,
            products: null,
            shippingMethods: null,
            paymentMethods: null,
            promotions: null,
            eventActions: null,
            associationSteps: [5, 10],
            associationEntities: null,
            deleteModal: false,
            deleteEntity: null,
            deleteItem: null,
            addModal: false,
            addEntityContext: null,
        };
    },

    computed: {
        getRuleAssignmentConfiguration() {
            return RuleAssignmentConfigurationService(this.rule.id, this.associationLimit).getConfiguration();
        },

        /** @feature-deprecated (flag:FEATURE_NEXT_16902) tag:v6.5.0 Unused method will be removed */
        hasNoAssociations() {
            return this.associationEntities.every((entity) => {
                return entity.loadedData && entity.loadedData.total === 0;
            });
        },

        /* eslint-disable max-len */
        /**
         * Definition of the associated entities of the current rule.
         * The component will render a sw-entity-listing for each association entity,
         * if results are given.
         *
         * @type {[{id: String, notAssignedDataTotal: int, entityName: String, label: String, criteria: Function, api: Function, detailRoute: String, gridColumns: Array<Object>, deleteContext: Object, addContext: Object }]}
         * @returns {Array<Object>}
         */
        /* eslint-enable max-len */
        associationEntitiesConfig() {
            if (!this.feature.isActive('FEATURE_NEXT_16902')) {
                return [
                    {
                        entityName: 'product',
                        label: 'sw-settings-rule.detail.associations.products',
                        criteria: () => {
                            const criteria = new Criteria();
                            criteria.setLimit(this.associationLimit);
                            criteria.addFilter(Criteria.equals('prices.rule.id', this.rule.id));

                            return criteria;
                        },
                        api: () => {
                            const api = Object.assign({}, Context.api);
                            api.inheritance = true;

                            return api;
                        },
                        detailRoute: 'sw.product.detail.prices',
                        gridColumns: [
                            {
                                property: 'name',
                                label: 'Name',
                                rawData: true,
                                sortable: false,
                                routerLink: 'sw.product.detail.prices',
                                allowEdit: false,
                            },
                        ],
                    },
                    {
                        entityName: 'shipping_method',
                        label: 'sw-settings-rule.detail.associations.shippingMethods',
                        criteria: () => {
                            const criteria = new Criteria();
                            criteria.setLimit(this.associationLimit);
                            criteria.addFilter(
                                Criteria.multi(
                                    'OR',
                                    [
                                        Criteria.equals('prices.ruleId', this.rule.id),
                                        Criteria.equals('prices.calculationRuleId', this.rule.id),
                                        Criteria.equals('availabilityRuleId', this.rule.id),
                                    ],
                                ),
                            );

                            return criteria;
                        },
                        detailRoute: 'sw.settings.shipping.detail',
                        gridColumns: [
                            {
                                property: 'name',
                                label: 'Name',
                                rawData: true,
                                sortable: false,
                                routerLink: 'sw.settings.shipping.detail',
                                allowEdit: false,
                            },
                        ],
                    },
                    {
                        entityName: 'payment_method',
                        label: 'sw-settings-rule.detail.associations.paymentMethods',
                        criteria: () => {
                            const criteria = new Criteria();
                            criteria.setLimit(this.associationLimit);
                            criteria.addFilter(Criteria.equals('availabilityRuleId', this.rule.id));

                            return criteria;
                        },
                        detailRoute: 'sw.settings.payment.detail',
                        gridColumns: [
                            {
                                property: 'name',
                                label: 'Name',
                                rawData: true,
                                sortable: false,
                                routerLink: 'sw.settings.payment.detail',
                                allowEdit: false,
                            },
                        ],
                    },
                    {
                        entityName: 'promotion',
                        label: 'sw-settings-rule.detail.associations.promotions',
                        criteria: () => {
                            const criteria = new Criteria();
                            criteria.setLimit(this.associationLimit);
                            criteria.addFilter(
                                Criteria.multi(
                                    'OR',
                                    [
                                        Criteria.equals('personaRules.id', this.rule.id),
                                        Criteria.equals('orderRules.id', this.rule.id),
                                        Criteria.equals('cartRules.id', this.rule.id),
                                        Criteria.equals('discounts.discountRules.id', this.rule.id),
                                        Criteria.equals('setgroups.setGroupRules.id', this.rule.id),
                                    ],
                                ),
                            );

                            return criteria;
                        },
                        detailRoute: 'sw.promotion.v2.detail.conditions',
                        gridColumns: [
                            {
                                property: 'name',
                                label: 'Name',
                                rawData: true,
                                sortable: false,
                                routerLink: 'sw.promotion.v2.detail.conditions',
                            },
                        ],
                    },
                    {
                        entityName: 'event_action',
                        label: 'sw-settings-rule.detail.associations.eventActions',
                        criteria: () => {
                            const criteria = new Criteria();
                            criteria.setLimit(this.associationLimit);
                            criteria.addFilter(Criteria.equals('rules.id', this.rule.id));

                            return criteria;
                        },
                        detailRoute: 'sw.event.action.detail',
                        gridColumns: [
                            {
                                property: 'eventName',
                                label: 'Business Event',
                                rawData: true,
                                sortable: false,
                                width: '50%',
                                routerLink: 'sw.event.action.detail',
                            },
                            {
                                property: 'title',
                                label: 'Business Event Title',
                                rawData: true,
                                sortable: false,
                                width: '50%',
                                routerLink: 'sw.event.action.detail',
                            },
                        ],
                    },
                ];
            }

            return Object.values(this.getRuleAssignmentConfiguration);
        },

        /** @feature-deprecated (flag:FEATURE_NEXT_16902) tag:v6.5.0 Unused method will be removed */
        loadedAssociationEntities() {
            return this.associationEntities.filter((item) => {
                return item.loadedData && item.loadedData.total > 0;
            });
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.prepareAssociationEntitiesList();
            this.loadAssociationData();
        },

        disableAdd(entity) {
            return entity.notAssignedDataTotal === 0;
        },

        allowDeletion(entity) {
            return !!entity.deleteContext;
        },

        prepareAssociationEntitiesList() {
            this.associationEntities = this.associationEntitiesConfig.map((item) => {
                return {
                    repository: this.repositoryFactory.create(item.entityName),
                    loadedData: null,
                    ...item,
                };
            });
        },

        onOpenDeleteModal(entity, item) {
            this.deleteModal = true;
            this.deleteEntity = entity;
            this.deleteItem = item;
        },

        onCloseDeleteModal() {
            this.deleteModal = false;
            this.deleteContext = null;
            this.deleteItem = null;
        },

        onOpenAddModal(entityContext) {
            this.addModal = true;
            this.addEntityContext = entityContext;
        },

        onCloseAddModal() {
            this.addModal = false;
            this.addEntityContext = null;
        },

        onEntitiesSaved() {
            this.addModal = false;

            return this.refreshAssignmentData(this.addEntityContext);
        },

        async onDeleteItems(entity, selection) {
            await Promise.all(Object.values(selection).map(async (item) => {
                this.deleteEntity = entity;
                this.deleteItem = item;

                await this.doDeleteItem();
            }));

            return this.refreshAssignmentData(entity).then(() => {
                this.onCloseDeleteModal();
            });
        },

        onDelete() {
            return this.doDeleteItem().then(() => {
                return this.refreshAssignmentData(this.deleteEntity).then(() => {
                    this.onCloseDeleteModal();
                });
            });
        },

        doDeleteItem() {
            const api = this.deleteEntity.api ? this.deleteEntity.api() : Context.api;
            const repository = this.repositoryFactory.create(this.deleteItem.getEntityName());

            if (this.deleteEntity.deleteContext.type === 'one-to-many') {
                Utils.object.set(this.deleteItem, this.deleteEntity.deleteContext.column, null);
            } else {
                Utils.object.get(this.deleteItem, this.deleteEntity.deleteContext.column).remove(this.rule.id);
            }

            return repository.save(this.deleteItem, api);
        },

        async refreshAssignmentData(entity) {
            const api = entity.api ? entity.api() : Context.api;
            const result = await entity.repository.search(entity.criteria(), api);
            const total = await this.loadNotAssignedDataTotals(entity, api);

            this.associationEntities.forEach((currentEntity) => {
                if (entity.id === currentEntity.id) {
                    currentEntity.loadedData = result;
                    currentEntity.notAssignedDataTotal = total;
                }
            });
        },

        onFilterEntity(item, term) {
            const api = item.api ? item.api() : Context.api;
            const criteria = item.criteria();

            criteria.setPage(1);
            criteria.setTerm(term);

            return item.repository.search(criteria, api).then((result) => {
                item.loadedData = result;
            });
        },

        async loadNotAssignedDataTotals(item, api) {
            if (!item.deleteContext && !item.addContext) {
                return Promise.resolve(true);
            }

            const criteria = new Criteria();
            criteria.addFilter(Criteria.not('AND', item.criteria().filters));
            criteria.setLimit(1);

            return item.repository.search(criteria, api).then((notAssignedDataResult) => {
                return Promise.resolve(notAssignedDataResult.total);
            });
        },

        getRouterLink(entity, item) {
            return { name: entity.detailRoute, params: { id: item.id } };
        },

        loadAssociationData() {
            this.isLoading = true;

            return Promise
                .all(this.associationEntities.map((item) => {
                    const api = item.api ? item.api() : Context.api;

                    return item.repository.search(item.criteria(), api).then(async (result) => {
                        item.loadedData = result;

                        if (this.feature.isActive('FEATURE_NEXT_16902')) {
                            item.notAssignedDataTotal = await this.loadNotAssignedDataTotals(item, api);
                        }
                    });
                }))
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-settings-rule.detail.associationsLoadingError'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },
    },
});
