import { Component } from 'src/core/shopware';
import template from './sw-category-detail-cms.html.twig';
import './sw-category-detail-cms.scss';

Component.register('sw-category-detail-cms', {
    template,

    props: {
        category: {
            type: Object,
            required: true
        },
        cmsPage: {
            type: Object,
            required: true,
            default: null
        },
        mediaItem: {
            type: Object,
            required: false,
            default: null
        },
        isLoading: {
            type: Boolean,
            required: true
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.$emit('sw-category-base-on-layout-change', this.category.cmsPageId);
        }
    }
});
