/*
 * @sw-package inventory
 */

import template from './sw-product-seo-form.html.twig';
import './sw-product-seo-form.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();
const createId = Shopware.Utils.createId;

const DEPRECATION_MESSAGE_SUFFIX = 'The canonical variant select loads the variants itself, page by page.';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'feature'],

    mixins: [Mixin.getByName('placeholder')],

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data() {
        return {
            /**
             * @deprecated tag:v6.8.0 - Will be removed, the canonical variant select loads the variants itself.
             */
            variants: [],
            /**
             * @deprecated tag:v6.8.0 - Will be removed, the canonical variant select keeps its own search term.
             */
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

        canonicalVariantCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('options.group');
            criteria.addFilter(Criteria.equals('parentId', this.product?.id ?? null));

            criteria.addSorting(Criteria.sort('_score', 'DESC'));
            criteria.addSorting(Criteria.sort('productNumber', 'ASC'));
            criteria.addSorting(Criteria.sort('id', 'ASC'));

            return criteria;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed. Use canonicalVariantCriteria instead.
         */
        variantCriteria() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-product-seo-form.variantCriteria is deprecated. Use canonicalVariantCriteria instead.',
            );

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

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement.
         */
        isCanonicalUrlSelectLoading() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                `sw-product-seo-form.isCanonicalUrlSelectLoading is deprecated. ${DEPRECATION_MESSAGE_SUFFIX}`,
            );

            return this.variants.length < 1;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement.
         */
        variantsWithResetOption() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                `sw-product-seo-form.variantsWithResetOption is deprecated. ${DEPRECATION_MESSAGE_SUFFIX}`,
            );

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

        recommendedMetaTitleLength() {
            // matches the limit named in the meta title help text
            return 56;
        },

        recommendedMetaDescriptionLength() {
            // matches the limit named in the meta description help text
            return 155;
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
                if (!value || this.feature.isActive('V6_8_0_0')) {
                    return;
                }

                Shopware.Feature.silence('V6_8_0_0', () => {
                    this.fetchVariants();
                });
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
        getRecommendedLengthClass(value, max) {
            return { 'is--exceeded': (value?.length ?? 0) > max };
        },

        getRecommendedLengthHint(value, max) {
            return this.$t('sw-product.seoForm.recommendedLength', { count: value?.length ?? 0, max });
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement.
         */
        fetchVariants() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                `sw-product-seo-form.fetchVariants() is deprecated. ${DEPRECATION_MESSAGE_SUFFIX}`,
            );

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

        /**
         * @private
         */
        onCanonicalVariantSearchTermChange(searchTerm) {
            this.removeOptionNameQueries();

            searchTerm
                .split(' ')
                .filter((term) => term !== '')
                .forEach((term) => {
                    this.canonicalVariantCriteria.addQuery(Criteria.equals('product.options.name', term), 3500);
                    this.canonicalVariantCriteria.addQuery(Criteria.contains('product.options.name', term), 500);
                });
        },

        /**
         * @private
         */
        onCanonicalVariantSelectCollapsed() {
            this.removeOptionNameQueries();
            this.canonicalVariantCriteria.setTerm('');
        },

        /**
         * @private
         */
        removeOptionNameQueries() {
            this.canonicalVariantCriteria.queries = this.canonicalVariantCriteria.queries.filter(
                ({ query }) => query.field !== 'product.options.name',
            );
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement.
         */
        // eslint-disable-next-line sw-deprecation-rules/require-deprecation-guard -- the select without the v6.8.0.0 flag calls it on every search
        onSearch(searchTerm) {
            this.searchTerm = searchTerm;

            Shopware.Feature.silence('V6_8_0_0', () => {
                this.fetchVariants().then((variants) => {
                    this.$refs.canonicalProductSelect.results = variants;

                    this.$nextTick().then(() => {
                        this.$refs.canonicalProductSelect.resetActiveItem();
                    });
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
