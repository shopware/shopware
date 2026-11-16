/**
 * @sw-package framework
 */

const fs = require('fs');
const os = require('os');
const path = require('path');
const { runCodemod, todo } = require('./tester');

const template = (body) => `<template>\n${body}\n</template>`;
const deprecated = (block, hint) => `The Administration Twig block "${block}" is deprecated. ${hint}`;
const renamed = (block, to) => deprecated(block, `Use "${to}" instead.`);

const PLUGIN = '/plugin/src/extension';
const SHARED_NAME = deprecated(
    'sw_entity_single_select_base_results_list_result_label',
    'Several core components use this block name. Check which one the template overrides.',
);

/** What the codemod comment names for a rename without a name that renders on 6.7 and 6.8, as `[what, component, block, message]`. */
const MESSAGES = [
    [
        'the _mt_ block when the 6.7 target sits in the legacy sw-tabs branch',
        'sw-cms-el-config-buy-box',
        'sw_entity_single_select_base_results_list_result_label',
        deprecated(
            'sw_entity_single_select_base_results_list_result_label',
            'It is removed from sw-cms-el-config-buy-box. Override sw_cms_element_buy_box_config_mt_product_select_result_label instead (renders with the v6.8.0.0 flag from 6.7.14).',
        ),
    ],
    [
        'the tabs computed for a renamed tab item',
        'sw-sales-channel-detail',
        'sw_sales_channel_detail_content_tab_product_comparison',
        deprecated(
            'sw_sales_channel_detail_content_tab_product_comparison',
            'It is removed. Add the tab to the salesChannelDetailTabs computed instead (renders with the v6.8.0.0 flag from 6.7.14).',
        ),
    ],
    [
        'both blocks of a split',
        'sw-settings-storefront-index',
        'sw_settings_storefront_smtp_settings',
        deprecated(
            'sw_settings_storefront_smtp_settings',
            'It is removed. Override sw_settings_storefront_settings_icon_cache and sw_settings_storefront_settings_speculation_rules instead.',
        ),
    ],
    [
        'the component a block moves to',
        'sw-mail-template-detail',
        'sw_mail_template_detail_preview_modal_footer',
        deprecated(
            'sw_mail_template_detail_preview_modal_footer',
            'It is removed from sw-mail-template-detail. Override sw_mail_template_detail_preview_modal_footer in sw-mail-template-preview-modal instead (renders with the v6.8.0.0 flag from 6.7.11).',
        ),
    ],
];

// Templates without a core component name take the override target from the script next to them.
const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'sw-renamed-twig-blocks-'));
const writeFile = (file, content) => {
    fs.mkdirSync(path.dirname(path.join(directory, file)), { recursive: true });
    fs.writeFileSync(path.join(directory, file), content);

    return path.join(directory, file);
};
const productBoxOverride = writeFile('product-box/acme-product-box.html.twig', '');
writeFile('product-box/index.js', "Shopware.Component.override('sw-cms-el-config-product-box', { template });");

