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
    name: 'sw-text-editor-link-menu',
    load: () => import('./src/sw-text-editor-link-menu'),
    public: {
        props: [
            'buttonConfig',
        ],
        emits: [
            'mounted',
            'button-click',
        ],
        blocks: [
            'sw_text_editor_toolbar_button_link_menu',
            'sw_text_editor_toolbar_button_link_menu_content',
            'sw_text_editor_toolbar_button_link_menu_content_type',
            'sw_text_editor_toolbar_button_link_menu_content_type_inputs',
            'sw_text_editor_toolbar_button_link_menu_content_type_inputs_link',
            'sw_text_editor_toolbar_button_link_menu_content_type_inputs_phone',
            'sw_text_editor_toolbar_button_link_menu_content_type_inputs_email',
            'sw_text_editor_toolbar_button_link_menu_content_type_inputs_detail',
            'sw_text_editor_toolbar_button_link_menu_content_type_inputs_category',
            'sw_text_editor_toolbar_button_link_menu_content_type_inputs_media',
            'sw_text_editor_toolbar_button_link_menu_content_type_opens_new_tab',
            'sw_text_editor_toolbar_button_link_menu_content_type_display_as_button',
            'sw_text_editor_toolbar_button_link_menu_content_type_button_type',
            'sw_text_editor_toolbar_button_link_menu_buttons',
            'sw_text_editor_toolbar_button_link_menu_button_remove',
            'sw_text_editor_toolbar_button_link_menu_button_insert',
        ],
        computed: [
            'seoUrlReplacePrefix',
            'entityFilter',
            'categoryRepository',
            'linkCategoryOptions',
        ],
        methods: [
            'createdComponent',
            'mountedComponent',
            'getCategoryCollection',
            'getEmptyCategoryCollection',
            'parseLink',
            'replaceCategorySelection',
            'removeCategorySelection',
            'prepareLink',
            'addProtocolToLink',
            'setLink',
            'removeLink',
            'onSelectFieldChange',
        ],
    },
});
