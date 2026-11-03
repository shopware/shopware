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
    name: 'sw-alert-deprecated',
    load: () => import('./src/sw-alert-deprecated'),
    public: {
        props: [
            'variant',
            'appearance',
            'title',
            'showIcon',
            'closable',
            'notificationIndex',
            'icon',
        ],
        emits: [
            'close',
        ],
        slots: [
            'customIcon',
            'default',
            'actions',
        ],
        blocks: [
            'sw_alert',
            'sw_alert_inner',
            'sw_alert_icon',
            'sw_alert_body',
            'sw_alert_title',
            'sw_alert_message',
            'sw_alert_slot_default',
            'sw_alert_actions',
            'sw_alert_close',
        ],
        computed: [
            'alertIcon',
            'hasActionSlot',
            'alertClasses',
            'alertBodyClasses',
        ],
    },
});
