import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module and what it inherits from sw-mail-header-footer-detail,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-mail-header-footer-create',
    extends: 'sw-mail-header-footer-detail',
    load: () => import('./src/sw-mail-header-footer-create'),
    public: {
        blocks: [
            'sw_mail_header_footer_detail_language_switch',
            'sw_mail_header_footer_detail',
            'sw_mail_header_footer_detail_header',
            'sw_mail_header_footer_detail_actions',
            'sw_mail_header_footer_detail_actions_abort',
            'sw_mail_header_footer_detail_actions_save',
            'sw_mail_header_footer_detail_content',
            'sw_mail_header_footer_detail_content_language_info',
            'sw_mail_header_footer_detail_basic_info',
            'sw_mail_header_footer_basic_form_name_field',
            'sw_mail_header_footer_basic_form_description_field',
            'sw_mail_header_footer_basic_form_sales_channels_field',
            'sw_mail_header_footer_detail_content_header',
            'sw_mail_header_footer_detail_content_header_plain_field',
            'sw_mail_header_footer_detail_content_header_html_field',
            'sw_mail_header_footer_detail_content_footer',
            'sw_mail_header_footer_detail_content_footer_plain_field',
            'sw_mail_header_footer_detail_content_footer_html_field',
        ],
        computed: [
            'identifier',
            'mailHeaderFooterRepository',
            'mailHeaderFooterCriteria',
            'salesChannelRepository',
            'completerFunction',
            'allowSave',
            'tooltipSave',
        ],
        methods: [
            'createdComponent',
            'saveFinish',
            'onSave',
            'onClose',
            'loadEntityData',
            'abortOnLanguageChange',
            'saveOnLanguageChange',
            'onChangeLanguage',
            'onCancel',
            'confirmSave',
            'findAlreadyAssignedSalesChannels',
        ],
    },
});
