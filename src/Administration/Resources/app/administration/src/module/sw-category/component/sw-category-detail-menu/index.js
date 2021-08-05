import template from './sw-category-detail-menu.html.twig';

const { Component } = Shopware;

Component.register('sw-category-detail-menu', {
    template,

    inject: ['acl', 'openMediaSidebar', 'repositoryFactory'],

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

});
