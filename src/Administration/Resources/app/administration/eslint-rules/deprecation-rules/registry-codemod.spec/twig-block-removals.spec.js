/**
 * @sw-package framework
 */

const fs = require('fs');
const os = require('os');
const path = require('path');
const { runCodemod, todo } = require('./tester');

const template = (body) => `<template>\n${body}\n</template>`;
const marked = (message, block, indentation = '') => template(`${indentation}${todo(message)}\n${indentation}${block}`);
const removed = (block, hint) => `The Administration Twig block "${block}" is deprecated. ${hint}`;

const PLUGIN = '/plugin/src/extension';
const SHARED_NAME = removed(
    'sw_flow_tabs_header_extension',
    'Several core components use this block name. Check which one the template overrides.',
);
const NAME_FIELD = removed(
    'sw_media_modal_folder_settings_name_field',
    'It is removed with the legacy sw-tabs branch. Override sw_media_modal_folder_settings_mt_name_field instead.',
);

/** What the codemod comment names instead of a removed block, as `[what, block, message]`. */
const MESSAGES = [
    [
        'the computed that takes a legacy tab item',
        'sw_product_detail_content_tabs_additional',
        removed('sw_product_detail_content_tabs_additional', 'It is removed with the legacy sw-tabs branch. Add the tab to the productDetailTabs computed instead.'),
    ],
    [
        'no replacement',
        'sw_integration_list_introduction',
        removed('sw_integration_list_introduction', 'It is removed without replacement. Remove or move this customization.'),
    ],
    [
        'a replacement in another component',
        'sw_product_packaging_form_width_field',
        removed('sw_product_packaging_form_width_field', 'It is removed. Override sw_product_measurement_form_width_field in sw-product-measurement-form instead.'),
    ],
    [
        'a hint of its own',
        'sw_settings_user_list_delete_modal_input__confirm_password',
        removed('sw_settings_user_list_delete_modal_input__confirm_password', 'It is removed. Extend the sw-verify-user-modal component instead.'),
    ],
    [
        'the removed component',
        'sw_order_user_card_avatar',
        removed('sw_order_user_card_avatar', 'It is removed with the sw-order-user-card component without replacement. Remove or move this customization.'),
    ],
];

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
const ownComponentInNamedScript = path.join(directory, 'acme-copy/acme-copy.html.twig');
fs.mkdirSync(path.dirname(ownComponentInNamedScript), { recursive: true });
fs.writeFileSync(path.join(directory, 'acme-copy/index.js'), "import './acme-copy';");
fs.writeFileSync(path.join(directory, 'acme-copy/acme-copy.js'), "Shopware.Component.register('acme-copy', { template });");

describe('twig-block-removals', () => {
    afterAll(() => {
        fs.rmSync(directory, { recursive: true, force: true });
    });

    runCodemod('twig-block-removals', {
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
                name: 'ignores templates of components the extension registers in a script named after them',
                filename: ownComponentInNamedScript,
                code: template('{% block sw_integration_list_introduction %}{% endblock %}'),
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
                    todo(removed('sw_settings_snippet_set_list_card_title', 'It is removed without replacement. Extend sw_settings_snippet_set_list_card_list_grid instead.')),
                    '{%  block sw_settings_snippet_set_list_card_title %}',
                    `    ${todo(removed('sw_settings_snippet_set_list_card_list_container', 'It is removed without replacement. Extend sw_settings_snippet_set_list_card_list_grid instead.'))}`,
                    '    {% block sw_settings_snippet_set_list_card_list_container %}{% endblock %}',
                    '{% endblock %}',
                ].join('\n')),
                errors: 2,
            },
            ...MESSAGES.map(([what, block, message]) => ({
                name: `names ${what}`,
                filename: `${PLUGIN}/acme/acme.html.twig`,
                code: template(`{% block ${block} %}{% endblock %}`),
                output: marked(message, `{% block ${block} %}{% endblock %}`),
                errors: [{ message }],
            })),
            {
                name: 'names the component of a shared name when the file name is the core component',
                filename: `${PLUGIN}/sw-cms-el-config-image-gallery/sw-cms-el-config-image-gallery.html.twig`,
                code: template('{% block sw_cms_element_image_gallery_config_settings_min_height %}{% endblock %}'),
                output: marked(
                    removed(
                        'sw_cms_element_image_gallery_config_settings_min_height',
                        'It is removed from sw-cms-el-config-image-gallery with the legacy sw-tabs branch. Override sw_cms_element_image_gallery_config_mt_settings_min_height instead.',
                    ),
                    '{% block sw_cms_element_image_gallery_config_settings_min_height %}{% endblock %}',
                ),
                errors: 1,
            },
            {
                name: 'picks the entry of the core component the script next to the template overrides',
                filename: flowIndexOverride,
                code: template('{% block sw_flow_tabs_header_extension %}{% endblock %}'),
                output: marked(
                    removed(
                        'sw_flow_tabs_header_extension',
                        'It is removed from sw-flow-index with the legacy sw-tabs branch. Add the tab to the flowTabs computed instead.',
                    ),
                    '{% block sw_flow_tabs_header_extension %}{% endblock %}',
                ),
                errors: 1,
            },
            {
                name: 'marks a shared name when the override target is unknown',
                filename: `${PLUGIN}/acme/acme.html.twig`,
                code: template('{% block sw_flow_tabs_header_extension %}{% endblock %}'),
                output: marked(SHARED_NAME, '{% block sw_flow_tabs_header_extension %}{% endblock %}'),
                errors: [{ message: SHARED_NAME }],
            },
            {
                name: 'marks blocks that a core component inherits from the one that removes them',
                filename: `${PLUGIN}/sw-bulk-edit-custom-fields/sw-bulk-edit-custom-fields.html.twig`,
                code: template('{% block sw_custom_field_set_renderer_card_form_renderer %}{% endblock %}'),
                output: marked(
                    removed(
                        'sw_custom_field_set_renderer_card_form_renderer',
                        'It is removed with the legacy sw-tabs branch. Override sw_custom_field_set_renderer_card_mt_tabs_form_renderer instead.',
                    ),
                    '{% block sw_custom_field_set_renderer_card_form_renderer %}{% endblock %}',
                ),
                errors: 1,
            },
        ],
    });
});
