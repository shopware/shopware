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
    name: 'sw-settings-country-new-snippet-modal',
    load: () => import('./src/sw-settings-country-new-snippet-modal'),
    public: {
        props: [
            'selections',
            'currentPosition',
            'addressFormat',
            'disabled',
            'getLabelProperty',
        ],
        emits: [
            'modal-close',
            'change',
        ],
        slots: [
            'selected-option',
            'label-property',
        ],
        blocks: [
            'sw_settings_country_new_snippet_modal',
        ],
        computed: [
            'selection',
        ],
        methods: [
            'createdComponent',
            'onCloseModal',
            'addElement',
            'debouncedSearch',
            'search',
            'getSnippetsTree',
            'onClickDismiss',
        ],
    },
});
