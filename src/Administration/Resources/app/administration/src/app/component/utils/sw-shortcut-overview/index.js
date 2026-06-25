/**
 * @sw-package framework
 */

import template from './sw-shortcut-overview.html.twig';
import './sw-shortcut-overview.scss';

const utils = Shopware.Utils;

/**
 * @private
 */
export default {
    template,

    emits: [
        'shortcut-open',
        'shortcut-close',
    ],

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
                        title: this.$t('sw-shortcut-overview.functionAddProduct'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutAddProduct'),
                        privilege: 'product.creator',
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionAddCategory'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutAddCategory'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionAddCustomer'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutAddCustomer'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionAddProperties'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutAddProperties'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionAddManufacturer'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutAddManufacturer'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionAddRule'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutAddRule'),
                    },
                ],
                navigation: [
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionGoToDashboard'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutGoToDashboard'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionGoToProducts'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutGoToProducts'),
                        privilege: 'product.viewer',
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionGoToCategories'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutGoToCategories'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionGoToDynamicProductGroups'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutGoToDynamicProductGroups'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionGoToProperties'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutGoToProperties'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionGoToManufacturers'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutGoToManufacturers'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionGoToOrders'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutGoToOrders'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionGoToCustomers'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutGoToCustomers'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionGoToShoppingExperience'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutGoToShoppingExperience'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionGoToMedia'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutGoToMedia'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionGoToPromotion'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutGoToPromotion'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionGoToNewsletterRecipients'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutGoToNewsletterRecipients'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionGoToSettingsListing'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutGoToSettingsListing'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionGoToSnippets'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutGoToSnippets'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionGoToPayment'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutGoToPayment'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionGoToShipping'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutGoToShipping'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionGoToRuleBuilder'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutGoToRuleBuilder'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionGoToPlugins'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutGoToPlugins'),
                        privilege: 'system.plugin_maintain',
                    },
                ],

                specialShortcuts: [
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionSpecialShortcutFocusSearch'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutSpecialShortcutFocusSearch'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionSpecialShortcutShortcutListing'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutSpecialShortcutShortcutListing'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionSpecialShortcutSaveDetailViewWindows'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutSpecialShortcutSaveDetailViewWindows'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionSpecialShortcutSaveDetailViewMac'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutSpecialShortcutSaveDetailViewMac'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionSpecialShortcutSaveDetailViewLinux'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutSpecialShortcutSaveDetailViewLinux'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionSpecialShortcutCancelDetailView'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutSpecialShortcutCancelDetailView'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionSpecialShortcutClearCacheWindows'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutSpecialShortcutClearCacheWindows'),
                        privilege: 'system.clear_cache',
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionSpecialShortcutClearCacheMac'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutSpecialShortcutClearCacheMac'),
                        privilege: 'system.clear_cache',
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionSpecialShortcutClearCacheLinux'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutSpecialShortcutClearCacheLinux'),
                        privilege: 'system.clear_cache',
                    },
                ],
            };
        },
    },

    methods: {
        onOpenShortcutOverviewModal() {
            this.showShortcutOverviewModal = true;
            this.$emit('shortcut-open');
        },

        onCloseShortcutOverviewModal() {
            this.showShortcutOverviewModal = false;
            this.$emit('shortcut-close');
        },
    },
};
