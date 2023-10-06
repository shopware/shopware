import template from './sw-category-detail-menu.html.twig';

/**
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl', 'repositoryFactory'],

    props: {
        category: {
            type: Object,
            required: true,
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            showMediaModal: false,
        };
    },

    computed: {
        reversedVisibility: {
            get() {
                return !this.category.visible;
            },
            set(visibility) {
                this.category.visible = !visibility;
            },
        },

        mediaItem() {
            return this.category !== null ? this.category.media : null;
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },
    },

    methods: {
        onMediaSelectionChange(mediaItems) {
            const media = mediaItems[0];
            if (!media) {
                return;
            }

            this.mediaRepository.get(media.id).then((updatedMedia) => {
                this.category.mediaId = updatedMedia.id;
                this.category.media = updatedMedia;
            });
        },

        onSetMediaItem({ targetId }) {
            this.mediaRepository.get(targetId).then((updatedMedia) => {
                this.category.mediaId = targetId;
                this.category.media = updatedMedia;
            });
        },

        onRemoveMediaItem() {
            this.category.mediaId = null;
            this.category.media = null;
        },

        onMediaDropped(dropItem) {
            // to be consistent refetch entity with repository
            this.onSetMediaItem({ targetId: dropItem.id });
        },
    },

};
