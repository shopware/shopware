import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-user-card',
    load: () => import('./src/sw-user-card'),
    public: {
        props: [
            'user',
            'title',
            'isLoading',
        ],
        slots: [
            'metadata-additional',
            'actions',
            'summary',
            'data-additional',
            'default',
        ],
        blocks: [
            'sw_user_card',
            'sw_user_card_rows',
            'sw_user_card_row_primary',
            'sw_user_card_metadata_container',
            'sw_user_card_avatar',
            'sw_user_card_metadata',
            'sw_user_card_metadata_user_name',
            'sw_user_card_metadata_user_company',
            'sw_user_card_metadata_user_email',
            'sw_user_card_metadata_additional',
            'sw_user_card_slot_metadata_additional',
            'sw_user_card_actions',
            'sw_user_card_slot_actions',
            'sw_user_card_slot_summary',
            'sw_user_card_slot_data_additional',
            'sw_user_card_row_secondary',
            'sw_user_card_slot_default',
        ],
        computed: [
            'hasActionSlot',
            'hasAdditionalDataSlot',
            'hasSummarySlot',
            'moduleColor',
            'salutationFilter',
        ],
    },
});
