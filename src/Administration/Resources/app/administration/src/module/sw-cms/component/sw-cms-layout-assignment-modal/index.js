import { difference } from 'lodash/array';
import template from './sw-cms-layout-assignment-modal.html.twig';
import './sw-cms-layout-assignment-modal.scss';

const { cloneDeep } = Shopware.Utils.object;
const { EntityCollection, Criteria } = Shopware.Data;

/**
 * @private
 * @package buyers-experience
 */
export default {
    template,

    inject: [
        'repositoryFactory',
        'systemConfigApiService',
        'acl',
    ],

    mixins: [
        Shopware.Mixin.getByName('notification'),
    ],

    props: {
        page: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            shopPageSalesChannelId: null,
            previousCategories: null,
            previousLandingPages: null,
            showConfirmChangesModal: false,
            isLoading: false,
            systemConfig: null,
            selectedShopPages: {},
            previousShopPages: {},
            confirmedCategories: false,
            confirmedShopPages: false,
            confirmedProducts: false,
            confirmedLandingPages: false,
            hasDeletedCategories: false,
            hasDeletedShopPages: false,
            hasDeletedProducts: false,
            hasDeletedLandingPages: false,
            hasCategoriesWithAssignedLayouts: false,
            hasProductsWithAssignedLayouts: false,
            hasLandingPagesWithAssignedLayouts: false,
            previousProducts: null,
            categoryIndex: 1,
            isCategoriesLoading: false,
        };
    },

    computed: {
        systemConfigDomain() {
            return 'core.basicInformation';
        },

        shopPages() {
            return [
                {
                    label: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.shopPages.tosPage'),
                    value: 'core.basicInformation.tosPage',
                },
                {
                    label: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.shopPages.revocationPage'),
                    value: 'core.basicInformation.revocationPage',
                },
                {
                    label: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.shopPages.shippingPaymentInfoPage'),
                    value: 'core.basicInformation.shippingPaymentInfoPage',
                },
                {
                    label: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.shopPages.privacyPage'),
                    value: 'core.basicInformation.privacyPage',
                },
                {
                    label: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.shopPages.imprintPage'),
                    value: 'core.basicInformation.imprintPage',
                },
                {
                    label: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.shopPages.404Page'),
                    value: 'core.basicInformation.404Page',
                },
                {
                    label: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.shopPages.maintenancePage'),
                    value: 'core.basicInformation.maintenancePage',
                },
                {
                    label: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.shopPages.contactPage'),
                    value: 'core.basicInformation.contactPage',
                },
                {
                    label: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.shopPages.newsletterPage'),
                    value: 'core.basicInformation.newsletterPage',
                },
            ];
        },

        productColumns() {
            return [
                {
                    property: 'name',
                    label: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.products.columnNameLabel'),
                    dataIndex: 'name',
                    routerLink: 'sw.product.detail',
                    sortable: false,
                }, {
                    property: 'manufacturer.name',
                    label: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.products.columnManufacturerLabel'),
                    routerLink: 'sw.manufacturer.detail',
                    sortable: false,
                },
            ];
        },

        productCriteria() {
            const productCriteria = new Criteria(1, 5);
            productCriteria
                .addAssociation('options.group')
                .addAssociation('manufacturer')
                .addFilter(Criteria.equals('parentId', null));
            return productCriteria;
        },

        isProductDetailPage() {
            return this.page.type === 'product_detail';
        },

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },

        categoryRepository() {
            return this.repositoryFactory.create('category');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.previousCategories = [...this.page.categories];
            this.previousCategoryIds = this.page.categories.getIds();

            this.previousLandingPages = [...this.page.landingPages];
            this.previousLandingPageIds = this.page.landingPages.getIds();

            this.previousProducts = [...this.page.products];
            this.previousProductIds = this.page.products.getIds();

            this.loadSystemConfig();
        },

        onModalClose(saveAfterClose = false) {
            this.$emit('modal-close', saveAfterClose);
        },

        saveShopPages() {
            if (this.page.type !== 'page' || !this.acl.can('system.system_config')) {
                return Promise.resolve();
            }

            const shopPages = {};
            let deletions = 0;

            Object.keys(this.selectedShopPages).forEach((salesChannelId) => {
                shopPages[salesChannelId] = {};

                if (this.selectedShopPages[salesChannelId] === null) {
                    return;
                }

                this.selectedShopPages[salesChannelId].forEach((name) => {
                    shopPages[salesChannelId][name] = this.page.id;
                });
            });

            // Set deleted items to null for API request
            Object.keys(this.previousShopPages).forEach((salesChannelId) => {
                if (this.previousShopPages[salesChannelId] === null) {
                    return;
                }

                this.previousShopPages[salesChannelId].forEach((name) => {
                    if (shopPages[salesChannelId][name] === undefined) {
                        shopPages[salesChannelId][name] = null;
                        deletions += 1;
                    }
                });
            });

            if (!this.confirmedShopPages && deletions > 0) {
                this.hasDeletedShopPages = true;
                this.openConfirmChangesModal();
                return Promise.reject();
            }

            return this.systemConfigApiService
                .batchSave(shopPages)
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.shopPagesSaveError'),
                    });
                });
        },

        loadSystemConfig() {
            if (this.page.type !== 'page' || !this.acl.can('system.system_config')) {
                return false;
            }

            if (this.selectedShopPages.hasOwnProperty(this.shopPageSalesChannelId)) {
                return false;
            }

            this.isLoading = true;

            return this.systemConfigApiService
                .getValues(this.systemConfigDomain, this.shopPageSalesChannelId)
                .then((values) => {
                    const pages = [];

                    Object.keys(values).forEach((key) => {
                        const found = this.shopPages.find((item) => {
                            return item.value === key;
                        });

                        if (found && values[key] === this.page.id) {
                            pages.push(key);
                        }
                    });

                    if (pages.length > 0) {
                        this.$set(this.selectedShopPages, this.shopPageSalesChannelId, pages);
                    } else {
                        this.$set(this.selectedShopPages, this.shopPageSalesChannelId, null);
                    }

                    this.previousShopPages = cloneDeep(this.selectedShopPages);
                }).catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.shopPagesLoadError'),
                    });
                }).finally(() => {
                    this.isLoading = false;
                });
        },

        validateCategories() {
            // Skip validation when user has confirmed changes
            if (this.confirmedCategories) {
                return Promise.resolve();
            }

            const currentCategoryIds = this.page.categories.getIds();
            const categoryDiff = difference(currentCategoryIds, this.previousCategoryIds);

            if ((this.previousCategoryIds.length > currentCategoryIds.length) ||
                (this.previousCategoryIds.length === currentCategoryIds.length && categoryDiff.length)) {
                this.hasDeletedCategories = true;
                this.openConfirmChangesModal();
                return Promise.reject();
            }

            // Search for categories which already have a different layout
            const foundCategoriesWithAssignedLayouts = this.page.categories.find((category) => {
                return category.hasOwnProperty('cmsPageId') &&
                    category.cmsPageId !== null &&
                    category.cmsPageId !== this.page.id;
            });

            if (foundCategoriesWithAssignedLayouts) {
                this.hasCategoriesWithAssignedLayouts = true;
                this.openConfirmChangesModal();
                return Promise.reject();
            }

            return Promise.resolve();
        },

        validateLandingPages() {
            // Skip validation when user has confirmed changes
            if (this.confirmedLandingPages) {
                return Promise.resolve();
            }

            const currentLandingPageIds = this.page.landingPages.getIds();
            const landingPageDiff = difference(currentLandingPageIds, this.previousLandingPageIds);

            if ((this.previousLandingPageIds.length > currentLandingPageIds.length) ||
                (this.previousLandingPageIds.length === currentLandingPageIds.length && landingPageDiff.length)) {
                this.hasDeletedLandingPages = true;
                this.openConfirmChangesModal();
                return Promise.reject();
            }

            // Search for categories which already have a different layout
            const foundLandingPagesWithAssignedLayouts = this.page.landingPages.find((landingPage) => {
                return landingPage.hasOwnProperty('cmsPageId') &&
                    landingPage.cmsPageId !== null &&
                    landingPage.cmsPageId !== this.page.id;
            });

            if (foundLandingPagesWithAssignedLayouts) {
                this.hasLandingPagesWithAssignedLayouts = true;
                this.openConfirmChangesModal();
                return Promise.reject();
            }

            return Promise.resolve();
        },

        validateProducts() {
            // Skip validation when user has confirmed changes
            if (this.confirmedProducts) {
                return Promise.resolve();
            }

            const currentProductIds = this.page.products.getIds();
            const productDiff = difference(currentProductIds, this.previousProductIds);

            if ((this.previousProductIds.length > currentProductIds.length) ||
                (this.previousProductIds.length === currentProductIds.length && productDiff.length)) {
                this.hasDeletedProducts = true;
                this.openConfirmChangesModal();
                return Promise.reject();
            }

            const foundProductsWithAssignedLayouts = this.page.products.find((product) => {
                return product.hasOwnProperty('cmsPageId') &&
                    product.cmsPageId !== null &&
                    product.cmsPageId !== this.page.id;
            });

            if (foundProductsWithAssignedLayouts) {
                this.hasProductsWithAssignedLayouts = true;
                this.openConfirmChangesModal();
                return Promise.reject();
            }

            return Promise.resolve();
        },

        onConfirm() {
            this.isLoading = true;

            Promise
                .all([this.validateCategories(), this.saveShopPages(), this.validateProducts(), this.validateLandingPages()])
                .then(() => {
                    this.onModalClose(true);
                }).catch(() => {
                    this.isLoading = false;
                });
        },

        openConfirmChangesModal() {
            this.showConfirmChangesModal = true;
        },

        closeConfirmChangesModal() {
            this.showConfirmChangesModal = false;
        },

        async onDiscardChanges() {
            this.discardCategoryChanges();
            this.discardShopPageChanges();
            this.discardProductChanges();
            this.discardLandingPageChanges();

            this.closeConfirmChangesModal();

            // Wait until "confirm changes" modal is closed
            await this.$nextTick();

            this.onModalClose();
        },

        discardCategoryChanges() {
            this.page.categories = new EntityCollection(
                this.page.categories.source,
                this.page.categories.entity,
                Shopware.Context.api,
                null,
                this.previousCategories,
            );
        },

        discardLandingPageChanges() {
            this.page.landingPages = new EntityCollection(
                this.page.landingPages.source,
                this.page.landingPages.entity,
                Shopware.Context.api,
                null,
                this.previousLandingPages,
            );
        },

        discardShopPageChanges() {
            if (this.page.type !== 'page') {
                return;
            }

            this.selectedShopPages = this.previousShopPages;
        },

        discardProductChanges() {
            this.page.products = new EntityCollection(
                this.page.products.source,
                this.page.products.entity,
                Shopware.Context.api,
                null,
                this.previousProducts,
            );
        },

        onAbort() {
            this.discardCategoryChanges();
            this.discardShopPageChanges();
            this.discardProductChanges();
            this.discardLandingPageChanges();

            this.onModalClose();
        },

        onKeepEditing() {
            this.closeConfirmChangesModal();
        },

        async onConfirmChanges() {
            this.closeConfirmChangesModal();

            this.confirmedCategories = true;
            this.confirmedLandingPages = true;
            this.confirmedShopPages = true;
            this.confirmedProducts = true;

            // Wait until "confirm changes" modal is closed
            await this.$nextTick();

            this.onConfirm();
        },

        onInputSalesChannelSelect() {
            this.loadSystemConfig();
        },

        onExtraCategories() {
            this.isCategoriesLoading = true;
            this.categoryIndex += 1;

            const criteria = new Criteria(this.categoryIndex, 25);

            criteria.addFilter(Criteria.equals('cmsPageId', this.page.id));

            this.categoryRepository.search(criteria).then((result) => {
                if (!!result && result.length > 0) {
                    this.page.categories.push(...result);
                }

                this.isCategoriesLoading = false;
            });
        },
    },
};
