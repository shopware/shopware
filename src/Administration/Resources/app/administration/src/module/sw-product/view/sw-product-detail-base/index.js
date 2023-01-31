/*
 * @package inventory
 */

import Criteria from 'src/core/data/criteria.data';
import template from './sw-product-detail-base.html.twig';
import './sw-product-detail-base.scss';

const { Component, Context, Utils, Mixin } = Shopware;
const { mapState, mapGetters } = Component.getComponentHelper();
const { isEmpty } = Utils.types;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        productId: {
            type: String,
            required: false,
            default: null,
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
            'parentProduct',
            'customFieldSets',
            'loading',
        ]),

        ...mapGetters('swProductDetail', [
            'isLoading',
            'showModeSetting',
            'showProductCard',
            'productStates',
        ]),

        ...mapState('swProductDetail', {
        }),

        mediaFormVisible() {
            return !this.loading.product &&
                   !this.loading.parentProduct &&
                   !this.loading.customFieldSets &&
                   !this.loading.media;
        },

        productMediaRepository() {
            return this.repositoryFactory.create(this.product.media.entity);
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

    watch: {
        product() {
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getMediaDefaultFolderId().then((mediaDefaultFolderId) => {
                this.mediaDefaultFolderId = mediaDefaultFolderId;
            });
        },

        getMediaDefaultFolderId() {
            return this.mediaDefaultFolderRepository.search(this.mediaDefaultFolderCriteria, Context.api)
                .then((mediaDefaultFolder) => {
                    const defaultFolder = mediaDefaultFolder.first();

                    if (defaultFolder.folder?.id) {
                        return defaultFolder.folder.id;
                    }

                    return null;
                });
        },

        mediaRemoveInheritanceFunction(newValue) {
            newValue.forEach(({ id, mediaId, position }) => {
                const media = this.productMediaRepository.create(Context.api);
                Object.assign(media, { mediaId, position, productId: this.product.id });
                if (this.parentProduct.coverId === id) {
                    this.product.coverId = media.id;
                }

                this.product.media.push(media);
            });

            this.$refs.productMediaInheritance.forceInheritanceRemove = true;

            return this.product.media;
        },

        mediaRestoreInheritanceFunction() {
            this.$refs.productMediaInheritance.forceInheritanceRemove = false;
            this.product.coverId = null;

            this.product.media.getIds().forEach((mediaId) => {
                this.product.media.remove(mediaId);
            });

            return this.product.media;
        },

        onOpenMediaModal() {
            this.showMediaModal = true;
        },

        onCloseMediaModal() {
            this.showMediaModal = false;
        },

        onOpenDownloadMediaModal() {
            this.showDownloadMediaModal = true;
        },

        onCloseDownloadMediaModal() {
            this.showDownloadMediaModal = false;
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

            const newMedia = this.productMediaRepository.create(Context.api);
            newMedia.mediaId = media.id;
            newMedia.media = {
                url: media.url,
                id: media.id,
            };

            if (isEmpty(this.product.media)) {
                this.setMediaAsCover(newMedia);
            }

            this.product.media.add(newMedia);

            return Promise.resolve();
        },

        isExistingMedia(media) {
            return this.product.media.some(({ id, mediaId }) => {
                return id === media.id || mediaId === media.id;
            });
        },

        setMediaAsCover(media) {
            media.position = 0;
            this.product.coverId = media.id;
        },
    },
};
