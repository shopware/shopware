/**
 * @sw-package framework
 */

import { templateBlockMigration, upgradeGuide } from '../helpers';
import { removeBlocks } from './block-steps';

export default templateBlockMigration({
    id: 'template-block.newsletter-recipient-filter',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'Newsletter recipient status filter Twig blocks are deprecated with the removed newsletter recipient filter UI.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#administration'),
        upgradeGuide('UPGRADE-6.8.md#the-following-template-blocks-of-the-newsletter-recipient-filter-have-been-removed'),
        upgradeGuide('UPGRADE-6.8.md#sw-newsletter-recipient-filter-switch'),
    ],
    usage: [
        ...removeBlocks(
            'sw-newsletter-recipient-list',
            [
                'sw_newsletter_recipient_list_sidebar_filter_status_not_set',
                'sw_newsletter_recipient_list_sidebar_filter_status_direct',
                'sw_newsletter_recipient_list_sidebar_filter_status_opt_in',
                'sw_newsletter_recipient_list_sidebar_filter_status_opt_out',
            ],
            { to: 'sw_newsletter_recipient_list_sidebar_filter_status' },
        ),
        ...removeBlocks('sw-newsletter-recipient-filter-switch', [
            'sw_newsletter_recipient_filter_switch',
            'sw_newsletter_recipient_filter_switch_field',
        ]),
    ],
});
