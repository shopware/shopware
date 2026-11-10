/**
 * @sw-package framework
 */

const fs = require('fs');
const os = require('os');
const path = require('path');
const { rule, template, tester } = require('./tester');

describe('no-deprecated-template-blocks (component scope)', () => {
    // `fileComponents` reads the index next to a template, so these cases need real files.
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'no-deprecated-template-blocks-'));

    function writeFile(relativePath, content) {
        const file = path.join(root, relativePath);
        fs.mkdirSync(path.dirname(file), { recursive: true });
        fs.writeFileSync(file, content);
    }

    writeFile(
        'product-box-override/index.js',
        "import template from './custom-template.html.twig';\nShopware.Component.override('sw-cms-el-config-product-box', { template });\n",
    );
    writeFile(
        'own-component/index.ts',
        "import template from './sw-cms-el-config-buy-box.html.twig';\nShopware.Component.register('swag-own', { template });\n",
    );

    afterAll(() => {
        fs.rmSync(root, { recursive: true, force: true });
    });

    const SHARED_RESULT_LABEL = 'sw_entity_single_select_base_results_list_result_label';

    tester.run('scoped steps', rule, {
        valid: [
            {
                name: 'ignores a shared block name in a template of another component',
                filename: path.join(root, 'acme-select/acme-select.html.twig'),
                code: template(`{% block ${SHARED_RESULT_LABEL} %}{% endblock %}`),
            },
            {
                name: 'ignores a shared block name in the template of a component of its own',
                filename: path.join(root, 'own-component/sw-cms-el-config-buy-box.html.twig'),
                code: template(`{% block ${SHARED_RESULT_LABEL} %}{% endblock %}`),
            },
            {
                name: 'ignores the mail template preview footer blocks that sw-mail-template-preview-modal still defines',
                filename: path.join(root, 'sw-mail-template-preview-modal/sw-mail-template-preview-modal.html.twig'),
                code: template('{% block sw_mail_template_detail_preview_modal_footer %}{% endblock %}'),
            },
        ],
        invalid: [
            {
                name: 'renames a shared block name in a template named after its component',
                filename: path.join(root, 'sw-cms-el-config-buy-box/sw-cms-el-config-buy-box.html.twig'),
                code: template(`{% block ${SHARED_RESULT_LABEL} %}{% endblock %}`),
                output: template('{% block sw_cms_element_buy_box_config_mt_product_select_result_label %}{% endblock %}'),
                errors: [
                    { message: /"sw_entity_single_select_base_results_list_result_label" of "sw-cms-el-config-buy-box"/ },
                ],
            },
            {
                name: 'renames a shared block name per component',
                filename: path.join(
                    root,
                    'sw-product-cross-selling-assignment/sw-product-cross-selling-assignment.html.twig',
                ),
                code: template(`{% block ${SHARED_RESULT_LABEL} %}{% endblock %}`),
                output: template('{% block sw_product_cross_selling_assignment_select_result_item_inner %}{% endblock %}'),
                errors: [{ message: /Use "sw_product_cross_selling_assignment_select_result_item_inner" instead/ }],
            },
            {
                name: 'renames a shared block name in a template of a component that extends the listed one',
                filename: path.join(root, 'sw-bulk-edit-custom-fields/sw-bulk-edit-custom-fields.html.twig'),
                code: template('{% block sw_custom_field_set_renderer_card_form_renderer %}{% endblock %}'),
                output: template('{% block sw_custom_field_set_renderer_card_mt_tabs_form_renderer %}{% endblock %}'),
                errors: [{ message: /of "sw-custom-field-set-renderer" is deprecated/ }],
            },
            {
                name: 'renames the block an sw-block of a native override extends',
                filename: path.join(root, 'plugin/sw-product-cross-selling-assignment.override.vue'),
                code: template(`    <sw-block extends="${SHARED_RESULT_LABEL}" />`),
                output: template('    <sw-block extends="sw_product_cross_selling_assignment_select_result_item_inner" />'),
                errors: [{ message: /Use "sw_product_cross_selling_assignment_select_result_item_inner" instead/ }],
            },
            {
                name: 'renames a shared block name in a template whose sibling index overrides the component',
                filename: path.join(root, 'product-box-override/custom-template.html.twig'),
                code: template(`{% block ${SHARED_RESULT_LABEL} %}{% endblock %}`),
                output: template(
                    '{% block sw_cms_element_product_box_config_product_select_result_item_inner %}{% endblock %}',
                ),
                errors: [{ message: /Use "sw_cms_element_product_box_config_product_select_result_item_inner" instead/ }],
            },
            {
                name: 'renames a block without component scope in any template',
                filename: path.join(root, 'own-component/sw-cms-el-config-buy-box.html.twig'),
                code: template('{% block sw_cms_element_buy_box_config_options %}{% endblock %}'),
                output: template('{% block sw_cms_element_buy_box_config_mt_options %}{% endblock %}'),
                errors: [{ message: /Use "sw_cms_element_buy_box_config_mt_options" instead/ }],
            },
            {
                name: 'reports a scoped removed block with the hint of its step',
                filename: path.join(root, 'sw-mail-template-create/sw-mail-template-create.html.twig'),
                code: template('{% block sw_mail_template_detail_preview_modal_footer %}{% endblock %}'),
                output: null,
                errors: [
                    {
                        message:
                            /of "sw-mail-template-detail" is deprecated\. Remove or move this customization\.\nOverride the block of the same name in "sw-mail-template-preview-modal" instead\./,
                    },
                ],
            },
        ],
    });
});
