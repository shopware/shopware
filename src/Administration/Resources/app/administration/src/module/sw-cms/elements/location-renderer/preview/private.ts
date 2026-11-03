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
    name: 'sw-cms-el-preview-location-renderer',
    load: () => import('./src/sw-cms-el-preview-location-renderer'),
    public: {
        props: [
            'elementData',
        ],
        blocks: [
            'sw_cms_el_preview_location_renderer',
        ],
        computed: [
            'src',
            'previewLocation',
            'publishingKey',
        ],
        methods: [
            'createdComponent',
        ],
    },
});
