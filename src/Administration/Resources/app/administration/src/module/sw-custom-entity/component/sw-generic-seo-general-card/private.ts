import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-generic-seo-general-card',
    load: () => import('./src/sw-generic-seo-general-card'),
    public: {
        props: [
            'seoMetaTitle',
            'seoMetaDescription',
            'seoUrl',
        ],
        emits: [
            'update:seo-meta-title',
            'update:seo-meta-description',
            'update:seo-url',
        ],
        blocks: [
            'sw_generic_seo_general_card',
        ],
        methods: [
            'emitSeoMetaTitle',
            'emitSeoMetaDescription',
            'emitSeoUrl',
        ],
    },
});
