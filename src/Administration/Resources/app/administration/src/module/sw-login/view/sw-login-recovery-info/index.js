import template from './sw-login-recovery-info.html.twig';

const { Component } = Shopware;

Component.register('sw-login-recovery-info', {
    template,

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.$emit('is-not-loading');
        },
    },
});
