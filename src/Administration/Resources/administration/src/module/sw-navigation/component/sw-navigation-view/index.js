import { Component } from 'src/core/shopware';
import template from './sw-navigation-view.html.twig';
import './sw-navigation-view.scss';

Component.register('sw-navigation-view', {
    template,

    props: {
        navigation: {
            type: Object,
            required: true,
            default: {}
        },
        isLoading: {
            type: Boolean,
            required: true,
            default: false
        }
    }
});
