import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-flow-app-action-modal',
    load: () => import('./src/sw-flow-app-action-modal'),
    public: {
        props: [
            'sequence',
        ],
        emits: [
            'process-finish',
            'modal-close',
        ],
        blocks: [
            'sw_flow_app_action_modal',
            'sw_flow_app_action_modal_header',
            'sw_flow_app_action_modal_content',
            'sw_flow_app_action_modal_headcontent',
            'sw_flow_app_action_modal_headline',
            'sw_flow_app_action_modal_paragraph',
            'sw_flow_app_action_modal_footer_cancel_button',
            'sw_flow_app_action_modal_footer_save_button',
        ],
        computed: [
            'actionLabel',
            'appBadge',
            'currentLocale',
            'headline',
            'paragraph',
        ],
        methods: [
            'createdComponent',
            'onChange',
            'isValid',
            'handleValid',
            'onSave',
            'buildConfig',
            'onClose',
            'getFields',
            'convertDefaultValue',
            'getConfig',
            'helpText',
        ],
    },
});
