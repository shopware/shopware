import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-first-run-wizard-modal',
    load: () => import('./src/sw-first-run-wizard-modal'),
    public: {
        blocks: [
            'sw_first_run_wizard_modal',
            'sw_first_run_wizard_modal_content',
            'sw_first_run_wizard_modal_content_steps',
            'sw_first_run_wizard_modal_content_page',
            'sw_first_run_wizard_modal_footer',
            'sw_first_run_wizard_modal_footer_left',
            'sw_first_run_wizard_modal_footer_right',
        ],
        computed: [
            'columns',
            'variant',
            'showSteps',
            'buttons',
            'stepIndex',
            'stepInitialItemVariants',
            'extensionManagementDisabled',
            'isClosable',
            'stepper',
        ],
        methods: [
            '_reloadPage',
            'handleRouteUpdate',
            'createdComponent',
            'updateButtons',
            'onButtonClick',
            'redirect',
            'setTitle',
            'finishFRW',
            'onExtensionActivated',
            'closeModal',
        ],
    },
});
