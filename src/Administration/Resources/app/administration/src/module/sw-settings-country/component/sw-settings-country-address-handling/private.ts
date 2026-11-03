import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@discovery
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
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
