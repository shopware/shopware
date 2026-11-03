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
    name: 'sw-custom-entity-input-field',
    load: () => import('./src/sw-custom-entity-input-field'),
    public: {
        props: [
            'value',
            'type',
            'label',
            'placeholder',
            'helpText',
        ],
        emits: [
            'update:value',
        ],
        blocks: [
            'sw_custom_entity_input_field',
        ],
        computed: [
            'currentValue',
        ],
        methods: [
            'onChange',
        ],
    },
});
