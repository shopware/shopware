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
    name: 'sw-cms-block-layout-config',
    load: () => import('./src/sw-cms-block-layout-config'),
    public: {
        props: [
            'block',
        ],
        blocks: [
            'sw_cms_block_layout_config',
            'sw_cms_block_layout_config_css_class',
            'sw_cms_block_layout_config_top_margin_field',
            'sw_cms_block_layout_config_bottom_margin_field',
            'sw_cms_block_layout_config_left_margin_field',
            'sw_cms_block_layout_config_right_margin_field',
        ],
    },
});
