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
    name: 'sw-settings-country-state',
    load: () => import('./src/sw-settings-country-state'),
    public: {
        props: [
            'country',
            'isLoading',
            'countryStateRepository',
        ],
        blocks: [
            'sw_settings_country_state_list_card',
            'sw_attribute_list_toolbar_searchfield',
            'sw_settings_country_state_list_toolbar_delete',
            'sw_settings_country_state_list_toolbar_add',
            'sw_settings_country_state_list_listing',
            'sw_settings_country_state_list_listing_column_name',
            'sw_settings_country_state_list_listing_action_edit',
            'sw_settings_country_state_list_empty',
            'sw_settings_country_state_detail',
        ],
        computed: [
            'stateColumns',
            'countryStates',
        ],
        methods: [
            'mountedComponent',
            'getStateColumns',
            'countryStateSelectionChanged',
            'onSearchCountryState',
            'onDeleteCountryStates',
            'onAddCountryState',
            'onSaveCountryState',
            'onCancelCountryState',
            'onClickCountryState',
            'refreshCountryStateList',
            'getCountryStateName',
            'checkEmptyState',
        ],
    },
});
