import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-search-preferences-modal',
    load: () => import('./src/sw-search-preferences-modal'),
    public: {
        emits: [
            'modal-close',
        ],
        blocks: [
            'sw_search_preferences_modal',
            'sw_search_preferences_modal_description',
            'sw_search_preferences_modal_grid',
            'sw_search_preferences_modal_grid_column_active',
            'sw_search_preferences_modal_grid_column_module_name',
            'sw_search_preferences_modal_button_cancel',
            'sw_search_preferences_modal_button_save',
        ],
        computed: [
            'defaultSearchPreferences',
            'searchPreferencesColumns',
        ],
        methods: [
            'createdComponent',
            'mountedComponent',
            'beforeDestroyComponent',
            'getDataSource',
            'addEventListeners',
            'removeEventListeners',
            'getModuleName',
            'onChangeSearchPreference',
            'onClose',
            'onOpenSearchSettings',
            'onCancel',
            'onSave',
        ],
    },
});
