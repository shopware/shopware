import template from './sw-promotion-v2-individual-codes-behavior.html.twig';

const { Component } = Shopware;

Component.register('sw-promotion-v2-individual-codes-behavior', {
    template,

    props: {
        codes: {
            type: Array,
            required: false,
            default: []
        }
    }
});
