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
    name: 'sw-category-detail-menu',
    load: () => import('./src/sw-category-detail-menu'),
    public: {
        props: [
            'category',
            'isLoading',
        ],
        blocks: [
            'sw_category_detail_menu',
            'sw_category_detail_information_visible',
            'sw_category_detail_menu_media',
            'sw_category_detail_menu_media_modal',
            'sw_category_detail_menu_description',
        ],
        computed: [
            'reversedVisibility',
            'mediaItem',
            'mediaRepository',
        ],
        methods: [
            'onMediaSelectionChange',
            'onSetMediaItem',
            'onRemoveMediaItem',
            'onMediaDropped',
        ],
    },
});
