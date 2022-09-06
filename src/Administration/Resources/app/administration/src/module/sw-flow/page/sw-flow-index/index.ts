import type { MetaInfo } from 'vue-meta';
import template from './sw-flow-index.html.twig';
import './sw-flow-index.scss';

const { Component, Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-flow-index', {
    template,

    inject: ['acl'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data(): {
        isLoading: boolean,
        term: string,
        total: number,
        showUploadModal: boolean,
        } {
        return {
            isLoading: false,
            term: '',
            total: 0,
            showUploadModal: false,
        };
    },

    metaInfo(): MetaInfo {
        return {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
            title: this.$createTitle() as string,
        };
    },

    methods: {
        onSearch(term: string): void {
            this.term = term;
        },
    },
});
