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
    name: 'sw-settings-state-machine-state-detail',
    load: () => import('./src/sw-settings-state-machine-state-detail'),
    public: {
        props: [
            'currentStateMachineState',
        ],
        emits: [
            'modal-close',
        ],
        blocks: [
            'sw_settings_state_machine_state_detail',
            'sw_settings_state_machine_state_detail_content',
            'sw_settings_state_machine_state_detail_content_name',
            'sw_settings_state_machine_state_detail_content_technical_name',
            'sw_settings_state_machine_state_detail_footer',
            'sw_settings_state_machine_state_detail_footer_cancel',
            'sw_settings_state_machine_state_detail_footer_save',
        ],
        computed: [
            'stateMachineStateRepository',
        ],
        methods: [
            'createdComponent',
            'onCancel',
            'onSave',
        ],
    },
});
