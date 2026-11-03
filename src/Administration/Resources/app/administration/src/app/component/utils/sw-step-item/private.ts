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
    name: 'sw-step-item',
    load: () => import('./src/sw-step-item'),
    public: {
        props: [
            'disabledIcon',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_step_item',
            'sw_step_item_bubble',
            'sw_step_item_description',
        ],
        computed: [
            'modifierClasses',
            'icon',
            'stepDisplay',
        ],
        methods: [
            'registerStep',
            'setActive',
            'setVariant',
        ],
    },
});
