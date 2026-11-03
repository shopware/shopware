import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-flow-create-mail-template-modal',
    load: () => import('./src/sw-flow-create-mail-template-modal'),
    public: {
        emits: [
            'modal-close',
            'process-finish',
        ],
        blocks: [
            'sw_flow_create_mail_template_modal',
            'sw_flow_create_mail_template_modal_content',
            'sw_flow_create_mail_template_modal_type_selection',
            'sw_flow_create_mail_template_modal_info',
            'sw_flow_create_mail_template_modal_subject',
            'sw_flow_create_mail_template_modal_sender_name',
            'sw_flow_create_mail_template_modal_description',
            'sw_flow_create_mail_template_modal_plain_content',
            'sw_flow_create_mail_template_modal_html_content',
            'sw_flow_create_mail_template_modal_footer',
            'sw_flow_create_mail_template_modal_footer_cancel_button',
            'sw_flow_create_mail_template_modal_footer_save_button',
        ],
        computed: [
            'mailTemplateRepository',
            'mailTemplateTypeRepository',
            'mailTemplateCriteria',
            'outerCompleterFunction',
        ],
        methods: [
            'createdComponent',
            'onClose',
            'onAddMailTemplate',
            'getMailTemplateType',
            'onChangeType',
            'getMailTemplate',
        ],
    },
});
