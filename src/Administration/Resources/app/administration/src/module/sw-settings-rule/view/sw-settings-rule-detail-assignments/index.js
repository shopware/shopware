import RuleAssignmentConfigurationService from 'src/module/sw-settings-rule/service/rule-assignment-configuration.service';
import template from './sw-settings-rule-detail-assignments.html.twig';
import './sw-settings-rule-detail-assignments.scss';

const { Mixin, Context, Utils } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 * @package business-ops
 */
export default {
    // eslint-disable-next-line max-len
    template,

    inject: [
        'repositoryFactory',
        'ruleConditionDataProviderService',
        'feature',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        rule: {
            type: Object,
            required: true,
        },

        conditions: {
            type: Array,
            required: false,
            default: null,
        },

        detailPageLoading: {
            type: Boolean,
            required: false,
            default: false,
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
            return Object.values(this.getRuleAssignmentConfiguration);
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
            const association = entity.associationName ?? null;
            if (this.ruleConditionDataProviderService.isRuleRestricted(this.conditions, association)) {
                return true;
            }

            return entity.notAssignedDataTotal === 0;
        },

        getTooltipConfig(entity) {
            const association = entity.associationName ?? null;

            return this.ruleConditionDataProviderService.getRestrictedRuleTooltipConfig(this.conditions, association);
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

            this.isLoading = true;
            return repository.save(this.deleteItem, api).finally(() => {
                this.isLoading = false;
            });
        },

        async refreshAssignmentData(entity) {
            this.isLoading = true;
            const api = entity.api ? entity.api() : Context.api;
            const result = await entity.repository.search(entity.criteria(), api);
            const total = await this.loadNotAssignedDataTotals(entity, api);

            this.associationEntities.forEach((currentEntity) => {
                if (entity.id === currentEntity.id) {
                    currentEntity.loadedData = result;
                    currentEntity.notAssignedDataTotal = total;
                }
            });
            this.isLoading = false;
        },

        onFilterEntity(item, term) {
            const api = item.api ? item.api() : Context.api;
            const criteria = item.criteria();

            criteria.setPage(1);
            criteria.setTerm(term);

            this.isLoading = true;
            return item.repository.search(criteria, api).then((result) => {
                item.loadedData = result;
            }).finally(() => {
                this.isLoading = false;
            });
        },

        async loadNotAssignedDataTotals(item, api) {
            if (!item.deleteContext && !item.addContext) {
                return Promise.resolve(true);
            }

            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.not('AND', item.criteria().filters));

            this.isLoading = true;
            return item.repository.search(criteria, api).then((notAssignedDataResult) => {
                return Promise.resolve(notAssignedDataResult.total);
            }).finally(() => {
                this.isLoading = false;
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

                        item.notAssignedDataTotal = await this.loadNotAssignedDataTotals(item, api);
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
};
