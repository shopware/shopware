import template from './sw-shortcut-overview.html.twig';
import './sw-shortcut-overview.scss';

const { Component } = Shopware;
const utils = Shopware.Utils;

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Component.register('sw-shortcut-overview', {
    template,

    shortcuts: {
        '?': 'onOpenShortcutOverviewModal',
    },

    data() {
        return {
            showShortcutOverviewModal: false,
        };
    },

    computed: {
        sections() {
            return {
                addingItems: [
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionAddProduct'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutAddProduct'),
                        privilege: 'product.creator',
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionAddCategory'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutAddCategory'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionAddCustomer'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutAddCustomer'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionAddProperties'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutAddProperties'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionAddManufacturer'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutAddManufacturer'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionAddRule'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutAddRule'),
                    },
                ],
                navigation: [
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionGoToDashboard'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToDashboard'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionGoToProducts'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToProducts'),
                        privilege: 'product.viewer',
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionGoToCategories'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToCategories'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionGoToDynamicProductGroups'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToDynamicProductGroups'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionGoToProperties'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToProperties'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionGoToManufacturers'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToManufacturers'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionGoToOrders'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToOrders'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionGoToCustomers'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToCustomers'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionGoToShoppingExperience'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToShoppingExperience'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionGoToMedia'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToMedia'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionGoToPromotion'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToPromotion'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionGoToNewsletterRecipients'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToNewsletterRecipients'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionGoToSettingsListing'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToSettingsListing'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionGoToSnippets'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToSnippets'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionGoToPayment'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToPayment'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionGoToShipping'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToShipping'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionGoToRuleBuilder'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToRuleBuilder'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionGoToPlugins'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToPlugins'),
                        privilege: 'system.plugin_maintain',
                    },

                ],

                specialShortcuts: [
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionSpecialShortcutFocusSearch'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutSpecialShortcutFocusSearch'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionSpecialShortcutShortcutListing'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutSpecialShortcutShortcutListing'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionSpecialShortcutSaveDetailViewWindows'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutSpecialShortcutSaveDetailViewWindows'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionSpecialShortcutSaveDetailViewMac'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutSpecialShortcutSaveDetailViewMac'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionSpecialShortcutSaveDetailViewLinux'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutSpecialShortcutSaveDetailViewLinux'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionSpecialShortcutCancelDetailView'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutSpecialShortcutCancelDetailView'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionSpecialShortcutClearCacheWindows'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutSpecialShortcutClearCacheWindows'),
                        privilege: 'system.clear_cache',
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionSpecialShortcutClearCacheMac'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutSpecialShortcutClearCacheMac'),
                        privilege: 'system.clear_cache',
                    },
                    {
                        id: utils.createId(),
                        title: this.$tc('sw-shortcut-overview.functionSpecialShortcutClearCacheLinux'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutSpecialShortcutClearCacheLinux'),
                        privilege: 'system.clear_cache',
                    },
                ],
            };
        },
    },

    methods: {
        onOpenShortcutOverviewModal() {
            this.showShortcutOverviewModal = true;
        },

        onCloseShortcutOverviewModal() {
            this.showShortcutOverviewModal = false;
        },
    },
});
