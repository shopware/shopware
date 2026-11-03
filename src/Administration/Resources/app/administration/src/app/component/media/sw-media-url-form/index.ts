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
    name: 'sw-media-url-form',
    load: () => import('./src/sw-media-url-form'),
    public: {
        props: [
            'variant',
        ],
        emits: [
            'media-url-form-submit',
            'modal-close',
        ],
        blocks: [
            'sw_media_url_form',
            'sw_media_url_form_input',
            'sw_media_url_form_footer',
            'sw_media_url_form_cancel_button',
            'sw_media_url_form_submit_button',
            'sw_media_url_form_input_inline',
        ],
        computed: [
            'urlObject',
            'hasInvalidInput',
            'invalidUrlError',
            'missingFileExtension',
            'fileExtension',
            'isValid',
        ],
        methods: [
            'mountedComponent',
            'emitUrl',
            'onModalChange',
        ],
    },
});
