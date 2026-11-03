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
    name: 'sw-settings-language-detail',
    load: () => import('./src/sw-settings-language-detail'),
    public: {
        props: [
            'languageId',
        ],
        blocks: [
            'sw_settings_language_detail',
            'sw_settings_language_detail_header',
            'sw_settings_language_detail_actions',
            'sw_settings_language_detail_actions_abort',
            'sw_settings_language_detail_actions_save',
            'sw_settings_language_detail_content',
            'sw_settings_language_detail_created_banner',
            'sw_settings_language_detail_content_language_info',
            'sw_settings_language_detail_content_card',
            'sw_settings_language_detail_content_field_name',
            'sw_settings_language_detail_content_field_iso_code',
            'sw_settings_language_detail_content_field_localeId',
            'sw_settings_language_detail_content_field_parentId',
            'sw_settings_language_detail_content_alert_change_parent',
            'sw_settings_language_detail_cards_row',
            'sw_settings_language_detail_sales_channels',
            'sw_settings_language_detail_sales_channels_assigned',
            'sw_settings_language_detail_sales_channels_empty',
            'sw_settings_language_detail_snippet_updates',
            'sw_settings_language_detail_custom_field_sets',
        ],
        computed: [
            'identifier',
            'languageRepository',
            'isIsoCodeRequired',
            'languageHasName',
            'isNewLanguage',
            'usedLocaleCriteria',
            'allowSave',
            'tooltipSave',
            'tooltipCancel',
            'parentLanguageCriteria',
            'isSystemDefaultLanguageId',
            'inheritanceTooltipText',
            'showCustomFields',
            'assignedSalesChannels',
            'visibleSalesChannels',
            'snippetUpdateState',
            'snippetUpdatesLabel',
            'showSnippetUpdateButton',
            'snippetUpdateButtonLabel',
            'showSnippetAutoUpdate',
            'salesChannelsEmptyHint',
            'salesChannelsCardTitle',
        ],
        methods: [
            'createdComponent',
            'loadEntityData',
            'loadSnippetMetadata',
            'onUpdateSnippets',
            'loadCustomFieldSets',
            'checkTranslationCodeInheritance',
            'setParentTranslationCodeId',
            'onInputLanguage',
            'isLocaleAlreadyUsed',
            'onSave',
            'onCancel',
            'onOpenSalesChannelSettings',
            'invalidateLanguageCaches',
        ],
    },
});
