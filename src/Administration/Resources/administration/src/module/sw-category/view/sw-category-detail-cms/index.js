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
    }
});
