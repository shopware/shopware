import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-snippet-field',
    load: () => import('./src/sw-snippet-field'),
    public: {
        props: [
            'snippet',
            'fieldType',
        ],
        blocks: [
            'sw_snippet_field',
        ],
        computed: [
            'snippetSetRepository',
            'languageRepository',
            'languageCriteria',
            'textField',
            'textareaField',
        ],
        methods: [
            'createdComponent',
            'updatePlaceholderValueToSnippetTranslation',
            'getTranslationByLocale',
            'getSystemDefaultLocale',
            'openEditModal',
            'closeEditModal',
            'onSave',
        ],
    },
});
