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
    name: 'sw-cms-mapping-field',
    load: () => import('./src/sw-cms-mapping-field'),
    public: {
        props: [
            'config',
            'valueTypes',
            'entity',
            'label',
            'disabled',
        ],
        slots: [
            'default',
            'preview',
        ],
        blocks: [
            'sw_cms_mapping_field',
            'sw_cms_mapping_field_info',
            'sw_cms_mapping_field_label',
            'sw_cms_mapping_field_mapping',
            'sw_cms_mapping_field_mapping_selection',
            'sw_cms_mapping_field_mapping_selection_action',
            'sw_cms_mapping_field_mapping_selection_options',
            'sw_cms_mapping_field_mapping_remove_action',
            'sw_cms_mapping_field_content',
            'sw_cms_mapping_field_mapping_value',
            'sw_cms_mapping_field_preview',
        ],
        computed: [
            'isMapped',
            'hasPreview',
            'cmsPageState',
        ],
        methods: [
            'createdComponent',
            'updateMappingTypes',
            'updateDemoValue',
            'onMappingSelect',
            'onMappingRemove',
            'getAllowedMappingTypes',
            'getDemoValue',
        ],
    },
});
