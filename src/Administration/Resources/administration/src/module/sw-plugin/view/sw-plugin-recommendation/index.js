import { Component } from '../../../../core/shopware';
import template from './sw-plugin-recommendation.html.twig';

Component.register('sw-plugin-recommendation', {
    template,

    props: {
        pageLoading: {
            type: Boolean,
            required: false,
            default: false
        }
    }
});
