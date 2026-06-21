import { reference, removeTemplateBlock, templateBlockMigration } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.newsletter-recipient-filter',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'Newsletter recipient status filter Twig blocks are deprecated with the removed newsletter recipient filter UI.',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#administration' }),
    ],
    usage: [
        removeTemplateBlock({
            from: 'sw_newsletter_recipient_list_sidebar_filter_status_not_set',
            fix: 'manual',
        }),
        removeTemplateBlock({
            from: 'sw_newsletter_recipient_list_sidebar_filter_status_direct',
            fix: 'manual',
        }),
        removeTemplateBlock({
            from: 'sw_newsletter_recipient_list_sidebar_filter_status_opt_in',
            fix: 'manual',
        }),
        removeTemplateBlock({
            from: 'sw_newsletter_recipient_list_sidebar_filter_status_opt_out',
            fix: 'manual',
        }),
    ],
});
