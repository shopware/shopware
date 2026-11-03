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
    name: 'sw-sales-channel-config',
    load: () => import('./src/sw-sales-channel-config'),
    public: {
        props: [
            'domain',
            'value',
            'criteria',
        ],
        emits: [
            'update:value',
            'salesChannelChanged',
        ],
        slots: [
            'select',
            'content',
        ],
        blocks: [
            'sw_sales_channel_config',
            'sw_sales_channel_config_switch',
        ],
        computed: [
            'actualConfigData',
            'salesChannelRepository',
        ],
        methods: [
            'createdComponent',
            'readAll',
            'onInput',
            'save',
        ],
    },
});
