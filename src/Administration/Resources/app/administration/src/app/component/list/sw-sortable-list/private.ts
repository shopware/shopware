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
    name: 'sw-sortable-list',
    load: () => import('./src/sw-sortable-list'),
    public: {
        props: [
            'items',
            'sortable',
            'dragConf',
            'scrollOnDrag',
            'scrollOnDragConf',
        ],
        emits: [
            'items-sorted',
        ],
        slots: [
            'default',
            'item',
        ],
        blocks: [
            'sw_sortable_list_container',
            'sw_sortable_list',
            'sw_sortable_list_item',
            'sw_sortable_list_empty_state',
        ],
        computed: [
            'hasItems',
            'isSortable',
            'mergedDragConfig',
            'mergedScrollOnDragConfig',
            'scrollableParent',
        ],
        methods: [
            'findScrollableParent',
            'hasOrderChanged',
            'onDragEnter',
            'onDragStart',
            'onScroll',
            'scroll',
            'onDrop',
        ],
    },
});
