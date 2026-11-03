import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-condition-tree-node',
    load: () => import('./src/sw-condition-tree-node'),
    public: {
        props: [
            'level',
            'condition',
            'parentCondition',
            'disabled',
            'insertBefore',
            'insertAfter',
        ],
        blocks: [
            'sw_condtion_tree_node',
        ],
        computed: [
            'conditionNodeComponent',
        ],
        methods: [
            'deleteNode',
            'insertNewNodeBefore',
            'insertNewNodeAfter',
        ],
    },
});
