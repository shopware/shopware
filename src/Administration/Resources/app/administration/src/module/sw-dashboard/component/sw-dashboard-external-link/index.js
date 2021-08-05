import template from './sw-dashboard-external-link.html.twig';
import './sw-dashboard-external-link.scss';

const { Component } = Shopware;

Component.register('sw-dashboard-external-link', {
    template,
    props: {
        title: {
            type: String,
            required: true,
        },

        link: {
            type: String,
            required: true,
        },
    },
});
