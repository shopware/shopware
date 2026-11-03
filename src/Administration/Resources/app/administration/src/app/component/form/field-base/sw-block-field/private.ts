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
    name: 'sw-block-field',
    load: () => import('./src/sw-block-field'),
    public: {
        props: [
            'size',
        ],
        slots: [
            'sw-field-input',
            'hint',
            'label',
        ],
        blocks: [
            'sw_block_field',
        ],
        computed: [
            'swBlockSize',
            'swBlockFieldClasses',
        ],
        methods: [
            'setFocusClass',
            'removeFocusClass',
        ],
    },
});
