import type { PropType } from 'vue';

import template from './sw-generic-seo-general-card.html.twig';
import './sw-generic-seo-general-card.scss';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'repositoryFactory',
    ],

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
