/*
 * @sw-package inventory
 */

import template from './sw-product-seo-form.html.twig';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();
const createId = Shopware.Utils.createId;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('placeholder'),
    ],

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data() {
        return {
            variants: [],
            searchTerm: '',
            canonicalProductSwitchEnabled: false,
            switchStateHasBeenSet: false,
            shouldKeepSelectValue: false,
            selectValue: null,
            showOgMediaModal: false,
            openGraphMediaItem: null,
            openGraphMediaUploadTag: `sw-product-seo-form-og-image-${createId().substring(0, 8)}`,
        };
    },

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        },

        currentOpenGraphMediaId() {
            return this.product?.openGraphMediaId ?? this.parentProduct?.openGraphMediaId ?? null;
        },

        hasParent() {
            return !!this.parentProduct.id;
        },

        hasVariants() {
            return this.product.childCount > 0;
        },

        variantCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('options.group');

            criteria.addFilter(Criteria.equals('parentId', this.product.id));

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);

                // split search term by words
                const terms = this.searchTerm.split(' ').filter((term) => {
                    return term !== '';
                });

                terms.forEach((term) => {
                    criteria.addQuery(Criteria.equals('product.options.name', term), 3500);
                    criteria.addQuery(Criteria.contains('product.options.name', term), 500);
                });
            }

            return criteria;
        },

        isCanonicalUrlSelectLoading() {
            return this.variants.length < 1;
        },

        variantsWithResetOption() {
            const variants = this.variants;

            variants.unshift({
                id: null,
                name: this.$t('sw-product.seoForm.placeholderCanonicalProduct'),
            });

            return variants;
        },

        product() {
            return Shopware.Store.get('swProductDetail').product;
        },

        parentProduct() {
            return Shopware.Store.get('swProductDetail').parentProduct;
        },

        isLoading() {
            return Shopware.Store.get('swProductDetail').isLoading;
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        ...mapPropertyErrors('product', [
            'keywords',
            'metaDescription',
            'metaTitle',
            'ogTitle',
            'ogDescription',
        ]),
    },

    watch: {
        'product.canonicalProductId': {
            handler(value) {
                /* Return if value is undefined or the switch state has been set the very first time.
                 * The reason to return is that using `immediate` on this watcher the very first value is always `undefined`.
                 * So when the product actually has a `canonicalProductId` the switch will be initially off instead off on.
                 */
                if (value === undefined || this.switchStateHasBeenSet) {
                    return;
                }

                this.canonicalProductSwitchEnabled = !!value;
                this.switchStateHasBeenSet = true;
            },
            immediate: true,
        },

        'product.id': {
            handler: function (value) {
                if (!value) {
                    return;
                }

                this.fetchVariants();
            },
            immediate: true,
        },

        canonicalProductSwitchEnabled(isEnabled) {
            if (!this.shouldKeepSelectValue) {
                this.shouldKeepSelectValue = true;

                return;
            }

            /* When the switch state is false it saves the variant id internally.
             * And when the switch is enabled and the value is not null it sets back the variant id.
             */
            if (isEnabled) {
                this.product.canonicalProductId = this.selectValue;
                this.selectValue = null;

                return;
            }

            this.selectValue = this.product.canonicalProductId;
            this.product.canonicalProductId = null;
        },

        isLoading(isLoading) {
            if (isLoading) {
                return;
            }

            this.selectValue = this.product.canonicalProductId;
        },

        currentOpenGraphMediaId: {
            async handler(mediaId) {
                if (!mediaId) {
                    this.openGraphMediaItem = null;
                    return;
                }

                const media = this.getLoadedOpenGraphMedia(mediaId);

                if (media) {
                    this.openGraphMediaItem = media;
                    return;
                }

                const fetchedMedia = await this.mediaRepository.get(mediaId);

                if (this.currentOpenGraphMediaId !== mediaId) {
                    return;
                }

                this.openGraphMediaItem = fetchedMedia;
            },
            immediate: true,
        },
    },

    methods: {
        fetchVariants() {
            return this.productRepository.search(this.variantCriteria).then((variants) => {
                this.variants = variants;

                return variants;
            });
        },

        getItemName(item) {
            if (!item.id) {
                return item.name;
            }

            return item.translated.name || this.product.translated.name;
        },

        onSearch(searchTerm) {
            this.searchTerm = searchTerm;

            this.fetchVariants().then((variants) => {
                this.$refs.canonicalProductSelect.results = variants;

                this.$nextTick().then(() => {
                    this.$refs.canonicalProductSelect.resetActiveItem();
                });
            });
        },

        getLoadedOpenGraphMedia(mediaId) {
            if (!mediaId) {
                return null;
            }

            if (this.product?.openGraphMedia?.id === mediaId) {
                return this.product.openGraphMedia;
            }

            if (this.parentProduct?.openGraphMedia?.id === mediaId) {
                return this.parentProduct.openGraphMedia;
            }

            return null;
        },

        onOpenOgMediaModal() {
            this.showOgMediaModal = true;
        },

        onCloseOgMediaModal() {
            this.showOgMediaModal = false;
        },

        onRemoveOgMedia(updateCurrentValue) {
            this.openGraphMediaItem = null;
            updateCurrentValue(null);
        },

        onOgMediaUploadFinish({ targetId }, updateCurrentValue) {
            updateCurrentValue(targetId);
        },

        onOgMediaSelectionChange(selection, updateCurrentValue) {
            if (selection.length !== 1) {
                this.onRemoveOgMedia(updateCurrentValue);
                return;
            }

            const [selected] = selection;
            this.openGraphMediaItem = selected;
            updateCurrentValue(selected.id);
            this.showOgMediaModal = false;
        },
    },
};
