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
    name: 'sw-popover',
    load: () => import('./src/sw-popover'),
    public: {
        props: [
            'isOpened',
            'resizeWidth',
        ],
        slots: [
            'name',
            'default',
        ],
        blocks: [
            'sw_popover',
            'sw_popover_deprecated',
        ],
        computed: [
            'useMeteorComponent',
            'computedMatchReferenceWidth',
        ],
        methods: [
            'getSlots',
        ],
    },
});
