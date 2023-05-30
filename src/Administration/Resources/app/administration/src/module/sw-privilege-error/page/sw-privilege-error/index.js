import template from './sw-privilege-error.html.twig';
import './sw-privilege-error.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    methods: {
        routerGoBack() {
            this.$router.go(-1);
        },
    },
};
