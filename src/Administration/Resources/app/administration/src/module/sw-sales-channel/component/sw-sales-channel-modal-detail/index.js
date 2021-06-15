import template from './sw-sales-channel-modal-detail.html.twig';
import './sw-sales-channel-modal-detail.scss';

const { Component } = Shopware;

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
