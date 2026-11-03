import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-search-example-modal',
    load: () => import('./src/sw-settings-search-example-modal'),
    public: {
        emits: [
            'modal-close',
        ],
        blocks: [
            'sw_settings_search_searchable_content_example_modal',
            'sw_settings_search_searchable_content_example_modal_content',
            'sw_settings_search_searchable_content_example_modal_explain',
            'sw_settings_search_searchable_content_example_modal_result',
            'sw_settings_search_searchable_content_example_modal_footer',
            'sw_settings_search_searchable_content_example_modal_close',
        ],
        methods: [
            'closeModal',
        ],
    },
});
