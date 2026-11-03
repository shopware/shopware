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
    name: 'sw-condition-all-line-items-container',
    load: () => import('./src/sw-condition-all-line-items-container'),
    public: {
        emits: [
            'create-before',
            'create-after',
        ],
        blocks: [
            'sw_condition_all_line_items_container',
        ],
        computed: [
            'children',
            'childType',
            'childrenLength',
        ],
        methods: [
            'createdComponent',
            'setConditionValue',
            'onAddPlaceholder',
            'unwrapCondition',
            'onInsertBefore',
            'onInsertAfter',
        ],
    },
});
