import { Component } from 'src/core/shopware';
import template from './sw-order-detail-base.html.twig';
import './sw-order-detail-base.less';

Component.register('sw-order-detail-base', {
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
