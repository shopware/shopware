import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-sales-channel-switch',
    load: () => import('./src/sw-sales-channel-switch'),
    public: {
        props: [
            'disabled',
            'abortChangeFunction',
            'saveChangesFunction',
            'label',
            'salesChannelCriteria',
        ],
        emits: [
            'change-sales-channel-id',
        ],
        blocks: [
            'sw_sales_channel_switch',
            'sw_sales_channel_switch_modal',
            'sw_sales_channel_switch_message',
            'sw_sales_channel_switch_footer',
            'sw_sales_channel_switch_footer_button_close',
            'sw_sales_channel_switch_footer_button_revert',
            'sw_sales_channel_switch_footer_button_save',
            'sw_sales_channel_switch_select',
        ],
        methods: [
            'onChange',
            'checkAbort',
            'emitChange',
            'onCloseChangesModal',
            'onClickSaveChanges',
            'onClickRevertUnsavedChanges',
            'changeToNewSalesChannel',
        ],
    },
});
