import template from './sw-category-seo-form.html.twig';
import './sw-category-seo-form.scss';
import { aclService } from '../dependencies';

/**
 * @sw-package discovery
 *
 * @public
 */
export default {
    template,

    inject: [aclService],

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
    },

    methods: {
        getRecommendedLengthClass(count, max) {
            return { 'is--exceeded': count > max };
        },

        getRecommendedLengthHint(count, max) {
            return this.$t('sw-category.base.seo.recommendedLength', { count, max });
        },
    },
};
