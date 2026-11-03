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
    name: 'sw-switch-field',
    load: () => import('./src/sw-switch-field'),
    public: {
        props: [
            'value',
            'checked',
            'deprecated',
        ],
        emits: [
            'update:value',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_switch_field',
            'sw_switch_field_deprecated',
        ],
        computed: [
            'checkedValue',
        ],
        methods: [
            'onChangeHandler',
        ],
    },
});
