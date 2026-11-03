import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module and what it inherits from sw-cms-el-image,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-cms-el-manufacturer-logo',
    extends: 'sw-cms-el-image',
    load: () => import('./src/sw-cms-el-manufacturer-logo'),
    public: {
        blocks: [
            'sw_cms_element_image_content',
            'sw_cms_element_image',
        ],
        computed: [
            'isProductPage',
            'styles',
            'logoStyles',
            'mediaRepository',
            'displayModeClass',
            'imgStyles',
            'horizontalAlign',
            'verticalAlignClass',
            'mediaUrl',
            'assetFilter',
            'mediaConfigValue',
        ],
        methods: [
            'createdComponent',
            'updateDemoValue',
            'updateMappedDemoMedia',
        ],
    },
});
