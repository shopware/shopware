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
    name: 'sw-cms-el-config-form',
    load: () => import('./src/sw-cms-el-config-form'),
    public: {
        blocks: [
            'sw_cms_el_config_form',
            'sw_cms_el_config_form_mt_tabs',
            'sw_cms_el_form_config_mt_content',
            'sw_cms_el_form_config_mt_content_form_type',
            'sw_cms_el_form_config_mt_content_form_title',
            'sw_cms_el_form_config_mt_content_form_confirmation_text',
            'sw_cms_el_form_config_mt_options',
            'sw_cms_el_config_form_tab_content',
            'sw_cms_el_form_config_tab_options',
            'sw_cms_el_form_config_content',
            'sw_cms_el_form_config_content_form_type',
            'sw_cms_el_form_config_content_form_title',
            'sw_cms_el_form_config_content_form_confirmation_text',
            'sw_cms_el_form_config_options',
        ],
        computed: [
            'tabs',
            'getLastMailClass',
            'formTypeOptions',
            'requireConfigTab',
        ],
        methods: [
            'createdComponent',
            'getShopMail',
            'setShopMail',
            'updateMailReceiver',
            'validateMail',
        ],
    },
});
