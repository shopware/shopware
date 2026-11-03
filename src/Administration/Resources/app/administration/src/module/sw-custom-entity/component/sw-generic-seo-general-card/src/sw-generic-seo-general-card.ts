/**
 * @sw-package inventory
 */

import template from './sw-generic-seo-general-card.html.twig';
import './sw-generic-seo-general-card.scss';
import { repositoryFactoryService, wrapComponentConfig } from '../dependencies';

/**
 * @private
 */
export default wrapComponentConfig({
    template,

    inject: [repositoryFactoryService],

    emits: ['update:seo-meta-title', 'update:seo-meta-description', 'update:seo-url'],

    props: {
        seoMetaTitle: {
            type: String as PropType<string | null>,
            required: false,
            default: '',
        },
        seoMetaDescription: {
            type: String as PropType<string | null>,
            required: false,
            default: '',
        },
        seoUrl: {
            type: String as PropType<string | null>,
            required: false,
            default: '',
        },
    },

    methods: {
        emitSeoMetaTitle(seoMetaTitle: string) {
            this.$emit('update:seo-meta-title', seoMetaTitle);
        },

        emitSeoMetaDescription(seoMetaDescription: string) {
            this.$emit('update:seo-meta-description', seoMetaDescription);
        },

        emitSeoUrl(seoUrl: string) {
            this.$emit('update:seo-url', seoUrl);
        },
    },
});
