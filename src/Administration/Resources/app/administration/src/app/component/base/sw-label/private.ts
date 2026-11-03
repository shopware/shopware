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
    name: 'sw-label',
    load: () => import('./src/sw-label'),
    public: {
        props: [
            'variant',
            'size',
            'appearance',
            'ghost',
            'caps',
            'dismissable',
            'light',
            'onDismiss',
        ],
        emits: [
            'selected',
            'dismiss',
        ],
        slots: [
            'default',
            'dismiss-icon',
        ],
        blocks: [
            'sw_label',
            'sw_label_status_color_badge',
            'sw_label_text_holder',
            'sw_label_slot_default',
            'sw_label_dismiss',
            'sw_select_selection_dismiss_icon',
        ],
        computed: [
            'labelClasses',
            'showDismissable',
        ],
    },
});
