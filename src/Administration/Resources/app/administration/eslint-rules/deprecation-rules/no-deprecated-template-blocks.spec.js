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
    ],
});
