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
    name: 'sw-loader',
    load: () => import('./src/sw-loader'),
    public: {
        props: [
            'modelValue',
            'value',
        ],
        slots: [
            'name',
            'default',
        ],
        blocks: [
            'sw_loader',
            'sw_loader_deprecated',
        ],
        computed: [
            'useMeteorComponent',
        ],
        methods: [
            'getSlots',
        ],
    },
});
