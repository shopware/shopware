/**
 * @package system-settings
 */
import template from './sw-bulk-edit-product-media.html.twig';

const { Context, Utils, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState } = Shopware.Component.getComponentHelper();
const { isEmpty } = Utils.types;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            showMediaModal: false,
            mediaDefaultFolderId: null,
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
        ]),

        productMediaRepository() {
            return this.repositoryFactory.create('product_media');
        },

        mediaDefaultFolderRepository() {
            return this.repositoryFactory.create('media_default_folder');
        },

        mediaDefaultFolderCriteria() {
            const criteria = new Criteria(1, 1);

            criteria.addAssociation('folder');
            criteria.addFilter(Criteria.equals('entity', 'product'));

            return criteria;
        },

    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadMediaDefaultFolder();
        },

        loadMediaDefaultFolder() {
            this.getMediaDefaultFolderId().then((mediaDefaultFolderId) => {
                this.mediaDefaultFolderId = mediaDefaultFolderId;
            });
        },

        getMediaDefaultFolderId() {
            return this.mediaDefaultFolderRepository.search(this.mediaDefaultFolderCriteria, Context.api)
                .then((mediaDefaultFolder) => {
                    const defaultFolder = mediaDefaultFolder.first();
                    if (defaultFolder === null) {
                        return null;
                    }

                    if (defaultFolder.folder?.id) {
                        return defaultFolder.folder.id;
                    }

                    return null;
                });
        },

        onAddMedia(media) {
            if (isEmpty(media)) {
                return;
            }

            media.forEach((item) => {
                this.addMedia(item).catch(({ fileName }) => {
                    this.createNotificationError({
                        message: this.$tc('sw-product.mediaForm.errorMediaItemDuplicated', 0, { fileName }),
                    });
                });
            });
        },

        addMedia(media) {
            if (this.isExistingMedia(media)) {
                return Promise.reject(media);
            }

            const newMedia = this.productMediaRepository.create(Shopware.Context.api);
            newMedia.mediaId = media.id;
            newMedia.media = {
                url: media.url,
                id: media.id,
            };

            this.product.media.add(newMedia);

            return Promise.resolve();
        },

        isExistingMedia(media) {
            return this.product.media.some(({ id, mediaId }) => {
                return id === media.id || mediaId === media.id;
            });
        },
    },
};
