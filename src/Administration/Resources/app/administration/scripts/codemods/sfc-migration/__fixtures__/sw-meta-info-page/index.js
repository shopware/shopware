import template from './sw-meta-info-page.html.twig';

export default {
    template,

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    data() {
        return {
            product: null,
        };
    },

    computed: {
        identifier() {
            return this.product ? this.product.name : '';
        },
    },
};
