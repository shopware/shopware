import template from './sw-external-link.html.twig';

const { Component } = Shopware;

/**
 * @sw-package framework
 * @deprecated tag:6.8.0 - Will be removed. Use mt-external-link instead.
 *
 * @private
 * @status ready
 * @description Wrapper component for mt-external-link.
 */
Component.register('sw-external-link', {
    template,

    methods: {
        getSlots() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access

            return this.$slots;
        },
    },
});
