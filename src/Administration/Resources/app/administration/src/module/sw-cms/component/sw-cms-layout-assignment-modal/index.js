import { difference } from 'lodash/array';
import template from './sw-cms-layout-assignment-modal.html.twig';
import './sw-cms-layout-assignment-modal.scss';

const { EntityCollection } = Shopware.Data;
const { cloneDeep } = Shopware.Utils.object;

Shopware.Component.register('sw-cms-layout-assignment-modal', {
    template,

    props: {
        page: {
            type: Object,
            required: true
        }
    },

    mixins: [
        Shopware.Mixin.getByName('notification')
    ],

    inject: [
        'systemConfigApiService',
        'acl'
    ],

    data() {
        return {
            shopPageSalesChannelId: null,
            previousCategories: null,
            showConfirmChangesModal: false,
            isLoading: false,
            systemConfig: null,
            selectedShopPages: {},
            previousShopPages: {},
            confirmedCategories: false,
            confirmedShopPages: false,
            hasDeletedCategories: false,
            hasDeletedShopPages: false,
            hasCategoriesWithAssignedLayouts: false
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        systemConfigDomain() {
            return 'core.basicInformation';
        },

        shopPages() {
            return [
                {
                    label: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.shopPages.tosPage'),
                    value: 'core.basicInformation.tosPage'
                },
                {
                    label: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.shopPages.revocationPage'),
                    value: 'core.basicInformation.revocationPage'
                },
                {
                    label: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.shopPages.shippingPaymentInfoPage'),
                    value: 'core.basicInformation.shippingPaymentInfoPage'
                },
                {
                    label: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.shopPages.privacyPage'),
                    value: 'core.basicInformation.privacyPage'
                },
                {
                    label: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.shopPages.imprintPage'),
                    value: 'core.basicInformation.imprintPage'
                },
                {
                    label: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.shopPages.404Page'),
                    value: 'core.basicInformation.404Page'
                },
                {
                    label: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.shopPages.maintenancePage'),
                    value: 'core.basicInformation.maintenancePage'
                },
                {
                    label: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.shopPages.contactPage'),
                    value: 'core.basicInformation.contactPage'
                },
                {
                    label: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.shopPages.newsletterPage'),
                    value: 'core.basicInformation.newsletterPage'
                }
            ];
        }
    },

    methods: {
        createdComponent() {
            this.previousCategories = [...this.page.categories];
            this.previousCategoryIds = this.page.categories.getIds();

            this.loadSystemConfig();
        },

        onModalClose() {
            this.$emit('modal-close');
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
                        message: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.shopPagesSaveError')
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
                        message: this.$tc('sw-cms.components.cmsLayoutAssignmentModal.shopPagesLoadError')
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

        onConfirm() {
            this.isLoading = true;

            Promise
                .all([this.validateCategories(), this.saveShopPages()])
                .then(() => {
                    this.$emit('confirm');

                    this.onModalClose();
                }).finally(() => {
                    this.isLoading = false;
                });
        },

        openConfirmChangesModal() {
            this.showConfirmChangesModal = true;
        },

        closeConfirmChangesModal() {
            this.showConfirmChangesModal = false;
        },

        onDiscardChanges() {
            this.discardCategoryChanges();
            this.discardShopPageChanges();

            this.closeConfirmChangesModal();
        },

        discardCategoryChanges() {
            this.page.categories = new EntityCollection(
                this.page.categories.source,
                this.page.categories.entity,
                Shopware.Context.api,
                null,
                this.previousCategories
            );
        },

        discardShopPageChanges() {
            if (this.page.type !== 'page') {
                return;
            }

            this.selectedShopPages = this.previousShopPages;
        },

        onAbort() {
            this.discardCategoryChanges();
            this.discardShopPageChanges();

            this.onModalClose();
        },

        onKeepEditing() {
            this.closeConfirmChangesModal();
        },

        async onConfirmChanges() {
            this.closeConfirmChangesModal();

            this.confirmedCategories = true;
            this.confirmedShopPages = true;

            // Wait until "confirm changes" modal is closed
            await this.$nextTick();

            this.onConfirm();
        },

        onInputSalesChannelSelect() {
            this.loadSystemConfig();
        }
    }
});
