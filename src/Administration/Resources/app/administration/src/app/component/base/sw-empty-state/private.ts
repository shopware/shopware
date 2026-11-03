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
    name: 'sw-empty-state',
    load: () => import('./src/sw-empty-state'),
    public: {
        props: [
            'title',
            'subline',
            'showDescription',
            'color',
            'icon',
            'absolute',
            'emptyModule',
            'autoHeight',
        ],
        slots: [
            'icon',
            'default',
            'actions',
        ],
        blocks: [
            'sw_empty_state',
            'sw_empty_state_element',
            'sw_empty_state_icon',
            'sw_empty_state_slot_icon',
            'sw_empty_state_title',
            'sw_empty_state_description',
            'sw_empty_state_slot_default',
            'sw_empty_state_actions',
            'sw_empty_state_slot_actions',
        ],
        computed: [
            'moduleColor',
            'moduleDescription',
            'moduleIcon',
            'hasActionSlot',
            'classes',
        ],
    },
});
