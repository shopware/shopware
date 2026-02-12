/*
 * @sw-package inventory
 */

import template from './sw-product-detail-variants.html.twig';
import './sw-product-detail-variants.scss';

const { Criteria, EntityCollection } = Shopware.Data;
const { uniqBy } = Shopware.Utils.array;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    data() {
        return {
            variantListHasContent: false,
            activeModal: '',
            isLoading: true,
            productEntity: {},
            configuratorSettingsRepository: {},
            groups: [],
            productEntityLoaded: false,
            propertiesAvailable: true,
            showAddPropertiesModal: false,
            defaultTab: 'all',
            activeTab: 'all',
            limit: 500,
        };
    },

    computed: {
        product() {
            return Shopware.Store.get('swProductDetail').product;
        },

        variants() {
            return Shopware.Store.get('swProductDetail').variants;
        },

        isStoreLoading() {
            return Shopware.Store.get('swProductDetail').isLoading;
        },

        contextLanguageId() {
            return Shopware.Store.get('context').api.languageId;
        },

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        groupRepository() {
            return this.repositoryFactory.create('property_group');
        },

        propertyRepository() {
            return this.repositoryFactory.create('property_group_option');
        },

        productProperties() {
            return this.isChild && this.product?.properties?.length <= 0
                ? this.parentProduct.properties
                : this.product.properties;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, use `currentProductType` instead.
         * @returns {string[]}
         */
        currentProductStates() {
            return this.activeTab.split(',');
        },

        currentProductType() {
            return this.activeTab.split(',')[0] ?? 'all';
        },

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },

        groupCriteria() {
            const criteria = new Criteria(1, this.limit);

            criteria.addFields('name');

            return criteria;
        },

        /**
         * @returns {Object[]}
         */
        configSettingGroups() {
            const settings = this.productEntity?.configuratorSettings ?? [];
            const groupIds = uniqBy(settings, 'option.groupId').map((item) => item.option.groupId);
            if (groupIds.length === 0) {
                return [];
            }
            const groupMap = new Map(
                this.groups.map((group) => [
                    group.id,
                    group,
                ]),
            );
            return groupIds.map((id) => groupMap.get(id)).filter(Boolean);
        },
    },

    watch: {
        isStoreLoading: {
            handler() {
                if (this.isStoreLoading === false) {
                    this.loadData();
                }
            },
        },

        contextLanguageId: {
            handler() {
                this.$refs.generatedVariants.getList();
            },
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        createdComponent() {
            this.checkIfPropertiesExists();
        },

        mountedComponent() {
            this.loadData();
        },

        setActiveTab(tabName) {
            this.activeTab = tabName;
        },

        loadData() {
            if (!this.isStoreLoading && this.product?.id) {
                this.loadOptions().then(() => this.loadGroups());
            }
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement.
         */
        async loadConfigSettingGroups() {
            // No-op: configSettingGroups is computed from productEntity.configuratorSettings and groups.
        },

        loadOptions() {
            return new Promise((resolve) => {
                const criteria = new Criteria(1, 25);

                criteria.addAssociation('configuratorSettings.option');
                criteria.addAssociation('prices');

                this.productRepository.get(this.product.id, Shopware.Context.api, criteria).then((product) => {
                    this.productEntity = product;
                    this.productEntityLoaded = true;

                    resolve();
                });
            });
        },

        loadGroups() {
            return new Promise((resolve) => {
                this.$nextTick().then(async () => {
                    this.groups = await this.loadAllPropertyGroups(this.groupCriteria);
                    resolve();
                });
            });
        },

        updateVariations() {
            // Reset filter
            this.$refs.generatedVariants.includeOptions = [];
            this.$refs.generatedVariants.filterWindowOpen = false;

            // get new filter options
            this.loadOptions()
                .then(() => {
                    return this.loadGroups();
                })
                .then(() => {
                    this.$refs.generatedVariants.getFilterOptions();
                    this.$refs.generatedVariants.resetFilterOptions();
                });
        },

        updateVariantListHasContent(variantList) {
            // Check for empty search or filter results
            const isFilterActive = this.$refs.generatedVariants.includeOptions.length > 0;
            const searchTerm = this.$route.query ? this.$route.query.term : '';

            this.variantListHasContent = variantList.length > 0 || searchTerm || isFilterActive;
            this.isLoading = false;
        },

        openModal(value) {
            this.activeModal = value;
        },

        onConfigurationClosed() {
            this.loadData();
            this.activeModal = '';
        },

        checkIfPropertiesExists() {
            this.propertyRepository.search(new Criteria(1, 1)).then((res) => {
                this.propertiesAvailable = res.total > 0;
            });
        },

        openAddPropertiesModal() {
            if (!this.propertiesAvailable) {
                this.$router.push({ name: 'sw.property.index' });
            } else {
                this.updateNewProperties();
                this.showAddPropertiesModal = true;
            }
        },

        closeAddPropertiesModal() {
            this.showAddPropertiesModal = false;
            this.updateNewProperties();
        },

        updateNewProperties() {
            this.newProperties = new EntityCollection(
                this.productProperties.source,
                this.productProperties.entity,
                this.productProperties.context,
                Criteria.fromCriteria(this.productProperties.criteria),
                this.productProperties,
                this.productProperties.total,
                this.productProperties.aggregations,
            );
        },

        onCancelAddPropertiesModal() {
            this.closeAddPropertiesModal();
        },

        onSaveAddPropertiesModal(newProperties) {
            this.closeAddPropertiesModal();

            if (newProperties.length <= 0) {
                return;
            }

            this.productProperties.splice(0, this.productProperties.length, ...newProperties);
        },

        async loadAllPropertyGroups(criteria) {
            const initialResult = await this.groupRepository.search(criteria);
            const totalGroups = initialResult.total ?? initialResult.length ?? 0;
            const limit = initialResult.length || criteria.limit || 25;

            const totalPages = Math.ceil(totalGroups / limit);

            const promises = [];
            // eslint-disable-next-line no-plusplus
            for (let page = 2; page <= totalPages; page++) {
                const nextCriteria = Criteria.fromCriteria(criteria).setPage(page).setLimit(limit);
                promises.push(this.groupRepository.search(nextCriteria));
            }

            const results = await Promise.all(promises);

            return [
                initialResult,
                ...results,
            ].flatMap((result) => result);
        },
    },
};
