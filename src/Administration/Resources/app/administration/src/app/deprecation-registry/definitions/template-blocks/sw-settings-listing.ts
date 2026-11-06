/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-settings-listing',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-settings-listing that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_settings_listing_content_card_view_options_card_empty_state_icon',
            moveTo: 'sw_settings_listing_content_card_view_options_card_empty_state (pass the icon and texts as props of mt-empty-state)',
            components: ['sw-settings-listing'],
        }),
    ],
});
