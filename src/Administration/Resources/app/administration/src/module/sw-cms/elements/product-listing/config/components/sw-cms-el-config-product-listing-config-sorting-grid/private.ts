import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-cms-el-config-product-listing-config-sorting-grid',
    load: () => import('./src/sw-cms-el-config-product-listing-config-sorting-grid'),
    public: {
        props: [
            'modelValue',
            'defaultSorting',
            'disabled',
        ],
        emits: [
            'sorting-delete',
            'update:modelValue',
            'inline-edit-save',
            'inline-edit-cancel',
        ],
        blocks: [
            'sw_cms_el_config_product_listing_config_sorting_grid',
        ],
        computed: [
            'productSortings',
            'visibleProductSortings',
            'paginationVisible',
            'customFieldRepository',
            'customFieldCriteria',
            'total',
            'gridColumns',
        ],
        methods: [
            'createdComponent',
            'fetchCustomFields',
            'formatProductSortingFields',
            'isItemACustomField',
            'stripCustomFieldPath',
            'getCustomFieldLabelByCriteriaName',
            'getCustomFieldByName',
            'onDelete',
            'onInlineEditSave',
            'onInlineEditCancel',
            'isDefaultSorting',
            'onPageChange',
        ],
    },
});
