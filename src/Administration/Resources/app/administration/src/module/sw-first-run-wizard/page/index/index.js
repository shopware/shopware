import template from './sw-first-run-wizard.html.twig';

const { Component } = Shopware;

Component.register('sw-first-run-wizard', {
    template,

    metaInfo() {
        return {
            title: this.title
        };
    },

    computed: {
        title() {
            return `${this.$tc('sw-first-run-wizard.welcome.modalTitle')}`;
        }
    }
});
