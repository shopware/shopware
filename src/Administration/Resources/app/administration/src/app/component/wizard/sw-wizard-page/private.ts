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
    name: 'sw-wizard-page',
    load: () => import('./src/sw-wizard-page'),
    public: {
        props: [
            'isActive',
            'title',
            'position',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_wizard_page',
            'sw_wizard_page_slot_default',
        ],
        methods: [
            'createdComponent',
            'destroyedComponent',
        ],
    },
});
