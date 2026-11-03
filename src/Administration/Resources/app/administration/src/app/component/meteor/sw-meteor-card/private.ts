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
    name: 'sw-meteor-card',
    load: () => import('./src/sw-meteor-card'),
    public: {
        props: [
            'title',
            'hero',
            'isLoading',
            'large',
            'defaultTab',
        ],
        slots: [
            'title',
            'action',
            'toolbar',
            'tabs',
            'default',
            'grid',
            'footer',
        ],
        blocks: [
            'sw_meteor_card',
            'sw_meteor_card_header',
            'sw_meteor_card_header_grid',
            'sw_meteor_card_title',
            'sw_meteor_card_header_action',
            'sw_meteor_card_header_action_inner',
            'sw_meteor_card_slot_header_action',
            'sw_meteor_card_toolbar',
            'sw_meteor_card_toolbar_inner',
            'sw_meteor_card_slot_toolbar',
            'sw_meteor_card_tabs',
            'sw_meteor_card_mt_tabs',
            'sw_meteor_card_content',
            'sw_meteor_card_content_wrapper',
            'sw_meteor_card_default_inner',
            'sw_meteor_card_slot_default',
            'sw_meteor_card_slot_grid',
            'sw_meteor_card_loader',
            'sw_meteor_card_footer',
            'sw_meteor_card_footer_inner',
            'sw_meteor_card_slot_footer',
        ],
        computed: [
            'hasTabs',
            'hasToolbar',
            'hasContent',
            'hasDefaultSlot',
            'tabItems',
            'hasHeader',
            'isToolbarLastHeaderElement',
            'cardClasses',
        ],
        methods: [
            'createdComponent',
            'setActiveTab',
            'getTabItemsFromSlot',
            'createTabItem',
            'getTabItemDefaultSlotText',
            'isTabItem',
        ],
    },
});
