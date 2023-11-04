/*
 * @package inventory
 */

import template from './sw-product-detail-variants.html.twig';
import './sw-product-detail-variants.scss';

const { Criteria, EntityCollection } = Shopware.Data;
const { mapState, mapGetters } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

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
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'variants',
        ]),

        ...mapState('context', {
            contextLanguageId: state => state.api.languageId,
        }),

        ...mapGetters('swProductDetail', {
            isStoreLoading: 'isLoading',
        }),

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

        selectedGroups() {
            if (!this.productEntity.configuratorSettings) {
                return [];
            }

            // get groups for selected options
            const groupIds = this.productEntity.configuratorSettings.reduce((result, element) => {
                if (result.indexOf(element.option.groupId) < 0) {
                    result.push(element.option.groupId);
                }

                return result;
            }, []);

            return this.groups.filter((group) => {
                return groupIds.indexOf(group.id) >= 0;
            });
        },

        currentProductStates() {
            return this.activeTab.split(',');
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
            if (!this.isStoreLoading) {
                this.loadOptions()
                    .then(() => {
                        return this.loadGroups();
                    });
            }
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
                this.$nextTick().then(() => {
                    const groupCriteria = new Criteria(1, null);

                    this.groupRepository.search(groupCriteria).then((searchResult) => {
                        this.groups = searchResult;
                        resolve();
                    });
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
    },
};
