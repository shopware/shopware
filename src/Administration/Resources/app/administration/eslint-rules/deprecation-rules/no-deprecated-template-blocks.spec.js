const RuleTester = require('eslint').RuleTester;
const rule = require('./no-deprecated-template-blocks');

const tester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2015,
    },
});

tester.run('no-deprecated-template-blocks', rule, {
    valid: [
        {
            name: 'allows current Twig block name',
            filename: 'test.html.twig',
            code: '<template>{% block sw_condition_date_range_field_to_date %}<div />{% endblock %}</template>',
        },
        {
            name: 'allows unrelated sw-block',
            filename: 'test.html.twig',
            code: '<template><sw-block name="custom_extension_block"><div /></sw-block></template>',
        },
        {
            name: 'allows a deprecated block kept as BC wrapper inside its replacement block',
            filename: 'sw-condition-date-range.html.twig',
            code: '<template>{% block sw_condition_date_range_field_to_date %}{% block sw_condiiton_date_range_field_to_date %}<div />{% endblock %}{% endblock %}</template>',
        },
        {
            name: 'allows a deprecated sw-block kept as BC wrapper inside its replacement sw-block',
            filename: 'test.html.twig',
            code: '<template><sw-block name="sw_cms_detail_stage_empty_stage_content"><sw-block name="sw_cms_detail_stage_empty_stade_content"><div /></sw-block></sw-block></template>',
        },
        {
            name: 'allows a deprecated whitespace-control block kept as BC wrapper inside its replacement block',
            filename: 'sw-condition-date-range.html.twig',
            code: '<template>{%- block sw_condition_date_range_field_to_date -%}{%- block sw_condiiton_date_range_field_to_date -%}<div />{%- endblock -%}{%- endblock -%}</template>',
        },
        {
            name: 'allows a block name another core template defines in a template of another component',
            filename: '/plugin/src/component/acme-select/acme-select.html.twig',
            code: '<template>{% block sw_entity_single_select_base_results_list_result_label %}<div />{% endblock %}</template>',
        },
    ],
    invalid: [
        {
            name: 'renames a block name another core template defines in a template of its component',
            filename: '/plugin/src/extension/sw-product-cross-selling-assignment/sw-product-cross-selling-assignment.html.twig',
            code: '<template>{% block sw_entity_single_select_base_results_list_result_label %}<div />{% endblock %}</template>',
            output: '<template>{% block sw_product_cross_selling_assignment_select_result_item_inner %}<div />{% endblock %}</template>',
            errors: 1,
        },
        {
            name: 'names the block to move a removed block override to',
            filename: '/plugin/src/extension/acme/acme.html.twig',
            code: '<template>{% block sw_settings_snippet_set_list_card_title %}<div />{% endblock %}</template>',
            errors: [{ message: /Move this customization to sw_settings_snippet_set_list_card_list\./ }],
        },
        {
            name: 'renames a legacy tab block and asks for a review of the active slot variable',
            filename: '/plugin/src/extension/sw-media-modal-folder-settings/sw-media-modal-folder-settings.html.twig',
            code: '<template>\n{% block sw_media_modal_folder_settings_tab_content_settings %}<div />{% endblock %}\n</template>',
            output: '<template>\n<!-- TODO Codemod: The Administration Twig block "sw_media_modal_folder_settings_tab_content_settings" is deprecated. Use "sw_media_modal_folder_settings_mt_tab_content_settings" instead. -->\n{% block sw_media_modal_folder_settings_mt_tab_content_settings %}<div />{% endblock %}\n</template>',
            settings: { 'sw-deprecation-rules': { level: 'review' } },
            errors: 1,
        },
        {
            name: 'renames deprecated Twig block',
            filename: 'test.html.twig',
            code: '<template>{% block sw_condiiton_date_range_field_to_date %}<div />{% endblock %}</template>',
            output: '<template>{% block sw_condition_date_range_field_to_date %}<div />{% endblock %}</template>',
            errors: [
                {
                    message: /Use "sw_condition_date_range_field_to_date" instead/,
                },
            ],
        },
        {
            name: 'renames a deprecated whitespace-control Twig block',
            filename: 'test.html.twig',
            code: '<template>{%- block sw_condiiton_date_range_field_to_date -%}<div />{%- endblock -%}</template>',
            output: '<template>{%- block sw_condition_date_range_field_to_date -%}<div />{%- endblock -%}</template>',
            errors: [
                {
                    message: /Use "sw_condition_date_range_field_to_date" instead/,
                },
            ],
        },
        {
            name: 'reports deprecated Twig block without fix [disableFix]',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: '<template>{% block sw_condiiton_date_range_field_to_date %}<div />{% endblock %}</template>',
            output: null,
            errors: [
                {
                    message: /Use "sw_condition_date_range_field_to_date" instead/,
                },
            ],
        },
        {
            name: 'renames deprecated sw-block name',
            filename: 'test.html.twig',
            code: '<template><sw-block name="sw_cms_detail_stage_empty_stade_content"><div /></sw-block></template>',
            output: '<template><sw-block name="sw_cms_detail_stage_empty_stage_content"><div /></sw-block></template>',
            errors: [
                {
                    message: /Use "sw_cms_detail_stage_empty_stage_content" instead/,
                },
            ],
        },
        {
            name: 'renames deprecated single-quoted sw-block name',
            filename: 'test.html.twig',
            code: "<template><sw-block name='sw_cms_detail_stage_empty_stade_content'><div /></sw-block></template>",
            output: "<template><sw-block name='sw_cms_detail_stage_empty_stage_content'><div /></sw-block></template>",
            errors: [
                {
                    message: /Use "sw_cms_detail_stage_empty_stage_content" instead/,
                },
            ],
        },
        {
            name: 'renames deprecated single-quoted sw-block extends',
            filename: 'test.html.twig',
            code: "<template><sw-block extends='sw_cms_detail_stage_empty_stade_content'><div /></sw-block></template>",
            output: "<template><sw-block extends='sw_cms_detail_stage_empty_stage_content'><div /></sw-block></template>",
            errors: [
                {
                    message: /Use "sw_cms_detail_stage_empty_stage_content" instead/,
                },
            ],
        },
        {
            name: 'renames deprecated sw-block name with spaced attribute assignment',
            filename: 'test.html.twig',
            code: '<template><sw-block name = "sw_cms_detail_stage_empty_stade_content"><div /></sw-block></template>',
            output: '<template><sw-block name = "sw_cms_detail_stage_empty_stage_content"><div /></sw-block></template>',
            errors: [
                {
                    message: /Use "sw_cms_detail_stage_empty_stage_content" instead/,
                },
            ],
        },
        {
            name: 'renames deprecated sw-block name and extends on the same tag',
            filename: 'test.html.twig',
            code: '<template><sw-block name="sw_cms_detail_stage_empty_stade_content" extends="sw_condiiton_date_range_field_to_date"><div /></sw-block></template>',
            output: '<template><sw-block name="sw_cms_detail_stage_empty_stage_content" extends="sw_condition_date_range_field_to_date"><div /></sw-block></template>',
            errors: [
                {
                    message: /Use "sw_cms_detail_stage_empty_stage_content" instead/,
                },
                {
                    message: /Use "sw_condition_date_range_field_to_date" instead/,
                },
            ],
        },
        {
            name: 'reports removed block without fixer',
            filename: 'test.html.twig',
            code: '<template>{% block sw_import_export_language_switch %}<div />{% endblock %}</template>',
            output: null,
            errors: [
                {
                    message: /Remove or move this customization/,
                },
            ],
        },
        {
            name: 'reports a deprecated block next to its replacement block without fixing it',
            filename: 'test.html.twig',
            code: '<template>{% block sw_condition_date_range_field_to_date %}{% endblock %}{% block sw_condiiton_date_range_field_to_date %}{% endblock %}</template>',
            output: null,
            errors: [
                {
                    message: /Use "sw_condition_date_range_field_to_date" instead/,
                },
            ],
        },
        {
            name: 'reports a deprecated sw-block next to its replacement sw-block without fixing it',
            filename: 'test.html.twig',
            code: '<template><sw-block name="sw_cms_detail_stage_empty_stage_content"></sw-block><sw-block name="sw_cms_detail_stage_empty_stade_content"></sw-block></template>',
            output: null,
            errors: [
                {
                    message: /Use "sw_cms_detail_stage_empty_stage_content" instead/,
                },
            ],
        },
        {
            name: 'reports a deprecated sw-block after a self-closing replacement sw-block',
            filename: 'test.html.twig',
            code: '<template><sw-block name="outer"><sw-block name="sw_cms_detail_stage_empty_stage_content" /><sw-block name="sw_cms_detail_stage_empty_stade_content"><div /></sw-block></sw-block></template>',
            output: null,
            errors: [
                {
                    message: /Use "sw_cms_detail_stage_empty_stage_content" instead/,
                },
            ],
        },
    ],
});
