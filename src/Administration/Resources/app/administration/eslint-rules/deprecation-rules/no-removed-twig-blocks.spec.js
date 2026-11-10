/**
 * @sw-package framework
 */

const fs = require('fs');
const os = require('os');
const path = require('path');
const { RuleTester } = require('eslint');
const rule = require('./no-removed-twig-blocks');
const { groups, shared } = require('./no-removed-twig-blocks.data');

const ruleTester = new RuleTester({
    languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 },
});

const template = (body) => `<template>\n${body}\n</template>`;
const todo = (message) => `{# TODO Codemod(6.8, twig-block-removals): ${message} #}`;
const marked = (message, block, indentation = '') => template(`${indentation}${todo(message)}\n${indentation}${block}`);

const PLUGIN = '/plugin/src/extension';
const SHARED_NAME = 'Block name is used by several core components. Check which one you override.';
const NAME_FIELD = 'Block sw_media_modal_folder_settings_name_field is removed with the legacy sw-tabs branch. Override sw_media_modal_folder_settings_mt_name_field instead.';

// Templates without a core component name take the override target from the script next to them.
const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'sw-removed-twig-blocks-'));
const flowIndexOverride = path.join(directory, 'flow/acme-flow.html.twig');
fs.mkdirSync(path.dirname(flowIndexOverride), { recursive: true });
fs.writeFileSync(path.join(directory, 'flow/index.js'), "Component.override('sw-flow-index', { template });");
const ownComponent = path.join(directory, 'sw-cms-el-config-acme/sw-cms-el-config-acme.html.twig');
fs.mkdirSync(path.dirname(ownComponent), { recursive: true });
fs.writeFileSync(path.join(directory, 'sw-cms-el-config-acme/index.js'), "Shopware.Component.register('sw-cms-el-config-acme', { template });");
const lazyOwnComponent = path.join(directory, 'acme-element/config/sw-cms-el-config-acme-slider.html.twig');
fs.mkdirSync(path.dirname(lazyOwnComponent), { recursive: true });
fs.writeFileSync(path.join(directory, 'acme-element/config/index.js'), 'export default { template };');
fs.writeFileSync(path.join(directory, 'acme-element/index.js'), "Shopware.Component.register('sw-cms-el-config-acme-slider', () => import('./config'));");

