import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-custom-field-type-base',
    load: () => import('./src/sw-custom-field-type-base'),
    public: {
        props: [
            'currentCustomField',
            'set',
            'disabled',
        ],
        blocks: [
            'sw_custom_field_type_base',
            'sw_custom_field_type_base_content',
            'sw_custom_field_type_base_labels',
        ],
        computed: [
            'locales',
        ],
    },
});
