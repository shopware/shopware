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
    name: 'sw-cms-slot',
    load: () => import('./src/sw-cms-slot'),
    public: {
        props: [
            'element',
            'active',
            'disabled',
        ],
        blocks: [
            'sw_cms_slot',
            'sw_cms_slot_content',
            'sw_cms_slot_content_component',
            'sw_cms_slot_content_preview_overlay',
            'sw_cms_slot_content_overlay',
            'sw_cms_slot_content_overlay_content',
            'sw_cms_slot_content_overlay_action_settings',
            'sw_cms_slot_content_overlay_action_swap',
            'sw_cms_slot_content_settings_modal',
            'sw_cms_slot_content_settings_modal_component',
            'sw_cms_slot_content_settings_modal_footer',
            'sw_cms_slot_content_settings_modal_action_confirm',
            'sw_cms_slot_content_element_modal',
            'sw_cms_slot_content_element_modal_selection',
            'sw_cms_slot_content_element_modal_selection_groups',
            'sw_cms_slot_content_element_modal_selection_element',
            'sw_cms_slot_content_element_modal_selection_element_component',
            'sw_cms_slot_content_element_modal_selection_element_overlay',
            'sw_cms_slot_content_element_modal_selection_element_overlay_favorite',
            'sw_cms_slot_content_element_modal_selection_element_label',
            'sw_cms_slot_content_element_modal_footer',
            'sw_cms_slot_content_element_modal_action_abort',
        ],
        computed: [
            'slotElementId',
            'cmsServiceState',
            'elementConfig',
            'elementModalTitle',
            'cmsElements',
            'groupedCmsElements',
            'componentClasses',
            'cmsSlotSettingsClasses',
            'tooltipDisabled',
            'modalVariant',
            'isElementLocked',
        ],
        methods: [
            'mountedComponent',
            'onSettingsButtonClick',
            'onCloseSettingsModal',
            'onElementButtonClick',
            'onCloseElementModal',
            'onSelectElement',
            'onToggleElementFavorite',
            'toggleHoverElement',
            'getFavoriteIconToggleState',
            'elementInElementGroup',
        ],
    },
});
