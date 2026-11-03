import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-customer-imitate-customer-modal',
    load: () => import('./src/sw-customer-imitate-customer-modal'),
    public: {
        props: [
            'customer',
        ],
        emits: [
            'modal-close',
        ],
        blocks: [
            'sw_customer_imitate_customer_modal',
            'sw_customer_imitate_customer_modal_alert',
            'sw_customer_imitate_customer_modal_description',
            'sw_customer_imitate_customer_modal_body',
            'sw_customer_imitate_customer_modal_sales_channel_domain_items',
            'sw_customer_imitate_customer_modal_footer',
            'sw_customer_imitate_customer_modal_footer_cancel',
        ],
        computed: [
            'modalTitle',
            'modalDescription',
            'salesChannelDomainRepository',
            'currentUser',
            'salesChannelDomainCriteria',
            'hasSalesChannelDomains',
        ],
        methods: [
            'createdComponent',
            'onSalesChannelDomainMenuItemClick',
            'onCancel',
            'fetchSalesChannelDomains',
            'fetchImitateCustomerTokens',
        ],
    },
});
