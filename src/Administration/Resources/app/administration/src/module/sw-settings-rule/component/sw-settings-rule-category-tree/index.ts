import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@after-sales
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
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
