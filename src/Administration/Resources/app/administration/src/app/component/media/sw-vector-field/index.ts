import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package innovation
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-vector-field',
    load: () => import('./src/sw-vector-field'),
    public: {
        props: [
            'value',
            'linkable',
            'label',
            'description',
            'disabled',
            'step',
            'variant',
        ],
        emits: [
            'update:value',
            'input-change',
            'link-change',
        ],
        slots: [
            'label',
        ],
        blocks: [
            'sw_vector_field_label',
            'sw_vector_field_input',
            'sw_vector_field_input_inner',
        ],
        computed: [
            'classes',
        ],
        methods: [
            'onChange',
            'onInput',
            'updateCurrentValue',
            'onLinkToggle',
        ],
    },
});
