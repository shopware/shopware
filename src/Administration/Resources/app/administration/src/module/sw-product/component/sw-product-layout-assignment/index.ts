import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-product-layout-assignment',
    load: () => import('./src/sw-product-layout-assignment'),
    public: {
        props: [
            'cmsPage',
            'product',
        ],
        emits: [
            'modal-layout-open',
            'button-edit-click',
            'button-delete-click',
        ],
        blocks: [
            'sw_product_layout_assignment',
            'sw_product_layout_assignment_firgure',
            'sw_product_layout_assignment_firgure_content',
            'sw_product_layout_assignment_description',
            'sw_product_layout_assignment_heading',
            'sw_product_layout_assignment_heading_title',
            'sw_product_layout_assignment_heading_subtitle',
            'sw_product_layout_assignment_buttons',
            'sw_product_layout_assignment_button_add',
            'sw_product_layout_assignment_button_edit',
            'sw_product_layout_assignment_button_delete',
        ],
        computed: [
            'pageName',
        ],
        methods: [
            'openLayoutModal',
            'openInPageBuilder',
            'onLayoutReset',
        ],
    },
});
