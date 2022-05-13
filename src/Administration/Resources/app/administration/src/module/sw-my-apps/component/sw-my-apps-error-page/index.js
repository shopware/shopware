import template from './sw-my-apps-error-page.html.twig';
import './sw-my-apps-error-page.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    methods: {
        goBack() {
            this.$router.go(-1);
        },
    },
};
