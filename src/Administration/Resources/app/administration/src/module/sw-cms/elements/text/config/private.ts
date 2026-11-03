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
    name: 'sw-cms-el-config-text',
    load: () => import('./src/sw-cms-el-config-text'),
    public: {
        emits: [
            'element-update',
        ],
        blocks: [
            'sw_cms_el_config_text',
            'sw_cms_el_config_text_mt_tabs',
            'sw_cms_el_text_config_mt_content',
            'sw_cms_el_text_config_mt_content_field',
            'sw_cms_el_text_config_mt_settings',
            'sw_cms_el_text_config_mt_settings_vertical_align',
            'sw_cms_el_config_text_tab_content',
            'sw_cms_el_text_config_tab_options',
            'sw_cms_el_text_config_content',
            'sw_cms_el_text_config_settings',
            'sw_cms_el_text_config_settings_vertical_align',
        ],
        computed: [
            'tabs',
            'availableDataMappings',
            'customTextEditorButtons',
            'alignmentOptions',
        ],
        methods: [
            'createdComponent',
            'handleUpdateContent',
            'onBlur',
            'onInput',
            'emitChanges',
        ],
    },
});
