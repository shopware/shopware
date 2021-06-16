import template from './sw-privilege-error.html.twig';
import './sw-privilege-error.scss';

Shopware.Component.register('sw-privilege-error', {
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
});
