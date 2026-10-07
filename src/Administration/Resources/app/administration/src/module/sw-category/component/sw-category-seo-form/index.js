import template from './sw-category-seo-form.html.twig';
import './sw-category-seo-form.scss';

// Google truncates longer titles in the SERP snippet
const RECOMMENDED_META_TITLE_LENGTH = 70;
// Google trims at about 175 characters by pixel width, 150 keeps the description from being cut off
const RECOMMENDED_META_DESCRIPTION_LENGTH = 150;

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
            return RECOMMENDED_META_TITLE_LENGTH;
        },

        recommendedMetaDescriptionLength() {
            return RECOMMENDED_META_DESCRIPTION_LENGTH;
        },

        metaTitleLength() {
            return this.category.metaTitle?.length ?? 0;
        },

        metaDescriptionLength() {
            return this.category.metaDescription?.length ?? 0;
        },
    },
};