describe('twig-block-renames', () => {
    afterAll(() => {
        fs.rmSync(directory, { recursive: true, force: true });
    });

    runCodemod('twig-block-renames', {
        valid: [
            {
                name: 'ignores blocks that keep their name and the new names',
                filename: `${PLUGIN}/sw-condition-date-range/sw-condition-date-range.html.twig`,
                code: template('{% block sw_condition_date_range_field_to_date %}\n    {% block sw_condition_date_range_field_from_date %}{% endblock %}\n{% endblock %}'),
            },
            {
                name: 'finds nothing to rename on a second run',
                filename: `${PLUGIN}/acme/acme.html.twig`,
                code: template('{% block sw_condition_date_range_field_to_date %}\n    {% parent %}\n{% endblock sw_condition_date_range_field_to_date %}'),
            },
            {
                name: 'ignores a shared name in an override of another core component',
                filename: `${PLUGIN}/sw-entity-single-select/sw-entity-single-select.html.twig`,
                code: template('{% block sw_entity_single_select_base_results_list_result_label %}{% endblock %}'),
            },
            {
                name: 'leaves the shared-name report to the removal codemod when it covers the name as well',
                filename: `${PLUGIN}/acme/acme.html.twig`,
                code: template('{% block sw_cms_element_image_gallery_config_settings_min_height %}{% endblock %}'),
            },
        ],
        invalid: [
            {
                name: 'renames a typo and its named endblock',
                filename: `${PLUGIN}/acme/acme.html.twig`,
                code: template('{% block sw_condiiton_date_range_field_to_date %}\n    {% parent %}\n{% endblock sw_condiiton_date_range_field_to_date %}'),
                output: template('{% block sw_condition_date_range_field_to_date %}\n    {% parent %}\n{% endblock sw_condition_date_range_field_to_date %}'),
                errors: [{ message: renamed('sw_condiiton_date_range_field_to_date', 'sw_condition_date_range_field_to_date'), line: 2 }],
            },
            {
                name: 'renames only the endblock that closes the block and keeps extra whitespace and trim markers',
                filename: `${PLUGIN}/acme/acme.html.twig`,
                code: template([
                    '{%-  block sw_cms_detail_stage_empty_stade_content -%}',
                    '    {% block acme_inner %}{% endblock acme_inner %}',
                    '{%- endblock  sw_cms_detail_stage_empty_stade_content -%}',
                ].join('\n')),
                output: template([
                    '{%-  block sw_cms_detail_stage_empty_stage_content -%}',
                    '    {% block acme_inner %}{% endblock acme_inner %}',
                    '{%- endblock  sw_cms_detail_stage_empty_stage_content -%}',
                ].join('\n')),
                errors: 1,
            },
            {
                name: 'renames nested blocks of one override together',
                filename: `${PLUGIN}/sw-product-stream-filter/sw-product-stream-filter.html.twig`,
                code: template('{% block sw_product_stream_filter %}\n    {% block sw_product_stream_filter_container %}{% parent %}{% endblock %}\n{% endblock %}'),
                output: template('{% block sw_condition_base %}\n    {% block sw_condition_base_content %}{% parent %}{% endblock %}\n{% endblock %}'),
                errors: [
                    { message: renamed('sw_product_stream_filter', 'sw_condition_base') },
                    { message: renamed('sw_product_stream_filter_container', 'sw_condition_base_content') },
                ],
            },
            {
                name: 'renames a shared name when the file name is the core component',
                filename: `${PLUGIN}/sw-cms-el-config-product-box/sw-cms-el-config-product-box.html.twig`,
                code: template('{% block sw_entity_single_select_base_results_list_result_label %}{% endblock %}'),
                output: template('{% block sw_cms_element_product_box_config_product_select_result_item_inner %}{% endblock %}'),
                errors: [
                    {
                        message: renamed(
                            'sw_entity_single_select_base_results_list_result_label',
                            'sw_cms_element_product_box_config_product_select_result_item_inner',
                        ),
                    },
                ],
            },
            {
                name: 'renames a shared name when the script next to the template overrides the core component',
                filename: productBoxOverride,
                code: template('{% block sw_entity_single_select_base_results_list_result_label %}{% endblock %}'),
                output: template('{% block sw_cms_element_product_box_config_product_select_result_item_inner %}{% endblock %}'),
                errors: 1,
            },
            {
                name: 'marks a shared name when the override target is unknown',
                filename: `${PLUGIN}/acme/acme.html.twig`,
                code: template('{% block sw_entity_single_select_base_results_list_result_label %}{% endblock %}'),
                output: template(`${todo(SHARED_NAME)}\n{% block sw_entity_single_select_base_results_list_result_label %}{% endblock %}`),
                errors: [{ message: SHARED_NAME }],
            },
            {
                name: 'writes a TODO when the new name is overridden in the same template',
                filename: `${PLUGIN}/acme/acme.html.twig`,
                code: template('{% block sw_settings_listing_option_base_content %}\n    {% block sw_settings_listing_option_base_smart_content %}{% endblock %}\n{% endblock %}'),
                output: template([
                    '{% block sw_settings_listing_option_base_content %}',
                    `    ${todo(deprecated('sw_settings_listing_option_base_smart_content', 'Use "sw_settings_listing_option_base_content" instead, which this template overrides already. Merge both overrides.'))}`,
                    '    {% block sw_settings_listing_option_base_smart_content %}{% endblock %}',
                    '{% endblock %}',
                ].join('\n')),
                errors: 1,
            },
            ...MESSAGES.map(([what, component, block, message]) => ({
                name: `names ${what}`,
                filename: `${PLUGIN}/${component}/${component}.html.twig`,
                code: template(`    {% block ${block} %}{% endblock %}`),
                output: template(`    ${todo(message)}\n    {% block ${block} %}{% endblock %}`),
                errors: [{ message }],
            })),
        ],
    });
});
