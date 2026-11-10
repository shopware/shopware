/**
 * @sw-package framework
 */

const fs = require('fs');
const os = require('os');
const path = require('path');
const { RuleTester } = require('eslint');
const rule = require('./no-renamed-twig-blocks');
const renames = require('./no-renamed-twig-blocks.data');
const { groups: removals } = require('./no-removed-twig-blocks.data');

const ruleTester = new RuleTester({
    languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 },
});

const template = (body) => `<template>\n${body}\n</template>`;
const todo = (message) => `{# TODO Codemod(6.8, twig-block-renames): ${message} #}`;

const PLUGIN = '/plugin/src/extension';
const SHARED_NAME = 'Block name is used by several core components. Check which one you override.';
const BUY_BOX_TODO = 'Block sw_entity_single_select_base_results_list_result_label of sw-cms-el-config-buy-box is removed. Override sw_cms_element_buy_box_config_mt_product_select_result_label instead (renders with the v6.8.0.0 flag from 6.7.14).';

// Templates without a core component name take the override target from the script next to them.
const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'sw-renamed-twig-blocks-'));
const writeFile = (file, content) => {
    fs.mkdirSync(path.dirname(path.join(directory, file)), { recursive: true });
    fs.writeFileSync(path.join(directory, file), content);

    return path.join(directory, file);
};
const productBoxOverride = writeFile('product-box/acme-product-box.html.twig', '');
writeFile('product-box/index.js', "Shopware.Component.override('sw-cms-el-config-product-box', { template });");
const assignmentExtension = writeFile('assignment/acme-assignment.html.twig', '');
writeFile('assignment/index.ts', "Component.extend('acme-assignment', 'sw-product-cross-selling-assignment', {});");
const ownComponent = writeFile('own/acme-date-range.html.twig', '');
writeFile('own/index.js', "Component.register('acme-date-range', { template });");

