/**
 * @sw-package framework
 */

const { rule, template, tester } = require('./tester');

tester.run('no-deprecated-template-blocks (renames and removals)', rule, {
    valid: [],
    invalid: [
        {
            name: 'renames a block in its named endblock tag too',
            filename: 'test.html.twig',
            code: template(
                '{% block sw_condiiton_date_range_field_to_date %}',
                '    {% parent %}',
                '{% endblock sw_condiiton_date_range_field_to_date %}',
            ),
            output: template(
                '{% block sw_condition_date_range_field_to_date %}',
                '    {% parent %}',
                '{% endblock sw_condition_date_range_field_to_date %}',
            ),
            errors: [{ message: /Use "sw_condition_date_range_field_to_date" instead/ }],
        },
        {
            name: 'renames a whitespace-control block in its named endblock tag too',
            filename: 'test.html.twig',
            code: template(
                '{%- block sw_condiiton_date_range_field_to_date -%}{%- endblock sw_condiiton_date_range_field_to_date -%}',
            ),
            output: template(
                '{%- block sw_condition_date_range_field_to_date -%}{%- endblock sw_condition_date_range_field_to_date -%}',
            ),
            errors: [{ message: /Use "sw_condition_date_range_field_to_date" instead/ }],
        },
        {
            // ESLint merges the two ranges of the outer fix into one, so the inner block follows in its next pass
            name: 'renames the outer one of two nested blocks with named endblock tags first',
            filename: 'test.html.twig',
            code: template(
                '{% block sw_media_modal_folder_settings_thumbnail_list %}',
                '    {% block sw_media_modal_folder_settings_thumbnail_size %}{% endblock sw_media_modal_folder_settings_thumbnail_size %}',
                '{% endblock sw_media_modal_folder_settings_thumbnail_list %}',
            ),
            output: template(
                '{% block sw_media_modal_folder_settings_mt_thumbnail_list %}',
                '    {% block sw_media_modal_folder_settings_thumbnail_size %}{% endblock sw_media_modal_folder_settings_thumbnail_size %}',
                '{% endblock sw_media_modal_folder_settings_mt_thumbnail_list %}',
            ),
            errors: [
                { message: /Use "sw_media_modal_folder_settings_mt_thumbnail_list" instead/ },
                { message: /Use "sw_media_modal_folder_settings_mt_thumbnail_size" instead/ },
            ],
        },
        {
            name: 'reports a removed block with the block to move the override to',
            filename: 'test.html.twig',
            code: template('{% block sw_settings_snippet_set_list_card_title %}{% endblock %}'),
            output: null,
            errors: [{ message: /is deprecated\. Move this customization to "sw_settings_snippet_set_list_card_list"\./ }],
        },
        {
            name: 'reports a removed tab item with the computed to add the tab to',
            filename: 'test.html.twig',
            code: template('{% block sw_product_detail_content_tabs_seo %}{% endblock %}'),
            output: null,
            errors: [
                {
                    message:
                        /Move this customization to "sw_product_detail_content_mt_tabs"\.\nAdd your tab to the "productDetailTabs" computed of "sw-product-detail"\./,
                },
            ],
        },
        {
            name: 'reports an override that reads the active slot variable of sw-tabs instead of renaming it',
            filename: 'test.html.twig',
            code: template(
                '{% block sw_media_modal_folder_settings_tab_content_settings %}',
                '    <div v-if="active === \'settings\'">{% parent %}</div>',
                '{% endblock %}',
            ),
            output: null,
            errors: [
                {
                    message:
                        /The override reads the slot variable "active", which "sw_media_modal_folder_settings_mt_tab_content_settings" does not provide\.\nThe mt-tabs branch has no "active" slot variable, use "activeTab" of the component instead\./,
                },
            ],
        },
        {
            name: 'reports a native override that reads the active slot variable inside an sw-block',
            filename: 'sw-media-modal-v2.override.vue',
            code: template(
                '    <sw-block extends="sw_media_modal_v2_tab_content_upload">',
                '        <p>{{ active }}</p>',
                '    </sw-block>',
            ),
            output: null,
            errors: [{ message: /reads the slot variable "active"/ }],
        },
        {
            name: 'renames a tab content override that only mentions active in classes, members or its own slot',
            filename: 'test.html.twig',
            code: template(
                '{% block sw_media_modal_folder_settings_tab_content_settings %}',
                '    <div class="is--active" :class="{ \'is--active\': tab.active }">',
                '        <sw-tabs><template #content="{ active }"><p v-if="active" /></template></sw-tabs>',
                '    </div>',
                '{% endblock %}',
            ),
            output: template(
                '{% block sw_media_modal_folder_settings_mt_tab_content_settings %}',
                '    <div class="is--active" :class="{ \'is--active\': tab.active }">',
                '        <sw-tabs><template #content="{ active }"><p v-if="active" /></template></sw-tabs>',
                '    </div>',
                '{% endblock %}',
            ),
            errors: [{ message: /Use "sw_media_modal_folder_settings_mt_tab_content_settings" instead/ }],
        },
        {
            name: 'renames an override that reads active when its new block keeps the variable',
            filename: 'test.html.twig',
            code: template('{% block sw_media_modal_folder_settings_name_field %}<p v-if="active" />{% endblock %}'),
            output: template('{% block sw_media_modal_folder_settings_mt_name_field %}<p v-if="active" />{% endblock %}'),
            errors: [{ message: /Use "sw_media_modal_folder_settings_mt_name_field" instead/ }],
        },
        {
            name: 'reports two overrides that are renamed to the same block without renaming them',
            filename: 'test.html.twig',
            code: template(
                '{% block sw_cms_element_buy_box_config_product_variant_label %}{% endblock %}',
                '{% block sw_cms_element_buy_box_config_product_selection_label %}{% endblock %}',
            ),
            output: null,
            errors: [
                {
                    message:
                        /is renamed to "sw_cms_element_buy_box_config_mt_product_variant_label" as well, merge the overrides/,
                },
                {
                    message:
                        /is renamed to "sw_cms_element_buy_box_config_mt_product_variant_label" as well, merge the overrides/,
                },
            ],
        },
        {
            name: 'explains why a block next to its replacement is not renamed',
            filename: 'test.html.twig',
            code: template(
                '{% block sw_condition_date_range_field_to_date %}{% endblock %}',
                '{% block sw_condiiton_date_range_field_to_date %}{% endblock %}',
            ),
            output: null,
            errors: [
                {
                    message:
                        /The template already overrides "sw_condition_date_range_field_to_date", merge this override into it\./,
                },
            ],
        },
    ],
});
