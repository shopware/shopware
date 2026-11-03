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
    name: 'sw-card-deprecated',
    load: () => import('./src/sw-card-deprecated'),
    public: {
        props: [
            'positionIdentifier',
            'title',
            'subtitle',
            'hero',
            'isLoading',
            'large',
            'aiBadge',
            'contentPadding',
        ],
        slots: [
            'avatar',
            'title',
            'subtitle',
            'header-right',
            'tabs',
            'toolbar',
            'context-actions',
            'default',
            'grid',
            'footer',
        ],
        blocks: [
            'sw_card',
            'sw_card_title',
            'sw_card_subtitle',
            'sw_card_toolbar',
            'sw_card_toolbar_inner',
            'sw_card_slot_toolbar',
            'sw_card_content',
            'sw_card_slot_context_menu_actions',
            'sw_card_slot_default',
            'sw_card_slot_grid',
            'sw_card_slot_footer',
            'sw_card_loader',
        ],
        computed: [
            'showHeader',
            'hasAvatar',
            'cardContentClasses',
            'contextSlot',
        ],
        methods: [
            'cardClasses',
        ],
    },
});
