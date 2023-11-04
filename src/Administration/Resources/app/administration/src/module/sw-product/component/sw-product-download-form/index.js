import template from './sw-product-download-form.html.twig';
import './sw-product-download-form.scss';

const { Component, Mixin } = Shopware;
const { format } = Shopware.Utils;
const { mapGetters } = Component.getComponentHelper();

/**
 * @private
 */
export default {
    template,

    inject: ['repositoryFactory', 'acl', 'configService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        isInherited: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            isMediaLoading: false,
            fileAcceptedExtensions: [],
        };
    },

    computed: {
        ...mapGetters('error', [
            'getApiError',
        ]),

        product() {
            const state = Shopware.State.get('swProductDetail');

            if (this.isInherited) {
                return state.parentProduct;
            }

            return state.product;
        },

        ...mapGetters('swProductDetail', {
            isStoreLoading: 'isLoading',
        }),

        isLoading() {
            return this.isMediaLoading || this.isStoreLoading;
        },

        productDownloadRepository() {
            return this.repositoryFactory.create('product_download');
        },

        productDownloads() {
            if (!this.product) {
                return [];
            }
            return this.product.downloads;
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        error() {
            return this.getApiError(this.product, 'downloads');
        },

        hasError() {
            return !!this.error;
        },

        swFieldClasses() {
            return {
                'has--error': this.hasError,
            };
        },

        fileAccept() {
            let list = '';
            this.fileAcceptedExtensions.forEach((extension) => {
                if (list === '') {
                    list = `*/${extension}`;
                } else {
                    list += `,*/${extension}`;
                }
            });

            return list;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.configService.getConfig().then((result) => {
                this.fileAcceptedExtensions = result.settings.private_allowed_extensions;
            });
        },

        onOpenMedia() {
            this.$emit('media-open');
        },

        getFileSize(download) {
            return format.fileSize(download.media.fileSize);
        },

        getFileName(download) {
            if (download.media.fileExtension) {
                return `${download.media.fileName}.${download.media.fileExtension}`;
            }

            return download.media.fileName;
        },

        createdAt(download) {
            const date = download.media.uploadedAt || download.media.createdAt;
            return format.date(date, {
                month: 'numeric',
            });
        },

        onRemoveDownload(download) {
            this.product.downloads.remove(download.id);
        },

        async successfulUpload({ targetId }) {
            // on replace
            if (this.product.downloads.find((productDownload) => productDownload.mediaId === targetId)) {
                return;
            }

            const productDownload = this.createDownloadAssociation(targetId);
            productDownload.media = await this.mediaRepository.get(targetId);

            this.product.downloads.add(productDownload);
            if (this.error) {
                Shopware.State.dispatch(
                    'error/removeApiError',
                    { expression: this.error.selfLink },
                );
            }
        },

        createDownloadAssociation(targetId) {
            const productDownload = this.productDownloadRepository.create();

            productDownload.productId = this.product.id;
            productDownload.mediaId = targetId;
            productDownload.position = this.product.downloads.length;

            return productDownload;
        },

        onUploadFailed(uploadTask) {
            const toRemove = this.product.media.find((productMedia) => {
                return productMedia.mediaId === uploadTask.targetId;
            });
            if (toRemove) {
                if (this.product.coverId === toRemove.id) {
                    this.product.coverId = null;
                }
                this.product.media.remove(toRemove.id);
            }
            this.product.isLoading = false;
        },

        removeFile(productMedia) {
            // remove cover id if mediaId matches
            if (this.product.coverId === productMedia.id) {
                this.product.cover = null;
                this.product.coverId = null;
            }

            if (this.product.coverId === null && this.product.media.length > 0) {
                this.product.coverId = this.product.media.first().id;
            }

            this.product.media.remove(productMedia.id);
        },

        updateMediaItemPositions() {
            this.productMedia.forEach((medium, index) => {
                medium.position = index;
            });
        },
    },
};
