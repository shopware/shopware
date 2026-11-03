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
    name: 'sw-step-display',
    load: () => import('./src/sw-step-display'),
    public: {
        props: [
            'itemIndex',
            'itemVariant',
            'initialItemVariants',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_step_display',
            'sw_step_display_slot',
        ],
        methods: [
            'addStep',
            'setItemVariants',
            'setItemVariant',
            'setVariantForCurrentItem',
            'setItemActive',
        ],
    },
});
