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
    name: 'sw-generic-social-media-card',
    load: () => import('./src/sw-generic-social-media-card'),
    public: {
        props: [
            'ogTitle',
            'ogDescription',
            'ogImageId',
        ],
        emits: [
            'update:og-image-id',
            'update:og-title',
            'update:og-description',
        ],
        blocks: [
            'sw_generic_social_media_card',
        ],
        computed: [
            'mediaRepository',
            'uploadTag',
        ],
        methods: [
            'onCreated',
            'loadOgImage',
            'removeOgImage',
            'onOpenMediaModal',
            'onCloseMediaModal',
            'onImageUpload',
            'onSelectionChanges',
            'emitMediaId',
            'emitOgTitle',
            'emitOgDescription',
        ],
    },
});
