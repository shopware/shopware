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
    name: 'sw-settings-rule-category-tree',
    load: () => import('./src/sw-settings-rule-category-tree'),
    public: {
        props: [
            'rule',
            'association',
            'categoriesCollection',
            'hideHeadline',
            'hideSearch',
        ],
        emits: [
            'on-selection',
        ],
        blocks: [
            'sw_settings_rule_category_tree',
            'sw_settings_rule_category_tree_toolbar',
            'sw_settings_rule_category_tree_component',
            'sw_settings_rule_category_tree_items',
            'sw_settings_rule_category_tree_item',
        ],
        computed: [
            'categoryRepository',
            'treeCriteria',
        ],
        methods: [
            'searchTreeItems',
            'onCheckItem',
            'getTreeItems',
        ],
    },
});
