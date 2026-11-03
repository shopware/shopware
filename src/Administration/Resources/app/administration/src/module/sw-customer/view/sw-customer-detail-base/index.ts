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
    name: 'sw-customer-detail-base',
    load: () => import('./src/sw-customer-detail-base'),
    public: {
        props: [
            'customer',
            'customerEditMode',
            'isLoading',
        ],
        blocks: [
            'sw_customer_detail_base',
            'sw_customer_detail_base_info_holder',
            'sw_customer_detail_base_info_card',
            'sw_customer_detail_base_info_metadata',
            'sw_customer_detail_base_default_addresses_card',
            'sw_customer_detail_base_default_addresses',
            'sw_customer_detail_custom_field_sets',
        ],
        computed: [
            'customFieldSetRepository',
            'customFieldSetCriteria',
        ],
        methods: [
            'createdComponent',
        ],
    },
});
