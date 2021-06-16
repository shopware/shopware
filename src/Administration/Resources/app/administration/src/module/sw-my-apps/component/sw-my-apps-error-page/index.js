import template from './sw-my-apps-error-page.html.twig';
import './sw-my-apps-error-page.scss';

const { Component } = Shopware;

Component.register('sw-my-apps-error-page', {
    template,

    methods: {
        goBack() {
            this.$router.go(-1);
        },
    },
});
