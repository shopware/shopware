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
    name: 'sw-language-info',
    load: () => import('./src/sw-language-info'),
    public: {
        props: [
            'entityDescription',
            'isNewEntity',
            'changeLanguageOnParentClick',
        ],
        blocks: [
            'sw_language_info',
            'sw_language_info_text',
        ],
        computed: [
            'languageId',
            'systemLanguageId',
            'language',
            'languageRepository',
            'infoParent',
            'infoText',
            'isDefaultLanguage',
        ],
        methods: [
            'refreshParentLanguage',
            'onClickParentLanguage',
        ],
    },
});
