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
    name: 'sw-settings-snippet-filter-switch',
    load: () => import('./src/sw-settings-snippet-filter-switch'),
    public: {
        props: [
            'label',
            'name',
            'group',
            'borderTop',
            'borderBottom',
            'type',
            'value',
        ],
        emits: [
            'update:value',
        ],
        slots: [
            'template',
            'field',
        ],
        blocks: [
            'sw_settings_snippet_filter_switch',
            'sw_settings_snippet_filter_switch_field',
        ],
        computed: [
            'fieldClasses',
        ],
        methods: [
            'onChange',
        ],
    },
});
