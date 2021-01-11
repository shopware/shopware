import template from './sw-extension-store-index.html.twig';
import './sw-extension-store-index.scss';

const { Component } = Shopware;

Component.register('sw-extension-store-index', {
    template,

    props: {
        id: {
            type: String,
            required: false,
            default: null
        }
    },

    methods: {
        updateSearch(term) {
            Shopware.State.commit('shopwareExtensions/setSearchValue', { key: 'term', value: term });
        }
    }
});
