import template from './sw-cms-section-config.html.twig';
import './sw-cms-section-config.scss';

/**
 * @package content
 */

const { Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'cmsService',
    ],

    mixins: [
        Mixin.getByName('cms-state'),
    ],

    props: {
        section: {
            type: Object,
            required: true,
        },
    },

    computed: {
        uploadTag() {
            return `cms-section-media-config-${this.section.id}`;
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        cmsPageState() {
            return Shopware.State.get('cmsPageState');
        },

        quickactionsDisabled() {
            return !this.isSystemDefaultLanguage;
        },

        quickactionClasses() {
            return {
                'is--disabled': this.quickactionsDisabled,
            };
        },
    },

    methods: {
        onSetBackgroundMedia([mediaItem]) {
            this.section.backgroundMediaId = mediaItem.id;
            this.section.backgroundMedia = mediaItem;
        },

        successfulUpload(media) {
            this.section.backgroundMediaId = media.targetId;

            this.mediaRepository.get(media.targetId).then((mediaItem) => {
                this.section.backgroundMedia = mediaItem;
            });
        },

        removeMedia() {
            this.section.backgroundMediaId = null;
            this.section.backgroundMedia = null;
        },

        onSectionDelete(sectionId) {
            if (this.quickactionsDisabled) {
                return;
            }

            this.$emit('section-delete', sectionId);
        },

        onSectionDuplicate(section) {
            if (this.quickactionsDisabled) {
                return;
            }

            this.$emit('section-duplicate', section);
        },
    },
};
