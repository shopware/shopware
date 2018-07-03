import { Component } from 'src/core/shopware';
import template from './sw-order-detail-deliveries.html.twig';

Component.register('sw-order-detail-deliveries', {
    template,

    props: {
        order: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        }
    }
});
