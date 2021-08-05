import template from './sw-cms-el-config-vimeo-video.html.twig';
import './sw-cms-el-config-vimeo-video.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-config-vimeo-video', {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    data() {
        return {
            mediaModalIsOpen: false,
            initialFolderId: null,
        };
    },

    computed: {
        videoID: {
            get() {
                return this.element.config.videoID.value;
            },

            set(link) {
                this.element.config.videoID.value = this.shortenLink(link);
            },
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        uploadTag() {
            return `cms-element-vimeo-video-config-${this.element.id}`;
        },

        previewSource() {
            if (this.element.data && this.element.data.previewMedia && this.element.data.previewMedia.id) {
                return this.element.data.previewMedia;
            }

            return this.element.config.previewMedia.value;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('vimeo-video');
        },

        shortenLink(link) {
            const videoLink = link;
            const videoIDPrefix = /https:\/\/vimeo\.com\//;
            const videoIDPostfix = /#/;
            let shortenLink = videoLink.replace(videoIDPrefix, '');

            if (videoIDPostfix.test(shortenLink)) {
                const positionOfPostfix = videoIDPostfix.exec(shortenLink).index;
                shortenLink = shortenLink.substring(0, positionOfPostfix);
            }

            return shortenLink;
        },

        async onImageUpload({ targetId }) {
            const mediaEntity = await this.mediaRepository.get(targetId);

            this.element.config.previewMedia.value = mediaEntity.id;

            this.updateElementData(mediaEntity);

            this.$emit('element-update', this.element);
        },

        onImageRemove() {
            this.element.config.previewMedia.value = null;

            this.updateElementData();

            this.$emit('element-update', this.element);
        },

        onCloseModal() {
            this.mediaModalIsOpen = false;
        },

        onSelectionChanges(mediaEntity) {
            const media = mediaEntity[0];
            this.element.config.previewMedia.value = media.id;

            this.updateElementData(media);

            this.$emit('element-update', this.element);
        },

        updateElementData(media = null) {
            this.$set(this.element.data, 'previewMediaId', media === null ? null : media.id);
            this.$set(this.element.data, 'previewMedia', media);
        },

        onOpenMediaModal() {
            this.mediaModalIsOpen = true;
        },
    },
});
