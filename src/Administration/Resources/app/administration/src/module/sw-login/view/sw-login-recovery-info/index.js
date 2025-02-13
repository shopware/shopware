/**
 * @sw-package framework
 */

import template from './sw-login-recovery-info.html.twig';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-login-recovery-info', {
    template,

    emits: ['is-not-loading'],

    computed: {
        rateLimitTime() {
            const waitTime = this.$route.params?.waitTime;
            if (typeof waitTime !== 'number') {
                return null;
            }

            return waitTime >= 1 ? waitTime : null;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.$emit('is-not-loading');
        },
    },
});
