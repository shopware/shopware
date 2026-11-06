/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-mail-template-index',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-mail-template-index that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks')],
    usage: [
        removeTemplateBlock({
            from: 'sw_mail_template_list_content_card',
            moveTo: 'sw_mail_template_list_router_view (or override sw-mail-template-list and sw-mail-header-footer-list)',
            components: ['sw-mail-template-index'],
        }),
    ],
});
