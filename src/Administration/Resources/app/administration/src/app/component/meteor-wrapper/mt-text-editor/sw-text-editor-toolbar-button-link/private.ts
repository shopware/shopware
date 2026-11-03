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
    name: 'sw-text-editor-toolbar-button-link',
    load: () => import('./src/sw-text-editor-toolbar-button-link'),
    public: {
        props: [
            'editor',
            'button',
            'disabled',
        ],
        computed: [
            'linkOptions',
            'buttonVariantList',
            'seoUrlReplacePrefix',
            'categoryRepository',
            'showOpenInNewTabToggle',
            'productEntityFilter',
            'entityFilter',
        ],
        methods: [
            'getEditor',
            'openLinkModal',
            'parseLink',
            'parseButtonClass',
            'applyLink',
            'removeLink',
            'isLink',
            'prepareLink',
            'prepareClass',
            'prepareTarget',
            'addProtocolToLink',
            'getCategoryCollection',
            'getEmptyCategoryCollection',
            'replaceCategorySelection',
            'removeCategorySelection',
            'onSelectFieldChange',
        ],
    },
});
