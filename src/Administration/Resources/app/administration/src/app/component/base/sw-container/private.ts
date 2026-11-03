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
    name: 'sw-container',
    load: () => import('./src/sw-container'),
    public: {
        props: [
            'columns',
            'rows',
            'gap',
            'justify',
            'align',
            'breakpoints',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_container',
            'sw_container_slot_default',
        ],
        methods: [
            'createdComponent',
            'registerResizeListener',
            'updateCssGrid',
            'buildCssGrid',
            'cssGridDefaults',
            'buildCssGridProps',
        ],
    },
});
