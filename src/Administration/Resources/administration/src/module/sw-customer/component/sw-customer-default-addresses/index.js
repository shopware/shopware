import { Component } from 'src/core/shopware';
import template from './sw-customer-default-addresses.html.twig';
import './sw-customer-default.addresses.less';

Component.register('sw-customer-default-addresses', {
    template,

    props: {
        customer: {
            type: Object,
            required: true,
            default: {}
        }
    }
});
