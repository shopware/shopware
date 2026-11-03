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
    name: 'sw-media-add-thumbnail-form',
    load: () => import('./src/sw-media-add-thumbnail-form'),
    public: {
        props: [
            'disabled',
        ],
        emits: [
            'thumbnail-form-size-add',
            'on-input',
        ],
        blocks: [
            'sw_media_add_thumbnail_form',
            'sw_media_add_thumbnail_form_form_container',
            'sw_media_add_thumbnail_form_width',
            'sw_media_add_thumbnail_form_lock_button',
            'sw_media_add_thumbnail_form_height',
            'sw_media_add_thumbnail_add_button',
        ],
        computed: [
            'lockedButtonClass',
        ],
        methods: [
            'onLockSwitch',
            'onAdd',
            'widthInputChanged',
            'heightInputChanged',
            'inputChanged',
        ],
    },
});
