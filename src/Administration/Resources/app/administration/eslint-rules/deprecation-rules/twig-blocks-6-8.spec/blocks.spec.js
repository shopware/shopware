/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const { removed, renamed, tester } = require('./tester');

tester.run('twig-blocks-6-8 renamed', renamed, {
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
            name: 'allows a removed block, which the removed rule reports',
            filename: 'test.html.twig',
            code: '<template>{% block sw_import_export_language_switch %}<div />{% endblock %}</template>',
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
    ],
    invalid: [
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
            name: 'reports with the context of the entry',
            filename: 'test.html.twig',
            code: '<template>{% block sw_condiiton_date_range_field_to_date %}<div />{% endblock %}</template>',
            output: '<template>{% block sw_condition_date_range_field_to_date %}<div />{% endblock %}</template>',
            errors: [
                {
                    message: /instead\.\n.+\nRemoved in Shopware 6\.8\.0\.\nReferences:\nupgrade: UPGRADE-6\.7\.md#administration\nupgrade: UPGRADE-6\.8\.md#/,
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

tester.run('twig-blocks-6-8 removed', removed, {
    valid: [
        {
            name: 'allows a renamed block, which the renamed rule fixes',
            filename: 'test.html.twig',
            code: '<template>{% block sw_condiiton_date_range_field_to_date %}<div />{% endblock %}</template>',
        },
    ],
    invalid: [
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
            name: 'reports a removed block that an sw-block extends',
            filename: 'test.html.twig',
            code: '<template><sw-block extends="sw_import_export_language_switch"><div /></sw-block></template>',
            output: null,
            errors: [
                {
                    message: /"sw_import_export_language_switch" is deprecated\. Remove or move this customization/,
                },
            ],
        },
    ],
});

// A CLI `--rule` reaches every linted file, so the rules must tolerate scripts without a template.
new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('twig-blocks-6-8 on a script', renamed, {
    valid: [
        {
            name: 'reports nothing for a block tag in a script string',
            filename: 'index.js',
            code: "export default { template: '{% block sw_condiiton_date_range_field_to_date %}{% endblock %}' };",
        },
    ],
    invalid: [],
});
