/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const { renamed, removed, parseTwigBlocks } = require('./twig-blocks-6-8');

const tester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

const template = (...lines) => ['<template>', ...lines, '</template>'].join('\n');
const rename = { messageId: 'rename' };
const todo = { messageId: 'todo' };

describe('parseTwigBlocks', () => {
    it('finds nested blocks with their bodies and named endblock tags', () => {
        const text = '{% block outer %}a{% block inner %}b{% endblock inner %}{% endblock %}';
        const [outer, inner] = parseTwigBlocks(text);

        expect(outer).toMatchObject({ name: 'outer', start: 0, endNameRange: null });
        expect(text.slice(...outer.body)).toBe('a{% block inner %}b{% endblock inner %}');
        expect(text.slice(...inner.nameRange)).toBe('inner');
        expect(text.slice(...inner.endNameRange)).toBe('inner');
    });
});

tester.run('twig-blocks-6-8 (renamed)', renamed, {
    valid: [
        {
            name: 'ignores blocks that stay',
            filename: '/plugin/src/extension/sw-condition-date-range/sw-condition-date-range.html.twig',
            code: template('{% block sw_condition_date_range_field_to_date %}{% endblock %}'),
        },
        {
            name: 'ignores a shared block name in a template of another component',
            filename: '/plugin/src/component/acme-select/acme-select.html.twig',
            code: template('{% block sw_entity_single_select_base_results_list_result_label %}{% endblock %}'),
        },
    ],
    invalid: [
        {
            name: 'renames a block with a typo, also in its named endblock tag',
            filename: '/plugin/src/extension/acme/acme.html.twig',
            code: template('{% block sw_condiiton_date_range_field_to_date %}', '    {% parent %}', '{% endblock sw_condiiton_date_range_field_to_date %}'),
            output: template('{% block sw_condition_date_range_field_to_date %}', '    {% parent %}', '{% endblock sw_condition_date_range_field_to_date %}'),
            errors: [rename],
        },
        {
            name: 'renames a shared block name in a template of its component',
            filename: '/plugin/src/extension/sw-product-cross-selling-assignment/sw-product-cross-selling-assignment.html.twig',
            code: template('{% block sw_entity_single_select_base_results_list_result_label %}{% endblock %}'),
            output: template('{% block sw_product_cross_selling_assignment_select_result_item_inner %}{% endblock %}'),
            errors: [rename],
        },
        {
            name: 'renames a block of a component that extends the listed one',
            filename: '/plugin/src/extension/sw-settings-listing-option-create/sw-settings-listing-option-create.html.twig',
            code: template('{% block sw_settings_listing_option_base_smart_content %}{% endblock %}'),
            output: template('{% block sw_settings_listing_option_base_content %}{% endblock %}'),
            errors: [rename],
        },
        {
            name: 'renames the block an sw-block extends',
            filename: '/plugin/src/extension/sw-cms-detail.override.vue',
            code: template('    <sw-block extends="sw_cms_detail_stage_empty_stade_content" />'),
            output: template('    <sw-block extends="sw_cms_detail_stage_empty_stage_content" />'),
            errors: [rename],
        },
        {
            name: 'comments an override of the legacy tab content that reads the active slot variable',
            filename: '/plugin/src/extension/sw-media-modal-folder-settings/sw-media-modal-folder-settings.html.twig',
            code: template(
                '{% block sw_media_modal_folder_settings_tab_content_settings %}',
                '    <div v-if="active === \'settings\'">{% parent %}</div>',
                '{% endblock %}',
            ),
            output: template(
                '{# TODO Codemod: Block sw_media_modal_folder_settings_tab_content_settings of sw-media-modal-folder-settings is renamed to ' +
                    'sw_media_modal_folder_settings_mt_tab_content_settings in 6.8. ' +
                    'Rename this override and use activeTab instead of the active slot variable of sw-tabs. #}',
                '{% block sw_media_modal_folder_settings_tab_content_settings %}',
                '    <div v-if="active === \'settings\'">{% parent %}</div>',
                '{% endblock %}',
            ),
            errors: [todo],
        },
        {
            name: 'comments an override when the template already overrides the new block',
            filename: '/plugin/src/extension/sw-cms-detail/sw-cms-detail.html.twig',
            code: template(
                '{% block sw_cms_detail_stage_empty_stage_content %}{% endblock %}',
                '{% block sw_cms_detail_stage_empty_stade_content %}{% endblock %}',
            ),
            output: template(
                '{% block sw_cms_detail_stage_empty_stage_content %}{% endblock %}',
                '{# TODO Codemod: Block sw_cms_detail_stage_empty_stade_content of sw-cms-detail is renamed to ' +
                    'sw_cms_detail_stage_empty_stage_content in 6.8. This template already overrides ' +
                    'sw_cms_detail_stage_empty_stage_content, merge this override into it. #}',
                '{% block sw_cms_detail_stage_empty_stade_content %}{% endblock %}',
            ),
            errors: [todo],
        },
    ],
});

tester.run('twig-blocks-6-8 (removed)', removed, {
    valid: [
        {
            name: 'ignores blocks that stay',
            filename: '/plugin/src/extension/sw-settings-snippet-set-list/sw-settings-snippet-set-list.html.twig',
            code: template('{% block sw_settings_snippet_set_list_card_list %}{% endblock %}'),
        },
    ],
    invalid: [
        {
            name: 'comments a removed block with the block to move the override to',
            filename: '/plugin/src/extension/acme/acme.html.twig',
            code: template('{% block sw_settings_snippet_set_list_card_title %}{% endblock %}'),
            output: template(
                '{# TODO Codemod: Block sw_settings_snippet_set_list_card_title of sw-settings-snippet-set-list is removed in 6.8. ' +
                    'Move this override to sw_settings_snippet_set_list_card_list. #}',
                '{% block sw_settings_snippet_set_list_card_title %}{% endblock %}',
            ),
            errors: [todo],
        },
        {
            name: 'comments a removed block without replacement in an sw-block',
            filename: '/plugin/src/extension/sw-integration-list.override.vue',
            code: template('    <sw-block extends="sw_integration_list_introduction" />'),
            output: template(
                '    <!-- TODO Codemod: Block sw_integration_list_introduction of sw-integration-list is removed in 6.8 without replacement. ' +
                    'Remove this override. -->',
                '    <sw-block extends="sw_integration_list_introduction" />',
            ),
            errors: [todo],
        },
    ],
});
