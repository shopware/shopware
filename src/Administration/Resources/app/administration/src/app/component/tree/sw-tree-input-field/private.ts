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
    name: 'sw-tree-input-field',
    load: () => import('./src/sw-tree-input-field'),
    public: {
        props: [
            'currentValue',
            'disabled',
        ],
        emits: [
            'new-item-create',
        ],
        slots: [
            'content',
        ],
        blocks: [
            'sw_tree_input_field',
            'sw_tree_input_fieldelement',
            'sw_tree_input_fieldelement_grip',
            'sw_tree_input_fieldelement_content',
            'sw_tree_input_fieldslot_content',
        ],
        computed: [
            'classes',
        ],
        methods: [
            'createNewItem',
        ],
    },
});
