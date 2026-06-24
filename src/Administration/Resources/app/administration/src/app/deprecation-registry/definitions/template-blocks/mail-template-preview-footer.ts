import { reference, removeTemplateBlock, templateBlockMigration } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.mail-template-preview-footer',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Mail template preview modal footer Twig blocks are deprecated because the modal footer was removed.',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#administration' }),
    ],
    usage: [
        removeTemplateBlock({ from: 'sw_mail_template_detail_preview_modal_footer', fix: 'manual' }),
        removeTemplateBlock({ from: 'sw_mail_template_detail_preview_modal_footer_cancel', fix: 'manual' }),
    ],
});
