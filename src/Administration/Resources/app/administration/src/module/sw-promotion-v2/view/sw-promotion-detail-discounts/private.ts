import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-promotion-detail-discounts',
    load: () => import('./src/sw-promotion-detail-discounts'),
    public: {
        blocks: [
            'sw_promotion_detail_discounts',
            'sw_promotion_detail_discounts_card_add',
            'sw_promotion_detail_discounts_actions_add',
            'sw_promotion_detail_discounts_items',
            'sw_promotion_detail_discounts_item_form',
        ],
        computed: [
            'promotion',
            'isLoading',
            'discounts',
        ],
        methods: [
            'onAddDiscount',
            'deleteDiscount',
        ],
    },
});
