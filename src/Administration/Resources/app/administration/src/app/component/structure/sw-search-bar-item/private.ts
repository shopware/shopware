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
    name: 'sw-search-bar-item',
    load: () => import('./src/sw-search-bar-item'),
    public: {
        props: [
            'item',
            'type',
            'index',
            'column',
            'searchTerm',
            'entityIconColor',
            'entityIconName',
        ],
        emits: [
            'click-search-result',
        ],
        blocks: [
            'sw_search_bar_item',
            'sw_search_bar_item_icon',
            'sw_search_bar_item_customer',
            'sw_search_bar_item_customer_label',
            'sw_search_bar_item_customer_label_name',
            'sw_search_bar_item_customer_label_number',
            'sw_search_bar_item_product',
            'sw_search_bar_item_product_label',
            'sw_search_bar_item_product_label_name',
            'sw_search_bar_item_product_label_number',
            'sw_search_bar_item_category',
            'sw_search_bar_item_category_label',
            'sw_search_bar_item_order',
            'sw_search_bar_item_order_label',
            'sw_search_bar_item_order_label_name',
            'sw_search_bar_item_order_label_number',
            'sw_search_bar_item_media',
            'sw_search_bar_item_media_label',
            'sw_search_bar_item_cms_page',
            'sw_search_bar_item_cms_page_label',
            'sw_search_bar_item_landing_page',
            'sw_search_bar_item_cms_landing_page_label',
            'sw_search_bar_item_module',
            'sw_search_bar_item_module_label',
            'sw_search_bar_item_module_label_module',
            'sw_search_bar_item_module_label_shortcut',
            'sw_search_bar_item_module_label_action',
            'sw_search_bar_item_other_entity',
            'sw_search_bar_item_other_entity_label',
        ],
        computed: [
            'searchTypes',
            'moduleManifest',
            'detailRoute',
            'displayValue',
            'componentClasses',
            'moduleName',
            'routeName',
            'iconName',
            'iconColor',
            'shortcut',
            'productDisplayName',
            'currentUser',
            'mediaNameFilter',
        ],
        methods: [
            'createdComponent',
            'destroyedComponent',
            'registerEvents',
            'removeEvents',
            'checkActiveState',
            'onEnter',
            'onMouseEnter',
            'onClickSearchResult',
        ],
    },
});
