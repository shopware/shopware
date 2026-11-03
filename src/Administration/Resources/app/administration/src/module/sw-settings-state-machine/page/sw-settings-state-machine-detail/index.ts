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
    name: 'sw-settings-state-machine-detail',
    load: () => import('./src/sw-settings-state-machine-detail'),
    public: {
        props: [
            'stateMachineId',
        ],
        blocks: [
            'sw_settings_state_machine_detail',
            'sw_settings_state_machine_detail_smart_bar_header',
            'sw_settings_state_machine_detail_language_switch',
            'sw_settings_state_machine_detail_smart_bar_actions',
            'sw_settings_state_machine_detail_smart_bar_actions_cancel',
            'sw_settings_state_machine_detail_smart_bar_actions_save',
            'sw_settings_state_machine_detail_content',
            'sw_settings_state_machine_detail_content_card',
            'sw_settings_state_machine_detail_content_card_name',
            'sw_settings_state_machine_detail_content_card_technical_name',
            'sw_settings_state_machine_detail_content_list',
        ],
        computed: [
            'identifier',
            'stateMachineRepository',
            'allowSave',
            'tooltipSave',
            'tooltipCancel',
        ],
        methods: [
            'createdComponent',
            'loadStateMachine',
            'onChangeLanguage',
            'onCancel',
            'onSave',
        ],
    },
});
