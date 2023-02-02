import template from './sw-sales-channel-modal-detail.html.twig';
import './sw-sales-channel-modal-detail.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-sales-channel-modal-detail', {
    template,

    props: {
        detailType: {
            type: Object,
            required: false,
            default: null,
        },
    },
});
