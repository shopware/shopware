import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-sales-channel-defaults-select',
    load: () => import('./src/sw-sales-channel-defaults-select'),
    public: {
        props: [
            'salesChannel',
            'propertyName',
            'propertyLabel',
            'defaultPropertyName',
            'defaultPropertyLabel',
            'propertyNameInDomain',
            'helpText',
            'disabled',
            'criteria',
            'disabledTooltipMessage',
            'shouldShowActiveState',
        ],
        blocks: [
            'sw_sales_channel_defaults_select',
        ],
        computed: [
            'propertyCollection',
            'defaultId',
            'propertyEntityName',
            'propertyNameKebabCase',
            'multiSelectClass',
            'singleSelectClass',
            'defaultsValueError',
            'labelProperty',
            'showClearableButtonForDefault',
        ],
        methods: [
            'updateCollection',
            'getNotInCollection',
            'addItem',
            'removeItem',
            'getDomainUsingValue',
            'updateDefault',
            'isDisabledItem',
            'getActiveIconColor',
        ],
    },
});
