import template from './sw-cms-block-config.html.twig';
import './sw-cms-block-config.scss';

const { Mixin, Utils } = Shopware;

/**
 * @private
 * @package content
 */
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
        block: {
            type: Object,
            required: true,
        },
    },

    computed: {
        uploadTag() {
            return `cms-block-media-config-${this.block.id}`;
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        cmsPageState() {
            return Shopware.State.get('cmsPageState');
        },

        cmsBlocks() {
            return this.cmsService.getCmsBlockRegistry();
        },

        blockConfig() {
            return this.cmsBlocks[this.block.type];
        },

        quickactionsDisabled() {
            return !this.isSystemDefaultLanguage || this.blockConfig.removable === false;
        },

        quickactionClasses() {
            return {
                'is--disabled': this.quickactionsDisabled,
            };
        },
    },

    methods: {
        onSetBackgroundMedia([mediaItem]) {
            this.block.backgroundMediaId = mediaItem.id;
            this.block.backgroundMedia = mediaItem;
        },

        successfulUpload(media) {
            this.block.backgroundMediaId = media.targetId;

            this.mediaRepository.get(media.targetId).then((mediaItem) => {
                this.block.backgroundMedia = mediaItem;
            });
        },

        removeMedia() {
            this.block.backgroundMediaId = null;
            this.block.backgroundMedia = null;
        },

        onBlockDelete() {
            if (this.quickactionsDisabled) {
                return;
            }

            this.$emit('block-delete', this.block);
        },

        onBlockDuplicate() {
            if (this.quickactionsDisabled) {
                return;
            }

            this.$emit('block-duplicate', this.block);
        },

        onBlockNameChange: Utils.debounce(function debouncedOnChange(value) {
            this.block.name = value;
        }, 400),
    },
};
