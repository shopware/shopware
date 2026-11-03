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
    name: 'sw-cms-el-image-gallery',
    load: () => import('./src/sw-cms-el-image-gallery'),
    public: {
        blocks: [
            'sw_cms_element_image_gallery',
            'sw_cms_element_image_gallery_grid',
            'sw_cms_element_image_gallery_selection',
            'sw_cms_element_image_gallery_empty',
            'sw_cms_element_image_gallery_nav_arrow',
            'sw_cms_element_image_gallery_slider',
        ],
        computed: [
            'currentDeviceView',
            'galleryPositionClass',
            'currentDeviceViewClass',
            'verticalAlignStyle',
            'mediaUrls',
            'isProductPage',
            'assetFilter',
        ],
        methods: [
            'createdComponent',
            'mountedComponent',
            'getPlaceholderItems',
            'onChangeGalleryImage',
            'activeMediaClass',
            'setGalleryLimit',
        ],
    },
});
