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
    name: 'sw-order-promotion-field',
    load: () => import('./src/sw-order-promotion-field'),
    public: {
        props: [
            'isLoading',
        ],
        emits: [
            'error',
            'loading-change',
            'reload-entity-data',
            'save-and-reload',
            'save-and-recalculate',
        ],
        blocks: [
            'sw_order_promotion_field',
            'sw_order_promotion_field_codes',
            'sw_order_promotion_field_switch',
            'sw_order_promotion_field_updates_modal',
        ],
        computed: [
            'order',
            'isOrderLoading',
            'versionContext',
            'orderLineItemRepository',
            'hasLineItem',
            'currency',
            'manualPromotions',
            'automaticPromotions',
            'promotionCodeTags',
            'hasAutomaticPromotions',
            'changesetGenerator',
            'hasOrderUnsavedChanges',
            'promotionsRemoved',
            'promotionsAdded',
        ],
        methods: [
            'createdComponent',
            'emitEntityData',
            'emitLoadingChange',
            'saveAndReload',
            'saveAndRecalculate',
            'handleUnsavedOrderChangesResponse',
            'handleError',
            'deleteAutomaticPromotions',
            'toggleAutomaticPromotions',
            'applyAutomaticPromotions',
            'onSubmitCode',
            'handlePromotionResponse',
            'onRemoveExistingCode',
            'dismissPromotionUpdates',
            'getLineItemByPromotionCode',
        ],
    },
});
