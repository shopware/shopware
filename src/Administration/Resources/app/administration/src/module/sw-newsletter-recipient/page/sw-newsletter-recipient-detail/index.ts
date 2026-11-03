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
    name: 'sw-newsletter-recipient-detail',
    load: () => import('./src/sw-newsletter-recipient-detail'),
    public: {
        blocks: [
            'sw_newsletter_recipient_detail',
            'sw_newsletter_recipient_detail_header',
            'sw_newsletter_recipient_detail_actions',
            'sw_newsletter_recipient_detail_actions_edit',
            'sw_newsletter_recipient_detail_content',
            'sw_newsletter_recipient_detail_form',
            'sw_newsletter_recipient_detail_form_salutation',
            'sw_newsletter_recipient_detail_form_title',
            'sw_newsletter_recipient_detail_form_first_name',
            'sw_newsletter_recipient_detail_form_last_name',
            'sw_newsletter_recipient_detail_form_email',
            'sw_newsletter_recipient_detail_form_street',
            'sw_newsletter_recipient_detail_form_zip_code',
            'sw_newsletter_recipient_detail_form_city',
            'sw_newsletter_recipient_detail_form_language',
            'sw_newsletter_recipient_detail_form_sales_channel',
            'sw_newsletter_recipient_detail_form_tags',
            'sw_newsletter_recipient_detail_custom_fields',
        ],
        computed: [
            'identifier',
            'newsletterRecipientStore',
        ],
        methods: [
            'createdComponent',
            'onSave',
            'onSaveFinish',
            'onCancel',
            'loadCustomFieldSets',
        ],
    },
});
