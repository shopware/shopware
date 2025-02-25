import template from './sw-icon.html.twig';

const { Component } = Shopware;

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-icon and mt-icon. Autoswitches between the two components.
 */
Component.register('sw-icon', {
    template,

    props: {
        name: {
            type: String,
            required: true,
        },
    },
});
