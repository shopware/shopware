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
    name: 'sw-custom-field-translated-labels',
    load: () => import('./src/sw-custom-field-translated-labels'),
    public: {
        props: [
            'locales',
            'config',
            'propertyNames',
            'disabled',
        ],
        blocks: [
            'sw_custom_field_translated_labels',
            'sw_custom_field_translated_labels_single',
            'sw_custom_field_translated_labels_translated',
            'sw_custom_field_translated_labels_translated_mt_tabs',
            'sw_custom_field_translated_labels_translated_mt_content',
            'sw_custom_field_translated_labels_translated_mt_content_field',
            'sw_custom_field_translated_labels_translated_tabs',
            'sw_custom_field_translated_labels_translated_content',
            'sw_custom_field_translated_labels_translated_content_field',
        ],
        computed: [
            'fallbackLocale',
            'localeCount',
            'activeLocale',
            'translatedLabelTabs',
        ],
        methods: [
            'createdComponent',
            'initializeConfiguration',
            'getLabel',
            'onInput',
        ],
    },
});
