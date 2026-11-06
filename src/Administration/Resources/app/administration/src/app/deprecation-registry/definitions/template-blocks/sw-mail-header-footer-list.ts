/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-mail-header-footer-list',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-mail-header-footer-list that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_mail_header_footer_list_grid_empty_state_icon',
            moveTo: 'sw_mail_header_footer_list_grid_empty_state (pass the icon and texts as props of mt-empty-state)',
            components: ['sw-mail-header-footer-list'],
        }),
    ],
});
