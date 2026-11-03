import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-cms-page-select',
    load: () => import('./src/sw-cms-page-select'),
    public: {
        props: [
            'pageType',
            'value',
        ],
        emits: [
            'update:value',
        ],
        blocks: [
            'sw_cms_page_select',
        ],
        computed: [
            'bind',
            'translations',
            'pageTypeCriteria',
        ],
        methods: [
            'getTranslations',
        ],
    },
});
