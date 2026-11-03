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
