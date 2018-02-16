import { Component } from 'src/core/shopware';
import './sw-card.less';
import template from './sw-card.html.twig';

Component.register('sw-card', {
    props: {
        title: {
            type: String,
            required: true
        }
    },

    template
});
