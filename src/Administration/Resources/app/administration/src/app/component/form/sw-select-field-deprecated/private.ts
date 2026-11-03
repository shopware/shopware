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
    name: 'sw-select-field-deprecated',
    load: () => import('./src/sw-select-field-deprecated'),
    public: {
        props: [
            'value',
            'placeholder',
            'options',
            'aside',
        ],
        emits: [
            'update:value',
        ],
        slots: [
            'default',
            'label',
            'hint',
        ],
        blocks: [
            'sw_block_field',
        ],
        computed: [
            'locale',
            'fallbackLocale',
            'swFieldSelectClasses',
            'hasOptions',
        ],
        methods: [
            'getOptionName',
            'onChange',
        ],
    },
});
