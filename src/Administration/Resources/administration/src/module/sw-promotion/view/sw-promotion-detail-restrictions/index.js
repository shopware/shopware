import { Component } from 'src/core/shopware';
import template from './sw-promotion-detail-restrictions.html.twig';

Component.register('sw-promotion-detail-restrictions', {
    template,

    props: {
        promotion: {
            type: Object,
            required: true,
            default: {}
        }
    }
});
