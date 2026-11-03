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
    name: 'sw-radio-field',
    load: () => import('./src/sw-radio-field'),
    public: {
        props: [
            'bordered',
            'block',
            'description',
            'options',
            'value',
        ],
        emits: [
            'update:value',
        ],
        slots: [
            '`custom-field-${option.value}`',
            'label',
            'hint',
        ],
        blocks: [
            'sw_radio_field',
            'sw_radio_field_help_text',
        ],
        computed: [
            'classes',
            'currentIndex',
        ],
        methods: [
            'onChange',
        ],
    },
});
