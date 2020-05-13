import template from './sw-sales-channel-modal-disconnect.html.twig';
import './sw-sales-channel-modal-disconnect.scss';

const { Component } = Shopware;

Component.register('sw-sales-channel-modal-disconnect', {
    template,

    props: {
        isDisconnectLoading: {
            type: Boolean,
            required: true
        },

        isDisconnectSuccessful: {
            type: Boolean,
            required: true
        }
    }
});
