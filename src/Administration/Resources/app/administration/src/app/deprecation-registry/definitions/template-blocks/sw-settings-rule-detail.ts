/**
 * @sw-package framework
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-settings-rule-detail',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-settings-rule-detail that Shopware 6.8 renames or removes.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches')],
    usage: [
        removeTemplateBlock({
            from: 'sw_settings_rule_detail_tab_items',
            moveTo: 'sw_settings_rule_detail_mt_tabs and add your tab to its ruleDetailTabs computed',
            components: ['sw-settings-rule-detail'],
        }),
    ],
});
