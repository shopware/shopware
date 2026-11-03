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
    name: 'sw-contextual-field',
    load: () => import('./src/sw-contextual-field'),
    public: {
        slots: [
            'sw-contextual-field-prefix',
            'sw-field-input',
            'sw-contextual-field-suffix',
            'hint',
            'label',
        ],
        blocks: [
            'sw_contextual_field',
        ],
        computed: [
            'hasPrefix',
            'hasSuffix',
        ],
    },
});
