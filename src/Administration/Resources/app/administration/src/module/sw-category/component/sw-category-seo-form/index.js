import template from './sw-category-seo-form.html.twig';
import './sw-category-seo-form.scss';

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl'],

    props: {
        category: {
            type: Object,
            required: true,
        },
    },

    computed: {
        recommendedMetaTitleLength() {
            // Google truncates longer titles in the SERP snippet
            return 70;
        },

        recommendedMetaDescriptionLength() {
            // Google trims at about 175 characters by pixel width, 150 keeps the description from being cut off
            return 150;
        },

        metaTitleLength() {
            return this.category.metaTitle?.length ?? 0;
        },

        metaDescriptionLength() {
            return this.category.metaDescription?.length ?? 0;
        },

        isMetaTitleExceeded() {
            return this.metaTitleLength > this.recommendedMetaTitleLength;
        },

        isMetaDescriptionExceeded() {
            return this.metaDescriptionLength > this.recommendedMetaDescriptionLength;
        },

        metaTitleHint() {
            return this.$t('sw-category.base.seo.recommendedLength', {
                count: this.metaTitleLength,
                max: this.recommendedMetaTitleLength,
            });
        },

        metaDescriptionHint() {
            return this.$t('sw-category.base.seo.recommendedLength', {
                count: this.metaDescriptionLength,
                max: this.recommendedMetaDescriptionLength,
            });
        },
    },
};
