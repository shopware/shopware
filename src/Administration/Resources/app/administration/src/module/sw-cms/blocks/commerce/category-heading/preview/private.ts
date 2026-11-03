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
    name: 'sw-cms-preview-category-heading',
    load: () => import('./src/sw-cms-preview-category-heading'),
    public: {
        blocks: [
            'sw_cms_preview_category_heading',
            'sw_cms_preview_category_heading_name',
        ],
    },
});
