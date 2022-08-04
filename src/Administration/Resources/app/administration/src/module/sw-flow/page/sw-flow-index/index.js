import template from './sw-flow-index.html.twig';
import './sw-flow-index.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-flow-index', {
    template,

    inject: ['acl'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            term: '',
            total: 0,
            showUploadModal: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    methods: {
        onSearch(term) {
            this.term = term;
        },
    },
});
