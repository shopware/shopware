import { Component } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-product-detail-variants.html.twig';
import './sw-product-detail-variants.scss';

Component.register('sw-product-detail-variants', {
    template,

    inject: ['repositoryFactory', 'context'],

    data() {
        return {
            languageId: null,
            variantListHasContent: false,
            activeModal: '',
            isLoading: true,
            productEntity: {},
            configuratorSettingsRepository: {},
            groups: [],
            productEntityLoaded: false
        };
    },

    props: {
        product: {
            type: Object,
            required: true,
            default: {}
        }
    },

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        },

        groupRepository() {
            return this.repositoryFactory.create('property_group');
        },

        selectedGroups() {
            // get groups for selected options
            const groupIds = Object.values(this.productEntity.configuratorSettings.items).reduce((result, element) => {
                if (result.indexOf(element.option.groupId) < 0) {
                    result.push(element.option.groupId);
                }

                return result;
            }, []);

            return this.groups.filter((group) => {
                return groupIds.indexOf(group.id) >= 0;
            });
        }
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.loadData();
        },

        loadData() {
            this.loadOptions()
                .then(() => {
                    return this.loadGroups();
                });
        },

        loadOptions() {
            return new Promise((resolve) => {
                const criteria = new Criteria();
                const configuratorSettingsCriteria = new Criteria();
                configuratorSettingsCriteria.setLimit(500);

                criteria.addAssociation('configuratorSettings', configuratorSettingsCriteria);

                this.productRepository.get(this.product.id, this.context, criteria).then((product) => {
                    this.productEntity = product;
                    this.productEntityLoaded = true;
                    resolve();
                });
            });
        },

        loadGroups() {
            return new Promise((resolve) => {
                this.$nextTick().then(() => {
                    const groupCriteria = new Criteria();
                    groupCriteria
                        .setLimit(100)
                        .setPage(1);

                    this.groupRepository.search(groupCriteria, this.context).then((response) => {
                        this.groups = Object.values(response.items);
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

            this.variantListHasContent = Object.keys(variantList).length > 0 || searchTerm || isFilterActive;
            this.isLoading = false;
        },

        openModal(value) {
            this.activeModal = value;
        },

        onConfigurationClosed() {
            this.loadData();
            this.activeModal = '';
        }
    }
});
