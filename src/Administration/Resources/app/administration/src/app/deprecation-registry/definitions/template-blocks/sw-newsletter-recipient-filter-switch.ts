/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-newsletter-recipient-filter-switch',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-newsletter-recipient-filter-switch that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_newsletter_recipient_filter_switch',
            components: ['sw-newsletter-recipient-filter-switch'],
        }),
        removeTemplateBlock({
            from: 'sw_newsletter_recipient_filter_switch_field',
            components: ['sw-newsletter-recipient-filter-switch'],
        }),
    ],
});
