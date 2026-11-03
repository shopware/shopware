import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-country-address-handling',
    load: () => import('./src/sw-settings-country-address-handling'),
    public: {
        props: [
            'country',
            'isLoading',
        ],
        emits: [
            'update:country',
        ],
        blocks: [
            'sw_settings_country_address_handling',
            'sw_settings_country_address_handling_options',
            'sw_settings_country_address_handling_formatting',
        ],
        computed: [
            'customerCriteria',
            'dragConf',
            'addressFormat',
            'addressFormatRows',
            'hasDefaultPostalCodePattern',
            'disabledAdvancedPostalCodePattern',
            'isDisplayStateInRegistrationActive',
            'isDisplayStateInRegistrationDisabled',
        ],
        methods: [
            'createdComponent',
            'onDragStart',
            'onSnippetDragStart',
            'onDragEnter',
            'onDrop',
            'getRowTargetIndex',
            'getRowDropPosition',
            'getRowDropIndex',
            'getRowKey',
            'onDropEnd',
            'onSnippetDragEnter',
            'moveToNewPosition',
            'addNewLineAt',
            'swapPosition',
            'change',
            'customerLabel',
            'onChangeCustomer',
            'resetMarkup',
            'openSnippetModal',
            'onCloseModal',
            'getSnippets',
            'renderFormattingAddress',
            'getLabelProperty',
            'updateCountry',
        ],
    },
});
