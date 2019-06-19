import template from './sw-shortcut-overview.html.twig';
import './sw-shortcut-overview.scss';

export default {
    name: 'sw-shortcut-overview',
    template,

    shortcuts: {
        '?': 'onOpenShortcutOverviewModal'
    },

    data() {
        return {
            showShortcutOverviewModal: false,
            sections: {
                addingItems: [
                    {
                        title: this.$tc('sw-shortcut-overview.functionAddProduct'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutAddProduct')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionAddCategory'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutAddCategory')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionAddShoppingExperienceLayout'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutAddShoppingExperienceLayout')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionAddCustomer'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutAddCustomer')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionAddProperties'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutAddProperties')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionAddManufacturer'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutAddManufacturer')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionAddRule'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutAddRule')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionAddSaleschannel'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutAddSaleschannel')
                    }
                ],
                navigation: [
                    {
                        title: this.$tc('sw-shortcut-overview.functionGoToDashboard'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToDashboard')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionGoToProducts'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToProducts')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionGoToCategories'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToCategories')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionGoToDynamicProductGroups'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToDynamicProductGroups')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionGoToProperties'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToProperties')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionGoToManufacturers'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToManufacturers')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionGoToOrders'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToOrders')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionGoToCustomers'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToCustomers')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionGoToShoppingExperience'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToShoppingExperience')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionGoToMedia'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToMedia')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionGoToPromotion'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToPromotion')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionGoToNewsletterRecipients'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToNewsletterRecipients')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionGoToSettingsListing'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToSettingsListing')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionGoToSnippets'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToSnippets')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionGoToPayment'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToPayment')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionGoToShipping'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToShipping')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionGoToRuleBuilder'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToRuleBuilder')
                    },
                    {
                        title: this.$tc('sw-shortcut-overview.functionGoToPlugins'),
                        content: this.$tc('sw-shortcut-overview.keyboardShortcutGoToPlugins')
                    }

                ]
            }
        };
    },

    computed: {
        tooltipOpenModal() {
            return {
                message: this.$tc('sw-shortcut-overview.iconTooltip', 0, { key: '?' }),
                appearance: 'light'
            };
        }
    },

    methods: {
        onOpenShortcutOverviewModal() {
            this.showShortcutOverviewModal = true;
        },

        onCloseShortcutOverviewModal() {
            this.showShortcutOverviewModal = false;
        }
    }
};
