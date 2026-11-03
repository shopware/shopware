import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-plugin-box',
    load: () => import('./src/sw-plugin-box'),
    public: {
        props: [
            'pluginId',
        ],
        blocks: [
            'sw_plugin_box',
            'sw_plugin_box_container',
            'sw_plugin_box_container_plugin_data',
            'sw_plugin_box_container_plugin_data_icon',
            'sw_plugin_box_container_plugin_data_description',
            'sw_plugin_box_container_plugin_data_description_label',
            'sw_plugin_box_container_plugin_data_description_author',
            'sw_plugin_box_container_button',
        ],
        computed: [
            'pluginRepository',
        ],
        methods: [
            'createdComponent',
            'checkPluginConfig',
        ],
    },
});
