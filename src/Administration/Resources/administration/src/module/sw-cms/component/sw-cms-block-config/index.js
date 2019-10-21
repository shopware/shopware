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

    model: {
        prop: 'block',
        event: 'block-update'
    },

    props: {
        block: {
            type: Object,
            required: true,
            default() {
                return {};
            }
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

    watch: {
        block: {
            deep: true,
            handler() {
                this.$emit('block-update', this.block);
            }
        }
    },

    methods: {
        onSetBackgroundMedia([mediaItem]) {
            this.block.backgroundMediaId = mediaItem.id;
            this.block.backgroundMedia = mediaItem;
            this.$emit('block-update', this.block);
        },

        successfulUpload(media) {
            this.block.backgroundMediaId = media.targetId;

            this.mediaRepository.get(media.targetId, this.apiContext).then((mediaItem) => {
                this.block.backgroundMedia = mediaItem;
                this.$emit('block-update', this.block);
            });
        },

        removeMedia() {
            this.block.backgroundMediaId = null;
            this.block.backgroundMedia = null;

            this.$emit('block-update', this.block);
        }
    }
});
