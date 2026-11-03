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
    name: 'sw-cms-preview-product-heading',
    load: () => import('./src/sw-cms-preview-product-heading'),
    public: {
        blocks: [
            'sw_cms_preview_product_heading',
            'sw_cms_preview_product_heading_name',
            'sw_cms_preview_product_heading_logo',
            'sw_cms_preview_product_heading_placeholder_container',
            'sw_cms_preview_product_heading_placeholder',
        ],
    },
});
