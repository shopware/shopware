import { Component } from 'src/core/shopware';
import template from './sw-promotion-detail-rebate.html.twig';

Component.register('sw-promotion-detail-rebate', {
    template,

    props: {
        promotion: {
            type: Object,
            required: true,
            default: {}
        }
    }
});
