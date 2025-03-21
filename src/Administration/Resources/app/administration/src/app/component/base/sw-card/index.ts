import template from './sw-card.html.twig';

const { Component } = Shopware;

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-card and mt-card. Autoswitches between the two components.
 *
 * @deprecated tag:v6.8.0 - Will be removed, use mt-card instead.
 */
Component.register('sw-card', {
    template,

    methods: {
        getSlots() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access

            return this.$slots;
        },
    },
});
