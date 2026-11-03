import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-product-stream-field-select',
    load: () => import('./src/sw-product-stream-field-select'),
    public: {
        props: [
            'definition',
            'field',
            'index',
            'disabled',
            'hasError',
        ],
        emits: [
            'field-changed',
        ],
        blocks: [
            'sw_condition_base_field_condition',
        ],
        computed: [
            'options',
        ],
        methods: [
            'changeField',
            'getPropertyTranslation',
        ],
    },
});