describe('no-renamed-twig-blocks', () => {
    afterAll(() => {
        fs.rmSync(directory, { recursive: true, force: true });
    });

    ruleTester.run('no-renamed-twig-blocks', rule, {
        valid: [
            {
                name: 'ignores .vue templates, which have no blocks',
                filename: `${PLUGIN}/sw-condition-date-range/sw-condition-date-range.vue`,
                code: template('{% block sw_condiiton_date_range_field_to_date %}{% endblock %}'),
            },
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
                name: 'ignores blocks inside Twig comments',
                filename: `${PLUGIN}/acme/acme.html.twig`,
                code: template('{# {% block sw_condiiton_date_range_field_to_date %}{% endblock %} #}'),
            },
            {
                name: 'ignores templates of components the extension registers itself',
                filename: ownComponent,
                code: template('{% block sw_condiiton_date_range_field_to_date %}{% endblock %}'),
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
                errors: [{ message: 'Block sw_condiiton_date_range_field_to_date is renamed to sw_condition_date_range_field_to_date.', line: 2 }],
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
                    { message: 'Block sw_product_stream_filter is renamed to sw_condition_base.' },
                    { message: 'Block sw_product_stream_filter_container is renamed to sw_condition_base_content.' },
                ],
            },
            {
                name: 'renames a shared name when the file name is the core component',
                filename: `${PLUGIN}/sw-cms-el-config-product-box/sw-cms-el-config-product-box.html.twig`,
                code: template('{% block sw_entity_single_select_base_results_list_result_label %}{% endblock %}'),
                output: template('{% block sw_cms_element_product_box_config_product_select_result_item_inner %}{% endblock %}'),
                errors: [{ message: 'Block sw_entity_single_select_base_results_list_result_label of sw-cms-el-config-product-box is renamed to sw_cms_element_product_box_config_product_select_result_item_inner.' }],
            },
            {
                name: 'renames a shared name when the script next to the template overrides the core component',
                filename: productBoxOverride,
                code: template('{% block sw_entity_single_select_base_results_list_result_label %}{% endblock %}'),
                output: template('{% block sw_cms_element_product_box_config_product_select_result_item_inner %}{% endblock %}'),
                errors: 1,
            },
            {
                name: 'renames a shared name when the script next to the template extends the core component',
                filename: assignmentExtension,
                code: template('{% block sw_entity_single_select_base_results_list_result_label %}{% endblock %}'),
                output: template('{% block sw_product_cross_selling_assignment_select_result_item_inner %}{% endblock %}'),
                errors: 1,
            },
            {
                name: 'reports a shared name without a fix when the override target is unknown',
                filename: `${PLUGIN}/acme/acme.html.twig`,
                code: template('{% block sw_entity_single_select_base_results_list_result_label %}{% endblock %}'),
                output: null,
                errors: [{ message: SHARED_NAME }],
            },
            {
                name: 'writes a TODO when the new name is overridden in the same template',
                filename: `${PLUGIN}/acme/acme.html.twig`,
                code: template('{% block sw_settings_listing_option_base_content %}\n    {% block sw_settings_listing_option_base_smart_content %}{% endblock %}\n{% endblock %}'),
                output: template([
                    '{% block sw_settings_listing_option_base_content %}',
                    `    ${todo('Block sw_settings_listing_option_base_smart_content is renamed to sw_settings_listing_option_base_content, which this template overrides already. Merge both overrides.')}`,
                    '    {% block sw_settings_listing_option_base_smart_content %}{% endblock %}',
                    '{% endblock %}',
                ].join('\n')),
                errors: 1,
            },
            {
                name: 'writes a TODO with the _mt_ block when the 6.7 target sits in the legacy sw-tabs branch',
                filename: `${PLUGIN}/sw-cms-el-config-buy-box/sw-cms-el-config-buy-box.html.twig`,
                code: template('    {% block sw_entity_single_select_base_results_list_result_label %}{% endblock %}'),
                output: template(`    ${todo(BUY_BOX_TODO)}\n    {% block sw_entity_single_select_base_results_list_result_label %}{% endblock %}`),
                errors: [{ message: BUY_BOX_TODO }],
            },
            {
                name: 'reports without a fix when the TODO is already there',
                filename: `${PLUGIN}/sw-cms-el-config-buy-box/sw-cms-el-config-buy-box.html.twig`,
                code: template(`${todo(BUY_BOX_TODO)}\n{% block sw_entity_single_select_base_results_list_result_label %}{% endblock %}`),
                output: null,
                errors: [{ message: BUY_BOX_TODO }],
            },
            {
                name: 'names the tabs computed for a renamed tab item',
                filename: `${PLUGIN}/acme/acme.html.twig`,
                code: template('{% block sw_sales_channel_detail_content_tab_product_comparison %}{% endblock %}'),
                output: template(`${todo('Block sw_sales_channel_detail_content_tab_product_comparison is removed. Add the tab to the salesChannelDetailTabs computed instead (renders with the v6.8.0.0 flag from 6.7.14).')}\n{% block sw_sales_channel_detail_content_tab_product_comparison %}{% endblock %}`),
                errors: 1,
            },
            {
                name: 'names both blocks of a split',
                filename: `${PLUGIN}/acme/acme.html.twig`,
                code: template('{% block sw_settings_storefront_smtp_settings %}{% endblock %}'),
                output: template(`${todo('Block sw_settings_storefront_smtp_settings is removed. Override sw_settings_storefront_settings_icon_cache and sw_settings_storefront_settings_speculation_rules instead.')}\n{% block sw_settings_storefront_smtp_settings %}{% endblock %}`),
                errors: 1,
            },
            {
                name: 'names the component a block moves to',
                filename: `${PLUGIN}/sw-mail-template-detail/sw-mail-template-detail.html.twig`,
                code: template('{% block sw_mail_template_detail_preview_modal_footer %}{% endblock %}'),
                output: template(`${todo('Block sw_mail_template_detail_preview_modal_footer of sw-mail-template-detail is removed. Override sw_mail_template_detail_preview_modal_footer in sw-mail-template-preview-modal instead (renders with the v6.8.0.0 flag from 6.7.11).')}\n{% block sw_mail_template_detail_preview_modal_footer %}{% endblock %}`),
                errors: 1,
            },
        ],
    });

    describe('no-renamed-twig-blocks data', () => {
        it('gives every entry either a new name or a 6.8 target', () => {
            renames.forEach((rename) => {
                const kinds = ['to', 'use', 'tab', 'hint'].filter((key) => rename[key]);

                expect([rename.from, kinds]).toEqual([rename.from, [kinds[0]]]);
                expect(rename.component).toMatch(/^sw-[a-z0-9-]+$/);
            });
        });

        it('marks names that several entries use as shared', () => {
            renames.filter((rename) => renames.filter(({ from }) => from === rename.from).length > 1).forEach((rename) => {
                expect([rename.component, rename.from, rename.shared]).toEqual([rename.component, rename.from, true]);
            });
        });

        it('does not list a block of a component as renamed and removed', () => {
            const removed = new Set(removals.flatMap((group) => Object.keys(group.blocks).map((name) => `${group.component}:${name}`)));

            expect(renames.filter((rename) => removed.has(`${rename.component}:${rename.from}`))).toEqual([]);
        });
    });
});
