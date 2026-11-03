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
    name: 'sw-simple-search-field',
    load: () => import('./src/sw-simple-search-field'),
    public: {
        props: [
            'variant',
            'value',
            'size',
            'delay',
            'icon',
        ],
        emits: [
            'update:value',
            'search-term-change',
        ],
        slots: [
            'sw-simple-search-field-icon',
        ],
        blocks: [
            'sw_simple_search_field',
            'sw_simple_search_field_input',
            'sw_simple_search_field_search_icon',
            'sw_simple_search_field_slot_search_icon',
        ],
        computed: [
            'fieldClasses',
            'placeholder',
        ],
        methods: [
            'onInput',
        ],
    },
});