describe('no-removed-twig-blocks', () => {
    afterAll(() => {
        fs.rmSync(directory, { recursive: true, force: true });
    });

    ruleTester.run('no-removed-twig-blocks', rule, {
        valid: [
            {
                name: 'ignores .vue templates, which have no blocks',
                filename: `${PLUGIN}/acme/acme.vue`,
                code: template('{% block sw_media_modal_folder_settings_name_field %}{% endblock %}'),
            },
            {
                name: 'ignores blocks that stay',
                filename: `${PLUGIN}/acme/acme.html.twig`,
                code: template('{% block sw_media_modal_folder_settings_mt_name_field %}{% endblock %}\n{% block acme_block %}{% endblock %}'),
            },
            {
                name: 'ignores blocks inside Twig comments',
                filename: `${PLUGIN}/acme/acme.html.twig`,
                code: template('{#\n    {% block sw_media_modal_folder_settings_name_field %}{% endblock %}\n#}'),
            },
            {
                name: 'ignores templates of components the extension registers itself, also with copied core block names',
                filename: ownComponent,
                code: template('{% block sw_cms_el_text_config_tab_options %}{% endblock %}'),
            },
            {
                name: 'ignores templates of components the extension registers lazily one folder up',
                filename: lazyOwnComponent,
                code: template('{% block sw_cms_element_product_slider_config_tab_content %}{% endblock %}'),
            },
            {
                name: 'ignores a shared name in an override of a core component that keeps it',
                filename: `${PLUGIN}/sw-entity-multi-select/sw-entity-multi-select.html.twig`,
                code: template('{% block sw_entity_multi_select_base_results_list_result_label %}{% endblock %}'),
            },
        ],
        invalid: [
            {
                name: 'names the _mt_ block of a legacy sw-tabs branch with the indentation of the block',
                filename: `${PLUGIN}/acme/acme.html.twig`,
                code: template('    {% block sw_media_modal_folder_settings_name_field %}{% endblock %}'),
                output: marked(NAME_FIELD, '{% block sw_media_modal_folder_settings_name_field %}{% endblock %}', '    '),
                errors: [{ message: NAME_FIELD, line: 2 }],
            },
            {
                name: 'reports without a fix when the TODO is already there',
                filename: `${PLUGIN}/acme/acme.html.twig`,
                code: marked(NAME_FIELD, '{% block sw_media_modal_folder_settings_name_field %}{% endblock %}'),
                output: null,
                errors: [{ message: NAME_FIELD }],
            },
            {
                name: 'marks nested removed blocks one by one, also with extra whitespace',
                filename: `${PLUGIN}/acme/acme.html.twig`,
                code: template('{%  block sw_settings_snippet_set_list_card_title %}\n    {% block sw_settings_snippet_set_list_card_list_container %}{% endblock %}\n{% endblock %}'),
                output: template([
                    todo('Block sw_settings_snippet_set_list_card_title is removed without replacement. Extend sw_settings_snippet_set_list_card_list_grid instead.'),
                    '{%  block sw_settings_snippet_set_list_card_title %}',
                    `    ${todo('Block sw_settings_snippet_set_list_card_list_container is removed without replacement. Extend sw_settings_snippet_set_list_card_list_grid instead.')}`,
                    '    {% block sw_settings_snippet_set_list_card_list_container %}{% endblock %}',
                    '{% endblock %}',
                ].join('\n')),
                errors: 2,
            },
            {
                name: 'names the tabs computed for a legacy tab item',
                filename: `${PLUGIN}/sw-product-detail/sw-product-detail.html.twig`,
                code: template('{% block sw_product_detail_content_tabs_additional %}{% endblock %}'),
                output: marked(
                    'Block sw_product_detail_content_tabs_additional is removed with the legacy sw-tabs branch. Add the tab to the productDetailTabs computed instead.',
                    '{% block sw_product_detail_content_tabs_additional %}{% endblock %}',
                ),
                errors: 1,
            },
            {
                name: 'says when a block is removed without replacement',
                filename: `${PLUGIN}/acme/acme.html.twig`,
                code: template('{% block sw_integration_list_introduction %}{% endblock %}'),
                output: marked('Block sw_integration_list_introduction is removed without replacement.', '{% block sw_integration_list_introduction %}{% endblock %}'),
                errors: 1,
            },
            {
                name: 'names the component of a replacement in another component',
                filename: `${PLUGIN}/acme/acme.html.twig`,
                code: template('{% block sw_product_packaging_form_width_field %}{% endblock %}'),
                output: marked(
                    'Block sw_product_packaging_form_width_field is removed. Override sw_product_measurement_form_width_field in sw-product-measurement-form instead.',
                    '{% block sw_product_packaging_form_width_field %}{% endblock %}',
                ),
                errors: 1,
            },
            {
                name: 'writes a hint of its own',
                filename: `${PLUGIN}/acme/acme.html.twig`,
                code: template('{% block sw_settings_user_list_delete_modal_input__confirm_password %}{% endblock %}'),
                output: marked(
                    'Block sw_settings_user_list_delete_modal_input__confirm_password is removed. Extend the sw-verify-user-modal component instead.',
                    '{% block sw_settings_user_list_delete_modal_input__confirm_password %}{% endblock %}',
                ),
                errors: 1,
            },
            {
                name: 'names a removed component',
                filename: `${PLUGIN}/acme/acme.html.twig`,
                code: template('{% block sw_order_user_card_avatar %}{% endblock %}'),
                output: marked(
                    'Block sw_order_user_card_avatar is removed with the sw-order-user-card component without replacement.',
                    '{% block sw_order_user_card_avatar %}{% endblock %}',
                ),
                errors: 1,
            },
            {
                name: 'names the component of a shared name when the file name is the core component',
                filename: `${PLUGIN}/sw-cms-el-config-image-gallery/sw-cms-el-config-image-gallery.html.twig`,
                code: template('{% block sw_cms_element_image_gallery_config_settings_min_height %}{% endblock %}'),
                output: marked(
                    'Block sw_cms_element_image_gallery_config_settings_min_height of sw-cms-el-config-image-gallery is removed with the legacy sw-tabs branch. Override sw_cms_element_image_gallery_config_mt_settings_min_height instead.',
                    '{% block sw_cms_element_image_gallery_config_settings_min_height %}{% endblock %}',
                ),
                errors: 1,
            },
            {
                name: 'picks the entry of the core component the script next to the template overrides',
                filename: flowIndexOverride,
                code: template('{% block sw_flow_tabs_header_extension %}{% endblock %}'),
                output: marked(
                    'Block sw_flow_tabs_header_extension of sw-flow-index is removed with the legacy sw-tabs branch. Add the tab to the flowTabs computed instead.',
                    '{% block sw_flow_tabs_header_extension %}{% endblock %}',
                ),
                errors: 1,
            },
            {
                name: 'reports a shared name without a fix when the override target is unknown',
                filename: `${PLUGIN}/acme/acme.html.twig`,
                code: template('{% block sw_flow_tabs_header_extension %}{% endblock %}'),
                output: null,
                errors: [{ message: SHARED_NAME }],
            },
            {
                name: 'marks blocks that a core component inherits from the one that removes them',
                filename: `${PLUGIN}/sw-bulk-edit-custom-fields/sw-bulk-edit-custom-fields.html.twig`,
                code: template('{% block sw_custom_field_set_renderer_card_form_renderer %}{% endblock %}'),
                output: marked(
                    'Block sw_custom_field_set_renderer_card_form_renderer is removed with the legacy sw-tabs branch. Override sw_custom_field_set_renderer_card_mt_tabs_form_renderer instead.',
                    '{% block sw_custom_field_set_renderer_card_form_renderer %}{% endblock %}',
                ),
                errors: 1,
            },
        ],
    });

    describe('no-removed-twig-blocks data', () => {
        const names = groups.flatMap((group) => Object.keys(group.blocks));

        it('describes every replacement in a known form', () => {
            groups.forEach((group) => {
                expect(['tabs', 'removed', 'component']).toContain(group.reason);
                expect(group.component).toMatch(/^sw-[a-z0-9-]+$/);

                Object.values(group.blocks).forEach((replacement) => {
                    if (replacement === null || typeof replacement === 'string') {
                        return;
                    }

                    expect(['use', 'extend', 'tab', 'hint'].filter((key) => typeof replacement[key] === 'string')).toHaveLength(1);
                });
            });
        });

        it('lists only shared names that a group removes', () => {
            Object.keys(shared).forEach((name) => {
                expect(names).toContain(name);
            });
        });

        it('marks names that several groups remove as shared', () => {
            names.filter((name, index) => names.indexOf(name) !== index).forEach((name) => {
                expect(Object.keys(shared)).toContain(name);
            });
        });
    });
});
