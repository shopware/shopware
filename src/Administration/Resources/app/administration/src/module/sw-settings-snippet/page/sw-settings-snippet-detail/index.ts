import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-snippet-detail',
    load: () => import('./src/sw-settings-snippet-detail'),
    public: {
        blocks: [
            'sw_settings_snippet_detail',
            'sw_settings_snippet_detail_smart_bar',
            'sw_settings_snippet_detail_header',
            'sw_settings_snippet_detail_actions',
            'sw_settings_snippet_detail_actions_abort',
            'sw_settings_snippet_detail_actions_save',
            'sw_settings_snippet_detail_content',
            'sw_settings_snippet_set_detail_card',
            'sw_settings_snippet_set_detail_card_information',
            'sw_settings_snippet_set_detail_card_information_key',
            'sw_settings_snippet_set_detail_card_snippets',
            'sw_settings_snippet_set_detail_card_snippets_fields',
        ],
        computed: [
            'identifier',
            'snippetRepository',
            'snippetSetRepository',
            'snippetSetCriteria',
            'backPath',
            'invalidKeyError',
            'currentAuthor',
            'snippetStates',
        ],
        methods: [
            'createdComponent',
            'prepareContent',
            'initializeSnippet',
            'applySnippetsToDummies',
            'createSnippetDummy',
            'saveFinish',
            'onSave',
            'onChange',
            'doChange',
            'onNewKeyRedirect',
            'getCustomList',
            'checkIsSaveable',
            'getPlaceholder',
            'getSnippetState',
            'onRemoveInheritance',
            'onResetSnippet',
            'getNoPermissionsTooltip',
        ],
    },
});
