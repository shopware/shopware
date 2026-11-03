import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-mail-template-index',
    load: () => import('./src/sw-mail-template-index'),
    public: {
        blocks: [
            'sw_mail_template_list',
            'sw_mail_templates_list_search_bar',
            'sw_mail_template_list_smart_bar_header',
            'sw_mail_template_list_smart_bar_header_title',
            'sw_mail_template_list_smart_bar_header_title_text',
            'sw_mail_template_list_smart_bar_actions',
            'sw_mail_template_list_smart_bar_actions_add',
            'sw_mail_header_footer_list_smart_bar_actions_add',
            'sw_mail_template_list_language_switch',
            'sw_mail_template_list_content',
            'sw_mail_template_list_tabs',
            'sw_mail_template_list_mt_tabs',
            'sw_mail_template_list_router_view',
            'sw_mail_template_list_content_card',
        ],
        computed: [
            'searchType',
            'mailTemplateTabs',
        ],
        methods: [
            'onChangeLanguage',
            'getList',
            'onCreateMailTemplate',
        ],
    },
});
