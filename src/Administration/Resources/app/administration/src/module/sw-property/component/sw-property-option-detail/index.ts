import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-property-option-detail',
    load: () => import('./src/sw-property-option-detail'),
    public: {
        props: [
            'currentOption',
            'allowEdit',
        ],
        emits: [
            'cancel-option-edit',
            'save-option-edit',
        ],
        blocks: [
            'sw_property_option_detail',
            'sw_property_option_detail_name',
            'sw_property_option_detail_position',
            'sw_property_option_detail_color',
            'sw_property_option_detail_media',
            'sw_property_option_detail_footer',
            'sw_property_option_detail_footer_cancel',
            'sw_property_option_detail_footer_save',
        ],
        computed: [
            'mediaRepository',
            'colorHexCode',
            'modalTitle',
            'showCustomFields',
        ],
        methods: [
            'createdComponent',
            'loadCustomFieldSets',
            'onCancel',
            'onSave',
            'successfulUpload',
            'removeMedia',
            'setMedia',
        ],
    },
});
