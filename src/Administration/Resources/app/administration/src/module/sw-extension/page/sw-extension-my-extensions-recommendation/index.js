import template from './sw-extension-store-recommendation.html.twig';

/**
 * @package merchant-services
 * @private
 */
export default {
    template,

    data() {
        return {
            isLoading: true,
        };
    },

    methods: {
        finishLoading() {
            this.isLoading = false;
        },
    },
};
