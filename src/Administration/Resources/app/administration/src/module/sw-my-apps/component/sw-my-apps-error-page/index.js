import template from './sw-my-apps-error-page.html.twig';
import './sw-my-apps-error-page.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-my-apps-error-page', {
    template,

    methods: {
        goBack() {
            this.$router.go(-1);
        },
    },
});
