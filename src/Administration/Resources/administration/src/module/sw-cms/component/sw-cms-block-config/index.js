import template from './sw-cms-block-config.html.twig';
import './sw-cms-block-config.scss';

const { Component } = Shopware;

Component.register('sw-cms-block-config', {
    template,

    inject: [
        'repositoryFactory',
        'cmsService',
        'apiContext'
    ],

    props: {
        block: {
            type: Object,
            required: true
        }
    },

    computed: {
        uploadTag() {
            return `cms-block-media-config-${this.block.id}`;
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        cmsPageState() {
            return this.$store.state.cmsPageState;
        }
    },

    methods: {
        onSetBackgroundMedia([mediaItem]) {
            this.block.backgroundMediaId = mediaItem.id;
            this.block.backgroundMedia = mediaItem;
        },

        successfulUpload(media) {
            this.block.backgroundMediaId = media.targetId;

            this.mediaRepository.get(media.targetId, this.apiContext).then((mediaItem) => {
                this.block.backgroundMedia = mediaItem;
            });
        },

        removeMedia() {
            this.block.backgroundMediaId = null;
            this.block.backgroundMedia = null;
        }
    }
});
