import template from './sw-select-field.html.twig';

const { Component } = Shopware;

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for sw-select-field and mt-select-field. Autoswitches between the two components.
 */
Component.register('sw-select-field', {
    template,

    props: {
        options: {
            type: Array,
            required: false,
        },
    },
});
