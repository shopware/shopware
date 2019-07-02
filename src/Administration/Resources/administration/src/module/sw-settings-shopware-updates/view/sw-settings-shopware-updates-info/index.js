import { Component } from 'src/core/shopware';
import template from './sw-shopware-updates-info.html.twig';

Component.register('sw-settings-shopware-updates-info', {
    template,

    props: {
        changelog: {
            type: String,
            required: true
        },
        isLoading: {
            type: Boolean
        }
    }
});
