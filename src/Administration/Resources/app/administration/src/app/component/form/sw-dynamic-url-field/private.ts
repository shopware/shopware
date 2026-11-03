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
    name: 'sw-dynamic-url-field',
    load: () => import('./src/sw-dynamic-url-field'),
    public: {
        props: [
            'value',
            'disabled',
        ],
        emits: [
            'update:value',
        ],
        blocks: [
            'sw_dynamic_url_field',
            'sw_dynamic_url_field_content',
            'sw_dynamic_url_field_content_type',
            'sw_dynamic_url_field_content_type_inputs',
            'sw_dynamic_url_field_content_type_inputs_link',
            'sw_dynamic_url_field_content_type_inputs_phone',
            'sw_dynamic_url_field_content_type_inputs_email',
            'sw_dynamic_url_field_content_type_inputs_detail',
            'sw_dynamic_url_field_content_type_inputs_category',
            'sw_dynamic_url_field_content_type_inputs_media',
            'sw_dynamic_url_field_button_remove',
        ],
        computed: [
            'seoUrlReplacePrefix',
            'entityFilter',
            'categoryRepository',
            'linkCategoryOptions',
        ],
        methods: [
            'createdComponent',
            'getEmptyCategoryCollection',
            'getCategoryCollection',
            'parseLink',
            'replaceCategorySelection',
            'removeCategorySelection',
            'prepareLink',
            'removeLink',
            'onSelectFieldChange',
        ],
    },
});
