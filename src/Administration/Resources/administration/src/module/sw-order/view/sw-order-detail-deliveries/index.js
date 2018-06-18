import { Component } from 'src/core/shopware';
import template from './sw-order-detail-deliveries.html.twig';
import './sw-order-detail-deliveries.less';

Component.register('sw-order-detail-deliveries', {
    template,

    props: {
        order: {
            type: Object,
            required: true,
            default: {}
        }
    }
});
