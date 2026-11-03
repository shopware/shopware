import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-text-preview',
    load: () => import('./src/sw-text-preview'),
    public: {
        props: [
            'text',
            'maximumLength',
            'modalTitle',
            'maximumNewLines',
        ],
        blocks: [
            'sw_text_preview',
            'sw_text_preview_shortened_content',
            'sw_text_preview_expand_button',
            'sw_text_preview_modal',
            'sw_text_preview_modal_content',
            'sw_text_preview_modal_footer',
        ],
        computed: [
            'shortenedText',
            'fullText',
        ],
        methods: [
            'closeModal',
            'openModal',
        ],
    },
});
