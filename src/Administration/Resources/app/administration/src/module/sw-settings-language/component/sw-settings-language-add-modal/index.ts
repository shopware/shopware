import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-language-add-modal',
    load: () => import('./src/sw-settings-language-add-modal'),
    public: {
        emits: [
            'close',
            'language-added',
        ],
        blocks: [
            'sw_settings_language_add_modal',
            'sw_settings_language_add_modal_select',
            'sw_settings_language_add_modal_selection_info',
            'sw_settings_language_add_modal_hint',
            'sw_settings_language_add_modal_footer',
            'sw_settings_language_add_modal_footer_create_custom',
            'sw_settings_language_add_modal_footer_actions',
            'sw_settings_language_add_modal_footer_cancel',
            'sw_settings_language_add_modal_footer_add',
        ],
        computed: [
            'languageRepository',
            'languageOptions',
            'selectedTranslation',
            'translationsSufficient',
            'translationsHintTextKey',
        ],
        methods: [
            'createdComponent',
            'loadExistingLanguageLocales',
            'onAddLanguage',
            'onClose',
        ],
    },
});
