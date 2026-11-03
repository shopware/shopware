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
    name: 'sw-settings-state-machine-list',
    load: () => import('./src/sw-settings-state-machine-list'),
    public: {
        blocks: [
            'sw_settings_state_machine_list',
            'sw_settings_state_machine_list_smart_bar_header',
            'sw_settings_state_machine_list_smart_bar_header_title',
            'sw_settings_state_machine_list_smart_bar_header_title_text',
            'sw_settings_state_machine_list_smart_bar_header_amount',
            'sw_settings_state_machine_list_language_switch',
            'sw_settings_state_machine_list_content',
            'sw_settings_state_machine_list_content_card',
            'sw_settings_state_machine_list_content_card_grid',
            'sw_settings_state_machine_list_content_card_grid_actions',
            'sw_settings_state_machine_list_content_card_grid_actions_edit',
        ],
        computed: [
            'stateMachineRepository',
            'stateMachineColumns',
        ],
        methods: [
            'createdComponent',
            'loadStateMachines',
            'onChangeLanguage',
            'onInlineEditCancel',
            'onInlineEditSave',
        ],
    },
});
