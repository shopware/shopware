import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module and what it inherits from sw-custom-field-type-base,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-custom-field-type-number',
    extends: 'sw-custom-field-type-base',
    load: () => import('./src/sw-custom-field-type-number'),
    public: {
        props: [
            'currentCustomField',
            'set',
            'disabled',
        ],
        blocks: [
            'sw_custom_field_type_base_content',
            'sw_custom_field_type_number_container',
            'sw_custom_field_type_number_container_numbertype',
            'sw_custom_field_type_number_container_step',
            'sw_custom_field_type_number_container_min',
            'sw_custom_field_type_number_container_max',
            'sw_custom_field_type_base',
            'sw_custom_field_type_base_labels',
        ],
        computed: [
            'isIntField',
            'locales',
        ],
        methods: [
            'createdComponent',
        ],
    },
});
