import { type PropType } from 'vue';
import template from './sw-cms-section-config.html.twig';
import './sw-cms-section-config.scss';
import type MediaUploadResult from '../../../shared/MediaUploadResult';

const { Mixin } = Shopware;

/**
 * @package buyers-experience
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'repositoryFactory',
    ],

    emits: ['section-delete', 'section-duplicate'],

    mixins: [
        Mixin.getByName('cms-state'),
    ],

    props: {
        section: {
            type: Object as PropType<EntitySchema.Entity<'cms_section'>>,
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
            return Shopware.Store.get('cmsPageState');
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
        onSetBackgroundMedia([mediaItem]: EntitySchema.Entity<'media'>[]) {
            this.section.backgroundMediaId = mediaItem.id;
            this.section.backgroundMedia = mediaItem;
        },

        async successfulUpload(media: MediaUploadResult) {
            this.section.backgroundMediaId = media.targetId;

            this.section.backgroundMedia = await this.mediaRepository.get(media.targetId) ?? undefined;
        },

        removeMedia() {
            this.section.backgroundMediaId = undefined;
            this.section.backgroundMedia = undefined;
        },

        onSectionDelete(sectionId: string) {
            if (this.quickactionsDisabled) {
                return;
            }

            this.$emit('section-delete', sectionId);
        },

        onSectionDuplicate(section: EntitySchema.Entity<'cms_section'>) {
            if (this.quickactionsDisabled) {
                return;
            }

            this.$emit('section-duplicate', section);
        },
    },
});
