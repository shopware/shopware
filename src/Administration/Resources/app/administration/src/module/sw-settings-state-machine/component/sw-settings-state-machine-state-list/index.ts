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
    name: 'sw-settings-state-machine-state-list',
    load: () => import('./src/sw-settings-state-machine-state-list'),
    public: {
        props: [
            'stateMachineId',
        ],
        blocks: [
            'sw_settings_state_machine_state_list',
            'sw_settings_state_machine_state_list_grid',
            'sw_settings_state_machine_state_list_grid_actions',
            'sw_settings_state_machine_state_list_grid_actions_edit',
            'sw_settings_state_machine_state_list_detail',
        ],
        computed: [
            'stateMachineStateRepository',
            'stateMachineStateCriteria',
            'stateMachineStateColumns',
        ],
        methods: [
            'createdComponent',
            'loadStateMachineStates',
            'onInlineEditCancel',
            'onInlineEditSave',
            'showModal',
            'onModalClose',
        ],
    },
});
