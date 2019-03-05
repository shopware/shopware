import { Component } from 'src/core/shopware';
import template from './sw-promotion-detail-base.html.twig';

Component.register('sw-promotion-detail-base', {
    template,

    props: {
        promotion: {
            type: Object,
            required: true,
            default: {}
        }
    }
});
