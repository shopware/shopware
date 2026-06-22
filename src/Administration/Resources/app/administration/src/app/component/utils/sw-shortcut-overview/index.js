/**
 * @sw-package framework
 */

import { classifyPlatform, PLATFORM } from 'src/core/helper/shortcut-key.helper';
import template from './sw-shortcut-overview.html.twig';
import './sw-shortcut-overview.scss';

const utils = Shopware.Utils;

const PLATFORM_NAMES = {
    [PLATFORM.MAC]: 'Mac',
    [PLATFORM.WINDOWS]: 'Windows',
    [PLATFORM.LINUX]: 'Linux',
};

/**
 * @private
 */
export default {
    template,

    inject: ['shortcutService'],

    emits: [
        'shortcut-open',
        'shortcut-close',
    ],

    props: {
        showModal: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    shortcuts: {
        '?': 'onOpenShortcutOverviewModal',
    },

    data() {
        return {
            showShortcutOverviewModal: this.showModal,
            shortcutsDisabled: false,
        };
    },

    created() {
        this.createdComponent();
    },

    watch: {
        showModal(value) {
            this.showShortcutOverviewModal = value;
        },
    },

    computed: {
        platform() {
            const userPlatform = this.$device?.getPlatform?.() ?? window.navigator.platform;

            return classifyPlatform(userPlatform);
        },

        platformShortcutSuffix() {
            return PLATFORM_NAMES[this.platform];
        },

        sections() {
            return {
                generalShortcuts: [
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionSpecialShortcutShortcutListing'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutSpecialShortcutShortcutListing'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionSpecialShortcutFocusSearch'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutSpecialShortcutFocusSearch'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionSpecialShortcutOpenFilters'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutSpecialShortcutOpenFilters'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionAccessibilityCloseDialog'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutAccessibilityCloseDialog'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionSpecialShortcutSaveDetailView'),
                        content: this.$t(
                            'sw-shortcut-overview.keyboardShortcutSpecialShortcutSaveDetailView' +
                                this.platformShortcutSuffix,
                        ),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionSpecialShortcutClearCache'),
                        content: this.$t(
                            'sw-shortcut-overview.keyboardShortcutSpecialShortcutClearCache' + this.platformShortcutSuffix,
                        ),
                        privilege: 'system.clear_cache',
                    },
                ],
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
                accessibility: [
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionAccessibilitySkipToContent'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutAccessibilitySkipToContent'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionAccessibilityMoveFocusForward'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutAccessibilityMoveFocusForward'),
                    },
                    {
                        id: utils.createId(),
                        title: this.$t('sw-shortcut-overview.functionAccessibilityMoveFocusBackward'),
                        content: this.$t('sw-shortcut-overview.keyboardShortcutAccessibilityMoveFocusBackward'),
                    },
                ],
            };
        },
    },

    methods: {
        createdComponent() {
            this.shortcutsDisabled = this.shortcutService.isShortcutsDisabled();
        },

        onOpenShortcutOverviewModal() {
            this.showShortcutOverviewModal = true;
            this.$emit('shortcut-open');
        },

        onCloseShortcutOverviewModal() {
            this.showShortcutOverviewModal = false;
            this.$emit('shortcut-close');
        },

        onToggleShortcutsDisabled(shortcutsDisabled) {
            this.shortcutsDisabled = shortcutsDisabled;
            this.shortcutService.setShortcutsDisabled(shortcutsDisabled);
        },
    },
};
