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
    name: 'sw-tagged-field',
    load: () => import('./src/sw-tagged-field'),
    public: {
        props: [
            'value',
            'placeholder',
            'addOnKey',
            'disabled',
        ],
        emits: [
            'update:value',
        ],
        slots: [
            'label',
            'hint',
        ],
        blocks: [
            'sw_tagged_field',
            'sw_tagged_field_inner',
            'sw_tagged_field_item_input',
            'sw_tagged_field_input',
        ],
        computed: [
            'hasValues',
            'taggedFieldClasses',
            'taggedFieldInputClasses',
        ],
        methods: [
            'dismissLastTag',
            'dismissTag',
            'performAddTag',
            'setFocus',
            'noTriggerKey',
        ],
    },
});
