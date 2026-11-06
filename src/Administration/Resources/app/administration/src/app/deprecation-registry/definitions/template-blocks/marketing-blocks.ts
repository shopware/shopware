/**
 * @sw-package framework
 */

import { removeTemplateBlocks, renameTemplateBlocks, templateBlockMigration, upgradeGuide } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.marketing-6-8',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'Twig blocks of the promotion, newsletter, mail template and flow modules that Shopware 6.8 renames or removes.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks'),
    ],
    usage: [
        ...removeTemplateBlocks({
            components: ['sw-flow-detail'],
            sharedNames: ['sw_flow_tabs_header_extension'],
            blocks: {
                sw_flow_tabs_header_builder: 'sw_flow_tabs_header_mt_tabs and add your tab to its flowDetailTabs computed',
                sw_flow_tabs_header_extension: 'sw_flow_tabs_header_mt_tabs and add your tab to its flowDetailTabs computed',
                sw_flow_tabs_header_general: 'sw_flow_tabs_header_mt_tabs and add your tab to its flowDetailTabs computed',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-flow-index'],
            sharedNames: ['sw_flow_tabs_header_extension'],
            blocks: {
                sw_flow_tabs_header_extension: 'the flowTabs computed of sw-flow-index',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-flow-list'],
            blocks: {
                sw_flow_list_empty_state_icon:
                    'sw_flow_list_empty_state (pass the icon and texts as props of mt-empty-state)',
            },
        }),
        ...renameTemplateBlocks({
            components: ['sw-flow-rule-modal'],
            activeSlot: ['sw_flow_rule_modal_tab_detail_content', 'sw_flow_rule_modal_tab_rule_content'],
            blocks: {
                sw_flow_rule_modal_conditions_card: 'sw_flow_rule_modal_mt_conditions_card',
                sw_flow_rule_modal_detail_description: 'sw_flow_rule_modal_mt_detail_description',
                sw_flow_rule_modal_detail_name: 'sw_flow_rule_modal_mt_detail_name',
                sw_flow_rule_modal_detail_priority: 'sw_flow_rule_modal_mt_detail_priority',
                sw_flow_rule_modal_detail_type: 'sw_flow_rule_modal_mt_detail_type',
                sw_flow_rule_modal_tab_detail_content: 'sw_flow_rule_modal_mt_tab_detail_content',
                sw_flow_rule_modal_tab_rule_content: 'sw_flow_rule_modal_mt_tab_rule_content',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-flow-rule-modal'],
            blocks: {
                sw_flow_rule_headers: 'sw_flow_rule_modal_mt_tabs and add your tab to its tabs computed',
                sw_flow_rule_modal_content: 'sw_flow_rule_modal_mt_content (the markup changed)',
                sw_flow_rule_modal_tab_detail: 'sw_flow_rule_modal_mt_tabs and add your tab to its tabs computed',
                sw_flow_rule_modal_tab_rule: 'sw_flow_rule_modal_mt_tabs and add your tab to its tabs computed',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-mail-template-index'],
            blocks: {
                sw_mail_template_list_content_card:
                    'sw_mail_template_list_router_view (or override sw-mail-template-list and sw-mail-header-footer-list)',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-mail-template-list'],
            blocks: {
                sw_mail_template_list_grid_empty_state_icon:
                    'sw_mail_template_list_grid_empty_state (pass the icon and texts as props of mt-empty-state)',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-newsletter-recipient-filter-switch'],
            blocks: {
                sw_newsletter_recipient_filter_switch: null,
                sw_newsletter_recipient_filter_switch_field: null,
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-promotion-v2-detail'],
            blocks: {
                sw_promotion_v2_detail_content_tabs_conditions:
                    'sw_promotion_v2_detail_content_mt_tabs and add your tab to its promotionDetailTabs computed',
                sw_promotion_v2_detail_content_tabs_discounts:
                    'sw_promotion_v2_detail_content_mt_tabs and add your tab to its promotionDetailTabs computed',
                sw_promotion_v2_detail_content_tabs_general:
                    'sw_promotion_v2_detail_content_mt_tabs and add your tab to its promotionDetailTabs computed',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-promotion-v2-empty-state-hero'],
            blocks: {
                sw_promotion_v2_empty_state_hero: null,
                sw_promotion_v2_empty_state_hero_content: null,
                sw_promotion_v2_empty_state_hero_content_actions: null,
                sw_promotion_v2_empty_state_hero_content_description: null,
                sw_promotion_v2_empty_state_hero_content_title: null,
                sw_promotion_v2_empty_state_hero_image: null,
                sw_promotion_v2_empty_state_hero_image_asset: null,
                sw_promotion_v2_empty_state_hero_slot_actions: null,
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-promotion-v2-individual-codes-behavior'],
            blocks: {
                sw_promotion_v2_individual_codes_behavior_empty_state_icon:
                    'sw_promotion_v2_individual_codes_behavior_empty_state (pass the icon and texts as props of mt-empty-state)',
            },
        }),
        ...removeTemplateBlocks({
            components: ['sw-promotion-v2-list'],
            blocks: {
                sw_promotion_v2_list_empty_state_actions: 'sw_promotion_v2_list_empty_state',
            },
        }),
    ],
});
