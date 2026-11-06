/**
 * @sw-package framework
 */

import { removeTemplateBlock, renameTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.sw-flow-rule-modal',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Twig blocks of sw-flow-rule-modal that Shopware 6.8 renames or removes.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks'),
    ],
    usage: [
        renameTemplateBlock({
            from: 'sw_flow_rule_modal_conditions_card',
            to: 'sw_flow_rule_modal_mt_conditions_card',
            components: ['sw-flow-rule-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_flow_rule_modal_detail_description',
            to: 'sw_flow_rule_modal_mt_detail_description',
            components: ['sw-flow-rule-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_flow_rule_modal_detail_name',
            to: 'sw_flow_rule_modal_mt_detail_name',
            components: ['sw-flow-rule-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_flow_rule_modal_detail_priority',
            to: 'sw_flow_rule_modal_mt_detail_priority',
            components: ['sw-flow-rule-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_flow_rule_modal_detail_type',
            to: 'sw_flow_rule_modal_mt_detail_type',
            components: ['sw-flow-rule-modal'],
        }),
        renameTemplateBlock({
            from: 'sw_flow_rule_modal_tab_detail_content',
            to: 'sw_flow_rule_modal_mt_tab_detail_content',
            components: ['sw-flow-rule-modal'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        renameTemplateBlock({
            from: 'sw_flow_rule_modal_tab_rule_content',
            to: 'sw_flow_rule_modal_mt_tab_rule_content',
            components: ['sw-flow-rule-modal'],
            fix: 'unsafe-auto',
            message: 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.',
        }),
        removeTemplateBlock({
            from: 'sw_flow_rule_headers',
            moveTo: 'sw_flow_rule_modal_mt_tabs and add your tab to its tabs computed',
            components: ['sw-flow-rule-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_flow_rule_modal_content',
            moveTo: 'sw_flow_rule_modal_mt_content (the markup changed)',
            components: ['sw-flow-rule-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_flow_rule_modal_tab_detail',
            moveTo: 'sw_flow_rule_modal_mt_tabs and add your tab to its tabs computed',
            components: ['sw-flow-rule-modal'],
        }),
        removeTemplateBlock({
            from: 'sw_flow_rule_modal_tab_rule',
            moveTo: 'sw_flow_rule_modal_mt_tabs and add your tab to its tabs computed',
            components: ['sw-flow-rule-modal'],
        }),
    ],
});
