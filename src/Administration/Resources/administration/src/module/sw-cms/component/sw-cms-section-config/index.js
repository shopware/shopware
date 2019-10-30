import template from './sw-cms-section-config.html.twig';
import './sw-cms-section-config.scss';

const { Component } = Shopware;

Component.register('sw-cms-section-config', {
    template,

    inject: [
        'repositoryFactory',
        'cmsService',
        'context'
    ],

    props: {
        section: {
            type: Object,
            required: true
        }
    },

    computed: {
        uploadTag() {
            return `cms-section-media-config-${this.section.id}`;
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
            this.section.backgroundMediaId = mediaItem.id;
            this.section.backgroundMedia = mediaItem;
        },

        successfulUpload(media) {
            this.section.backgroundMediaId = media.targetId;

            this.mediaRepository.get(media.targetId, this.context).then((mediaItem) => {
                this.section.backgroundMedia = mediaItem;
            });
        },

        removeMedia() {
            this.section.backgroundMediaId = null;
            this.section.backgroundMedia = null;
        },

        onSectionDelete(sectionId) {
            this.$emit('section-delete', sectionId);
        },

        onSectionDuplicate(section) {
            this.$emit('section-duplicate', section);
        }
    }
});
