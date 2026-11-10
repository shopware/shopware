/**
 * @sw-package framework
 */

const fs = require('fs');
const os = require('os');
const path = require('path');
const { ESLint } = require('eslint');
const twigVue = require('../../../twigVuePlugin/lib/index');
const { rule } = require('./tester');

describe('no-deprecated-template-blocks with the twig-vue processor', () => {
    // ESLint lints only files below its working directory
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'no-deprecated-template-blocks-'));

    afterAll(() => {
        fs.rmSync(root, { recursive: true, force: true });
    });

    async function fixAtPartialLevel(code, filePath) {
        const eslint = new ESLint({
            cwd: root,
            fix: true,
            overrideConfigFile: true,
            overrideConfig: [
                {
                    files: ['**/*.html.twig'],
                    languageOptions: { parser: require('vue-eslint-parser') },
                    plugins: {
                        twigVue,
                        'sw-deprecation-rules': { rules: { 'no-deprecated-template-blocks': rule } },
                    },
                    processor: 'twigVue/twig-vue',
                    settings: { 'sw-deprecation-rules': { level: 'partial' } },
                    rules: { 'sw-deprecation-rules/no-deprecated-template-blocks': 'error' },
                },
            ],
        });
        const [result] = await eslint.lintText(code, { filePath });

        return result.output ?? code;
    }

    it('renames what it can, comments the rest once and changes nothing on a second run', async () => {
        const filePath = path.join(root, 'sw-media-modal-folder-settings/sw-media-modal-folder-settings.html.twig');
        const code = [
            '{% block sw_media_modal_folder_settings_tab_content_settings %}',
            '    <div v-if="active === \'settings\'">{% parent %}</div>',
            '{% endblock %}',
            '',
            '{% block sw_media_modal_folder_settings_name_field %}',
            '    {% block sw_media_modal_folder_settings_thumbnail_size %}{% endblock sw_media_modal_folder_settings_thumbnail_size %}',
            '{% endblock sw_media_modal_folder_settings_name_field %}',
            '',
            '{% block sw_media_modal_folder_settings_mt_default_folder %}{% endblock %}',
            '{% block sw_media_modal_folder_settings_default_folder %}{% endblock %}',
            '',
            '{% block sw_media_modal_folder_settings_tab_item_settings %}{% endblock %}',
            '',
        ].join('\n');

        const firstRun = await fixAtPartialLevel(code, filePath);
        const secondRun = await fixAtPartialLevel(firstRun, filePath);

        expect(firstRun).toBe(
            [
                '<!-- TODO Codemod: The Administration Twig block "sw_media_modal_folder_settings_tab_content_settings" is deprecated. ' +
                    'Use "sw_media_modal_folder_settings_mt_tab_content_settings" instead. ' +
                    'The override reads the slot variable "active", which "sw_media_modal_folder_settings_mt_tab_content_settings" does not provide. ' +
                    'The mt-tabs branch has no "active" slot variable, use "activeTab" of the component instead. -->',
                '{% block sw_media_modal_folder_settings_tab_content_settings %}',
                '    <div v-if="active === \'settings\'">{% parent %}</div>',
                '{% endblock %}',
                '',
                '{% block sw_media_modal_folder_settings_mt_name_field %}',
                '    {% block sw_media_modal_folder_settings_mt_thumbnail_size %}{% endblock sw_media_modal_folder_settings_mt_thumbnail_size %}',
                '{% endblock sw_media_modal_folder_settings_mt_name_field %}',
                '',
                '{% block sw_media_modal_folder_settings_mt_default_folder %}{% endblock %}',
                '<!-- TODO Codemod: The Administration Twig block "sw_media_modal_folder_settings_default_folder" is deprecated. ' +
                    'Use "sw_media_modal_folder_settings_mt_default_folder" instead. ' +
                    'The template already overrides "sw_media_modal_folder_settings_mt_default_folder", merge this override into it. -->',
                '{% block sw_media_modal_folder_settings_default_folder %}{% endblock %}',
                '',
                '<!-- TODO Codemod: The Administration Twig block "sw_media_modal_folder_settings_tab_item_settings" is deprecated. ' +
                    'Move this customization to "sw_media_modal_folder_settings_mt_tabs". ' +
                    'Add your tab to the "tabs" computed of "sw-media-modal-folder-settings". -->',
                '{% block sw_media_modal_folder_settings_tab_item_settings %}{% endblock %}',
                '',
            ].join('\n'),
        );
        expect(secondRun).toBe(firstRun);
    });
});
