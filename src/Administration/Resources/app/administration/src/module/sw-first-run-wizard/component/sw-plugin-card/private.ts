import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-plugin-card',
    load: () => import('./src/sw-plugin-card'),
    public: {
        props: [
            'plugin',
            'showDescription',
        ],
        emits: [
            'extension-activated',
            'on-plugin-installed',
        ],
        blocks: [
            'sw_plugin_card',
        ],
        computed: [
            'pluginIsNotActive',
            'truncateFilter',
        ],
        methods: [
            'onInstall',
            'setupPlugin',
        ],
    },
});
