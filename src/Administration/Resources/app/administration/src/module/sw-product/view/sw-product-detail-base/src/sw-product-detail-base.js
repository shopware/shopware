/*
 * @sw-package inventory
 */

import template from './sw-product-detail-base.html.twig';
import './sw-product-detail-base.scss';
import {
    aclService,
    Criteria,
    getApiContext,
    isEmpty,
    notificationMixin,
    repositoryFactoryService,
    ShopwareError,
    useErrorStore,
    useProductDetailStore,
} from '../dependencies';

/**
 * @public
 */
export default {
    template,

    inject: [repositoryFactoryService, aclService],

    mixins: [notificationMixin],

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
        product() {
            return useProductDetailStore().product;
        },

        isDownloadExternallyManaged() {
            return useProductDetailStore().isDownloadExternallyManaged;
        },

        parentProduct() {
            return useProductDetailStore().parentProduct;
        },

        customFieldSets() {
            return useProductDetailStore().customFieldSets;
        },

        loading() {
            return useProductDetailStore().loading;
        },

        isLoading() {
            return useProductDetailStore().isLoading;
        },

        showModeSetting() {
            return useProductDetailStore().showModeSetting;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, use `productType` instead.
         */
        productStates() {
            return useProductDetailStore().productStates;
        },

        productType() {
            return useProductDetailStore().productType;
        },

        isDownloadCardVisible() {
            return this.productType === 'digital' || this.productStates.includes('is-download');
        },

        mediaFormVisible() {
            return (
                !this.loading.product && !this.loading.parentProduct && !this.loading.customFieldSets && !this.loading.media
            );
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
        product() {},
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

        onDownloadExternalManagementChange(value) {
            useProductDetailStore().isDownloadExternallyManaged = value;
            const errorPath = `product.${this.product.id}.downloads`;

            if (value) {
                const error = useErrorStore().getApiError(this.product, 'downloads');

                if (error?.code === 'SW_PRODUCT_DOWNLOADS_EXTERNAL_MANAGEMENT_REQUIRED') {
                    useErrorStore().removeApiError(errorPath);
                }
                return;
            }

            if (this.productType === 'digital' && !this.product.downloads?.length) {
                this.showRequiredDownloadsError();
            }
        },

        showRequiredDownloadsError() {
            useErrorStore().addApiError({
                expression: `product.${this.product.id}.downloads`,
                error: new ShopwareError({
                    code: 'SW_PRODUCT_DOWNLOADS_EXTERNAL_MANAGEMENT_REQUIRED',
                    detail: this.$t('sw-product.detailBase.downloadsExternalManagementRequired'),
                }),
            });
        },

        showProductCard(key) {
            return useProductDetailStore().showProductCard(key);
        },

        getMediaDefaultFolderId() {
            return this.mediaDefaultFolderRepository
                .search(this.mediaDefaultFolderCriteria, {
                    cacheKey: ['media-default-folder', 'product'],
                })
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
                const media = this.productMediaRepository.create(getApiContext());
                Object.assign(media, {
                    mediaId,
                    position,
                    productId: this.product.id,
                });
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
                        message: this.$t('sw-product.mediaForm.errorMediaItemDuplicated', { fileName }, 0),
                    });
                });
            });
        },

        addMedia(media) {
            if (this.isExistingMedia(media)) {
                return Promise.reject(media);
            }

            const newMedia = this.productMediaRepository.create(getApiContext());
            newMedia.mediaId = media.id;
            newMedia.media = {
                url: media.url,
                id: media.id,
            };

            if (isEmpty(this.product.media) && !this.isSpatial(newMedia)) {
                this.setMediaAsCover(newMedia);
            }

            this.product.media.add(newMedia);

            return Promise.resolve();
        },

        /**
         * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
         */
        isSpatial(productMedia) {
            if (productMedia.media?.fileExtension === 'glb') {
                return true;
            }
            // we need to check the media url since media.fileExtension is set directly after upload
            return !!productMedia.media?.url?.endsWith('.glb');
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
